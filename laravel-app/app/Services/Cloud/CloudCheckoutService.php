<?php

namespace App\Services\Cloud;

use App\Cloud\CloudPaymentMethod;
use App\Cloud\CloudPaymentMethodCode;
use App\Cloud\CloudSubscription;
use App\Cloud\CloudSubscriptionPayment;
use App\Cloud\CloudSubscriptionStatus;
use App\Cloud\CloudTenant;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

/**
 * Starts a MoMo or VISA checkout using the providers already configured for this install.
 * A browser return does not mark the subscription paid. Confirmation checks the provider.
 */
class CloudCheckoutService
{
    public function start(CloudTenant $tenant, CloudSubscription $subscription, $methodCode)
    {
        $methodCode = strtoupper((string) $methodCode);
        $method = CloudPaymentMethod::where('code', $methodCode)->where('active', true)->first();
        if (! $method) {
            throw new \RuntimeException('That payment method is not available.');
        }

        $subscription->load('plan');
        $amount = $subscription->quoted_price !== null
            ? $subscription->quoted_price
            : ($subscription->plan ? $subscription->plan->price : null);
        $currency = $subscription->quoted_currency
            ?: ($subscription->plan ? $subscription->plan->currency : 'XAF');

        $payment = CloudSubscriptionPayment::create([
            'cloud_tenant_id' => $tenant->id,
            'cloud_subscription_id' => $subscription->id,
            'method_code' => $method->code,
            'amount' => $amount,
            'currency' => $currency ?: 'XAF',
            'provider' => $method->provider,
            'status' => CloudSubscriptionPayment::PENDING,
        ]);
        $subscription->payment_method_code = $method->code;
        $subscription->save();
        if (\Illuminate\Support\Facades\Schema::hasTable('cloud_subscription_events')) {
            \App\Cloud\CloudSubscriptionEvent::create([
                'cloud_tenant_id' => $tenant->id,
                'cloud_subscription_id' => $subscription->id,
                'event' => \App\Cloud\CloudSubscriptionEventType::PAYMENT_REQUESTED,
                'payload' => json_encode(['payment_id' => $payment->id, 'currency' => $payment->currency]),
            ]);
        }

        if ($method->code === CloudPaymentMethodCode::VISA) {
            return $this->stripeLink($tenant, $payment);
        }

        return $this->campayLink($tenant, $payment);
    }

    public function markPaidFromProvider(CloudSubscriptionPayment $payment)
    {
        $reference = (string) $payment->provider_reference;
        $eventId = $payment->provider.':payment:'.$payment->id.($reference !== '' ? ':'.$reference : '');
        app(CloudSubscriptionService::class)->confirmPayment($payment, [
            'provider' => (string) $payment->provider,
            'event_id' => $eventId,
            'status' => 'paid',
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency ?: 'XAF',
            'tenant_id' => (int) $payment->cloud_tenant_id,
            'provider_reference' => $reference,
        ]);

        return $payment->fresh();
    }

    protected function campayLink(CloudTenant $tenant, CloudSubscriptionPayment $payment)
    {
        $token = config('services.campay.token') ?: getenv('CAMPAY_TOKEN') ?: getenv('MOMO_TOKEN');
        if (! $token) {
            throw new \RuntimeException('Mobile money is not configured.');
        }
        $phone = preg_replace('/\D/', '', (string) $tenant->phone);
        $payload = json_encode([
            'amount' => (string) $payment->amount,
            'from' => $phone,
            'currency' => $payment->currency ?: 'XAF',
            'external_reference' => 'cloud-'.$payment->id,
            'redirect_url' => route('cloud.pay.return', ['payment' => $payment->id]),
            'payment_options' => 'MOMO',
            'failure_redirect_url' => route('cloud.pay.return', ['payment' => $payment->id, 'failed' => 1]),
        ]);
        $base = rtrim((string) config('services.campay.base_url', 'https://www.campay.net/api'), '/');
        $raw = $this->postJson($base.'/get_payment_link/', $payload, ['Authorization: Token '.$token]);
        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded) || empty($decoded['link'])) {
            Log::info('Cloud Campay link failed', ['body' => substr((string) $raw, 0, 300)]);
            throw new \RuntimeException('Could not start MoMo payment.');
        }
        if (! empty($decoded['reference'])) {
            $payment->provider_reference = (string) $decoded['reference'];
            $payment->save();
        }

        return $decoded['link'];
    }

    protected function stripeLink(CloudTenant $tenant, CloudSubscriptionPayment $payment)
    {
        $secret = config('services.stripe.secret') ?: getenv('STRIPE_SECRET');
        if (! $secret) {
            throw new \RuntimeException('Card payment is not configured.');
        }
        Stripe::setApiKey($secret);
        $session = StripeSession::create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($payment->currency ?: 'xaf'),
                    'unit_amount' => (int) $payment->amount,
                    'product_data' => [
                        'name' => $tenant->system_name ?: $tenant->name,
                        'description' => 'Beyond Cloud subscription',
                    ],
                ],
                'quantity' => 1,
            ]],
            'success_url' => route('cloud.pay.return', ['payment' => $payment->id], true).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('cloud.subscribe'),
            'metadata' => [
                'cloud_payment_id' => (string) $payment->id,
            ],
        ]);
        $payment->provider_reference = (string) $session->id;
        $payment->save();
        $url = isset($session->url) ? $session->url : null;
        if (! $url) {
            throw new \RuntimeException('Card checkout did not return a payment page.');
        }

        return $url;
    }

    /**
     * Confirm a VISA checkout with Stripe. Returns true only when Stripe says paid.
     */
    public function confirmVisa(CloudSubscriptionPayment $payment, $sessionId)
    {
        $secret = config('services.stripe.secret') ?: getenv('STRIPE_SECRET');
        if (! $secret || ! $sessionId || $sessionId !== $payment->provider_reference) {
            return false;
        }
        Stripe::setApiKey($secret);
        $session = StripeSession::retrieve($sessionId);
        if (! $session || $session->payment_status !== 'paid') {
            return false;
        }
        $charged = isset($session->amount_total) ? (int) $session->amount_total : null;
        $expected = (int) round((float) $payment->amount);
        if ($charged === null || $charged !== $expected) {
            app(CloudSubscriptionService::class)->confirmPayment($payment, [
                'provider' => 'stripe',
                'event_id' => 'stripe:amount:'.$sessionId,
                'status' => 'paid',
                'amount' => $charged === null ? 0 : $charged,
                'currency' => $payment->currency ?: 'XAF',
                'tenant_id' => (int) $payment->cloud_tenant_id,
                'provider_reference' => (string) $sessionId,
            ]);

            return false;
        }
        $this->markPaidFromProvider($payment);

        return true;
    }

    protected function postJson($url, $payload, array $headers)
    {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array_merge($headers, ['Content-Type: application/json', 'Accept: application/json']),
        ]);
        $raw = curl_exec($curl);
        curl_close($curl);

        return $raw;
    }
}
