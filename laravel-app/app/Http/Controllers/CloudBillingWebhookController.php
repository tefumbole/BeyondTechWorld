<?php

namespace App\Http\Controllers;

use App\Cloud\CloudSubscriptionPayment;
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
}
