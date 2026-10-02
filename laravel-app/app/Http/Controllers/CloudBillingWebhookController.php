<?php

namespace App\Http\Controllers;

use App\Cloud\CloudSubscriptionPayment;
use App\Services\Cloud\CloudCampayBillingProvider;
use App\Services\Cloud\CloudSubscriptionService;
use Illuminate\Http\Request;

/**
 * Provider notifications for Beyond Cloud billing.
 * A repeated event id does not extend a subscription twice.
 * Sandbox events are accepted only when cloud.billing_sandbox is on.
 */
class CloudBillingWebhookController extends Controller
{
    public function handle(Request $request, $provider)
    {
        $provider = strtolower((string) $provider);
        if ($provider === 'sandbox' && ! config('cloud.billing_sandbox')) {
            return response('Sandbox billing is off.', 404);
        }
        if (! in_array($provider, ['sandbox', 'stripe', 'campay'], true)) {
            return response('Unknown billing provider.', 404);
        }
        if ($provider === 'campay' && config('cloud.billing_sandbox')) {
            return $this->campayStatus($request);
        }
        if ($provider !== 'sandbox') {
            return response('Payment provider webhook is not activated for production yet.', 501);
        }

        $paymentId = (int) $request->input('payment_id');
        $payment = CloudSubscriptionPayment::find($paymentId);
        if (! $payment || $payment->provider !== 'sandbox') {
            return response('Unknown payment.', 422);
        }

        $result = app(CloudSubscriptionService::class)->confirmPayment($payment, [
            'provider' => 'sandbox',
            'event_id' => (string) $request->input('event_id'),
            'status' => (string) $request->input('status'),
            'amount' => $request->input('amount'),
            'currency' => (string) $request->input('currency'),
            'tenant_id' => (int) $request->input('tenant_id'),
            'provider_reference' => (string) $request->input('provider_reference'),
        ]);

        return response()->json($result);
    }

    /**
     * Campay's transaction-status read is the authority. The posted body does not set the amount.
     * This path runs only while billing_sandbox is on. Production webhooks stay closed.
     */
    protected function campayStatus(Request $request)
    {
        $external = (string) $request->input('external_reference');
        $reference = (string) $request->input('reference');
        $payment = null;
        if ($external !== '') {
            $payment = CloudSubscriptionPayment::where('internal_reference', $external)->first();
        }
        if (! $payment && $reference !== '') {
            $payment = CloudSubscriptionPayment::where('provider_reference', $reference)->first();
        }
        if (! $payment || $payment->provider !== 'campay') {
            return response('Unknown payment.', 422);
        }
        if ($reference !== '' && $payment->provider_reference && $reference !== (string) $payment->provider_reference) {
            $result = app(CloudSubscriptionService::class)->confirmPayment($payment, [
                'provider' => 'campay',
                'event_id' => 'campay:reference:'.$reference,
                'status' => 'paid',
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'tenant_id' => (int) $payment->cloud_tenant_id,
                'provider_reference' => $reference,
            ]);

            return response()->json($result);
        }

        $remote = app(CloudCampayBillingProvider::class)->status($payment);
        if (! is_array($remote)) {
            return response()->json(['applied' => false, 'reason' => 'unverified']);
        }
        $remoteStatus = isset($remote['status']) ? strtoupper((string) $remote['status']) : '';
        $mapped = $remoteStatus === 'SUCCESSFUL' ? 'paid' : ($remoteStatus === 'FAILED' ? 'failed' : 'pending');
        if ($mapped === 'pending') {
            return response()->json(['applied' => false, 'reason' => 'pending', 'payment_status' => $payment->status]);
        }
        $postedTenant = $request->input('tenant_id');
        $tenantId = $postedTenant === null || $postedTenant === ''
            ? (int) $payment->cloud_tenant_id
            : (int) $postedTenant;
        $eventReference = $payment->provider_reference ?: $reference;
        $result = app(CloudSubscriptionService::class)->confirmPayment($payment, [
            'provider' => 'campay',
            'event_id' => 'campay:'.$eventReference.':'.$mapped,
            'status' => $mapped,
            'amount' => isset($remote['amount']) ? $remote['amount'] : null,
            'currency' => isset($remote['currency']) ? $remote['currency'] : '',
            'tenant_id' => $tenantId,
            'provider_reference' => (string) $eventReference,
        ]);

        return response()->json($result);
    }
}
