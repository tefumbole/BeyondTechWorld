<?php

namespace App\Http\Controllers;

use App\Services\Messaging\InfobipSmsProvider;
use App\Services\Messaging\MessagingHub;
use App\Services\Messaging\SmsProviderRegistry;
use Illuminate\Http\Request;

/**
 * Delivery receipts. The company is the connection that owns the provider message id.
 */
class SmsWebhookController extends Controller
{
    public function handle(Request $request, $provider, SmsProviderRegistry $registry, MessagingHub $hub)
    {
        $driver = $registry->resolve($provider);
        $headers = [
            'x-messaging-signature' => (string) $request->header('X-Messaging-Signature', ''),
        ];
        if (! $driver->verifyWebhook($headers, $request->getContent())) {
            return response('Unauthorized', 401);
        }
        if ($driver instanceof InfobipSmsProvider) {
            foreach ($driver->deliveryReports($request->getContent()) as $report) {
                $hub->applyDelivery($driver->name(), $report['provider_message_id'], $report['status'], $report);
            }

            return response('ok', 200);
        }
        $id = (string) $request->input('provider_message_id', '');
        $status = (string) $request->input('status', '');
        if ($id === '') {
            return response('Missing message', 422);
        }
        $hub->applyDelivery($driver->name(), $id, $status);

        return response('ok', 200);
    }
}
