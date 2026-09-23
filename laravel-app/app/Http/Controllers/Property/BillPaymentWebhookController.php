<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Property\BillPaymentRequest;
use Illuminate\Http\Request;

class BillPaymentWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $secret = (string) config('services.property.bill_webhook_secret');
        $raw = $request->getContent();
        $given = (string) $request->header('X-Bill-Signature');
        if ($secret === '' || $given === '' || ! hash_equals(hash_hmac('sha256', $raw, $secret), $given)) {
            return response()->json(['ok' => false], 403);
        }
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return response()->json(['ok' => false], 422);
        }
        $bill = BillPaymentRequest::find(isset($data['request_id']) ? (int) $data['request_id'] : 0);
        if (! $bill) {
            return response()->json(['ok' => false], 404);
        }
        $result = app(\App\Services\Property\BillPaymentService::class)->applyProviderResult(
            $bill,
            isset($data['status']) ? $data['status'] : 'UNKNOWN',
            isset($data['reference']) ? $data['reference'] : null,
            isset($data['event_id']) ? $data['event_id'] : ''
        );
        $fresh = isset($result['request']) ? $result['request'] : $bill;

        return response()->json([
            'ok' => ! empty($result['ok']),
            'duplicate' => ! empty($result['duplicate']),
            'status' => $fresh->status,
        ]);
    }
}
