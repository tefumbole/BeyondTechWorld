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
        if (! config('cloud.payments_live') && ! config('cloud.billing_sandbox')) {
            throw new \RuntimeException('Online renewal is not available yet.');
        }
        $existing = $this->recentPending($tenant, [$subscription->id]);
        if ($existing && config('cloud.payments_live')) {
            return route('cloud.pay.return', ['payment' => $existing->id]);
        }

        $payment = app(CloudBillingCheckout::class)->open($tenant, [$subscription], $methodCode);
        $subscription->payment_method_code = $payment->method_code;
        $subscription->save();
        if (\Illuminate\Support\Facades\Schema::hasTable('cloud_subscription_events')) {
            \App\Cloud\CloudSubscriptionEvent::create([
                'cloud_tenant_id' => $tenant->id,
                'cloud_subscription_id' => $subscription->id,
                'event' => \App\Cloud\CloudSubscriptionEventType::PAYMENT_REQUESTED,
                'payload' => json_encode([
                    'payment_id' => $payment->id,
                    'currency' => $payment->currency,
                    'amount' => $payment->amount,
                ]),
            ]);
        }

        if (! config('cloud.payments_live')) {
            return $this->sandboxHold($payment);
        }

        if ($payment->method_code === CloudPaymentMethodCode::VISA) {
            return $this->stripeLink($tenant, $payment);
        }
        if ($payment->method_code === CloudPaymentMethodCode::CRYPTO) {
            return $this->cryptoLink($payment);
        }

        $link = app(CloudCampayBillingProvider::class)->begin($payment);
        if (! $link) {
            throw new \RuntimeException('Could not start MoMo payment.');
        }

        return $link;
    }

    public function startMany(CloudTenant $tenant, array $subscriptions, $methodCode)
    {
        if (! config('cloud.payments_live') && ! config('cloud.billing_sandbox')) {
            throw new \RuntimeException('Online renewal is not available yet.');
        }
        $ids = [];
        foreach ($subscriptions as $subscription) {
            if ($subscription instanceof CloudSubscription) {
                $ids[] = (int) $subscription->id;
            }
        }
        $existing = $this->recentPending($tenant, $ids);
        if ($existing && config('cloud.payments_live')) {
            return route('cloud.pay.return', ['payment' => $existing->id]);
        }
        $payment = app(CloudBillingCheckout::class)->open($tenant, $subscriptions, $methodCode);
        if (! config('cloud.payments_live')) {
            return $this->sandboxHold($payment);
        }
        if ($payment->method_code === CloudPaymentMethodCode::VISA) {
            return $this->stripeLink($tenant, $payment);
        }
        if ($payment->method_code === CloudPaymentMethodCode::CRYPTO) {
            return $this->cryptoLink($payment);
        }
        $link = app(CloudCampayBillingProvider::class)->begin($payment);
        if (! $link) {
            throw new \RuntimeException('Could not start MoMo payment.');
        }

        return $link;
    }

    protected function recentPending(CloudTenant $tenant, array $subscriptionIds)
    {
        if (count($subscriptionIds) !== 1) {
            return null;
        }
        $payment = CloudSubscriptionPayment::where('cloud_tenant_id', $tenant->id)
            ->where('cloud_subscription_id', $subscriptionIds[0])
            ->where('status', CloudSubscriptionPayment::PENDING)
            ->where('created_at', '>=', now()->subMinutes(3))
            ->orderBy('id', 'desc')
            ->first();

        return $payment;
    }

    protected function sandboxHold(CloudSubscriptionPayment $payment)
    {
        $payment->provider = 'sandbox';
        $payment->provider_reference = 'sandbox-'.$payment->id;
        $payment->status = CloudSubscriptionPayment::PENDING;
        $payment->save();

        return route('cloud.pay.return', ['payment' => $payment->id]);
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
     * A browser return is not payment authority. Activation waits for a verified provider status.
     */
    public function confirmVisa(CloudSubscriptionPayment $payment, $sessionId)
    {
        return $this->settle($payment);
    }

    /**
     * Asks the provider whether this charge was collected. A browser return alone does not count.
     */
    public function settle(CloudSubscriptionPayment $payment)
    {
        if ($payment->status === CloudSubscriptionPayment::PAID) {
            return true;
        }
        if ($payment->method_code === CloudPaymentMethodCode::CRYPTO || $payment->provider === 'binance') {
            return $this->settleCrypto($payment);
        }
        if ($payment->method_code === CloudPaymentMethodCode::VISA || $payment->provider === 'stripe') {
            return $this->settleVisa($payment);
        }

        return $this->settleCampay($payment);
    }

    protected function cryptoLink(CloudSubscriptionPayment $payment)
    {
        $result = app(\App\Services\BinancePayService::class)->instructions((float) $payment->amount, (int) $payment->id);
        if (empty($result['ok'])) {
            throw new \RuntimeException(isset($result['error']) ? $result['error'] : 'Crypto payment could not be opened.');
        }
        $snap = json_decode((string) $payment->snapshot, true);
        if (! is_array($snap)) {
            $snap = [];
        }
        $snap['crypto'] = $result;
        $payment->provider = 'binance';
        $payment->snapshot = json_encode($snap);
        $payment->provider_reference = 'usdt:'.$result['usdt'];
        $payment->status = CloudSubscriptionPayment::PENDING;
        $payment->save();

        return route('cloud.pay.return', ['payment' => $payment->id]);
    }

    protected function settleCampay(CloudSubscriptionPayment $payment)
    {
        $remote = app(CloudCampayBillingProvider::class)->status($payment);
        if (! is_array($remote)) {
            return false;
        }
        $remoteStatus = isset($remote['status']) ? strtoupper((string) $remote['status']) : '';
        if ($remoteStatus !== 'SUCCESSFUL' && $remoteStatus !== 'FAILED') {
            return false;
        }
        $reference = (string) ($payment->provider_reference ?: '');
        app(CloudSubscriptionService::class)->confirmPayment($payment, [
            'provider' => 'campay',
            'event_id' => 'campay:'.$reference.':'.strtolower($remoteStatus),
            'status' => $remoteStatus === 'SUCCESSFUL' ? 'paid' : 'failed',
            'amount' => isset($remote['amount']) ? $remote['amount'] : null,
            'currency' => isset($remote['currency']) ? $remote['currency'] : '',
            'tenant_id' => (int) $payment->cloud_tenant_id,
            'provider_reference' => $reference,
        ]);

        return $payment->fresh()->status === CloudSubscriptionPayment::PAID;
    }

    protected function settleVisa(CloudSubscriptionPayment $payment)
    {
        $sessionId = (string) $payment->provider_reference;
        if (strpos($sessionId, 'cs_') !== 0) {
            return false;
        }
        $secret = config('services.stripe.secret') ?: getenv('STRIPE_SECRET');
        if (! $secret) {
            return false;
        }
        Stripe::setApiKey($secret);
        try {
            $session = StripeSession::retrieve($sessionId);
        } catch (\Throwable $e) {
            return false;
        }
        if (! isset($session->payment_status) || $session->payment_status !== 'paid') {
            return false;
        }
        $paidAmount = isset($session->amount_total) ? ((float) $session->amount_total) : null;
        $paidCurrency = isset($session->currency) ? strtoupper((string) $session->currency) : '';
        app(CloudSubscriptionService::class)->confirmPayment($payment, [
            'provider' => 'stripe',
            'event_id' => 'stripe:'.$sessionId.':paid',
            'status' => 'paid',
            'amount' => $paidAmount,
            'currency' => $paidCurrency,
            'tenant_id' => (int) $payment->cloud_tenant_id,
            'provider_reference' => $sessionId,
        ]);

        return $payment->fresh()->status === CloudSubscriptionPayment::PAID;
    }

    protected function settleCrypto(CloudSubscriptionPayment $payment)
    {
        $snap = json_decode((string) $payment->snapshot, true);
        $crypto = is_array($snap) && isset($snap['crypto']) && is_array($snap['crypto']) ? $snap['crypto'] : [];
        $address = isset($crypto['address']) ? (string) $crypto['address'] : '';
        $usdt = isset($crypto['usdt']) ? (string) $crypto['usdt'] : '';
        if ($address === '' || $usdt === '') {
            return false;
        }
        $since = $payment->created_at ? ($payment->created_at->getTimestamp() * 1000) - 60000 : (time() - 86400) * 1000;
        $tx = app(\App\Services\BinancePayService::class)->findPayment($address, $usdt, $since);
        if (! $tx) {
            return false;
        }
        app(CloudSubscriptionService::class)->confirmPayment($payment, [
            'provider' => 'binance',
            'event_id' => 'binance:'.$tx,
            'status' => 'paid',
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency ?: 'XAF',
            'tenant_id' => (int) $payment->cloud_tenant_id,
            'provider_reference' => (string) $payment->provider_reference,
        ]);

        return $payment->fresh()->status === CloudSubscriptionPayment::PAID;
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
