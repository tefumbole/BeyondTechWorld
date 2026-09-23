<?php

namespace App\Services\Property;

use App\Property\BillPaymentRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BillPaymentService
{
    protected $log;
    protected $media;

    public function __construct(PropertyActivityLogger $log, PropertyMediaGuard $media)
    {
        $this->log = $log;
        $this->media = $media;
    }

    public function types()
    {
        $types = config('services.property.bill_types');

        return is_array($types) ? $types : ['electricity', 'water', 'tv', 'internet', 'other'];
    }

    public function create(array $data)
    {
        $messageId = isset($data['provider_message_id']) ? $data['provider_message_id'] : null;
        if ($messageId) {
            $existing = BillPaymentRequest::where('provider_message_id', $messageId)->first();
            if ($existing) {
                return ['ok' => true, 'duplicate' => true, 'request' => $existing];
            }
        }
        $category = $this->category(isset($data['text']) ? $data['text'] : '');
        if (isset($data['bill_category']) && in_array($data['bill_category'], $this->types(), true)) {
            $category = $data['bill_category'];
        }
        $amount = $this->amountFrom(isset($data['text']) ? $data['text'] : '');
        $status = ($category && $amount) ? 'AWAITING_DETAILS' : 'AWAITING_DETAILS';
        $row = BillPaymentRequest::create([
            'customer_id' => isset($data['customer_id']) ? $data['customer_id'] : null,
            'tenancy_id' => isset($data['tenancy_id']) ? $data['tenancy_id'] : null,
            'conversation_id' => isset($data['conversation_id']) ? $data['conversation_id'] : null,
            'bill_category' => $category ?: 'other',
            'provider' => isset($data['provider']) ? $data['provider'] : null,
            'account_reference' => isset($data['account_reference']) ? $data['account_reference'] : null,
            'amount' => $amount,
            'service_fee' => $this->fee(),
            'currency' => (string) config('services.property.currency', 'XAF'),
            'amount_provisional' => $amount !== null,
            'source' => 'whatsapp',
            'status' => $status,
            'provider_message_id' => $messageId,
            'requested_at' => Carbon::now(),
        ]);
        $this->log->write('bill_requested', 'bill', $row->id, [
            'category' => $row->bill_category,
            'status' => $row->status,
        ], $row->conversation_id);

        return ['ok' => true, 'request' => $row];
    }

    public function updateDetails(BillPaymentRequest $row, array $data)
    {
        if (in_array($row->status, ['PAID', 'FAILED', 'CANCELLED'], true)) {
            return ['ok' => false, 'error' => 'closed'];
        }
        if (! empty($data['provider'])) {
            $row->provider = $data['provider'];
        }
        if (! empty($data['account_reference'])) {
            $row->account_reference = $data['account_reference'];
        }
        if (isset($data['amount']) && $data['amount'] !== '') {
            $row->amount = round((float) $data['amount'], 2);
            $row->amount_provisional = ! empty($data['provisional']);
        }
        if (in_array($row->bill_category, $this->types(), true) && $row->provider && $row->account_reference && $row->amount > 0) {
            $row->status = 'SUBMITTED';
        } else {
            $row->status = 'AWAITING_DETAILS';
        }
        $row->save();
        $this->log->write('bill_details', 'bill', $row->id, [
            'status' => $row->status,
            'account_reference' => $row->account_reference,
            'amount' => $row->amount,
        ], $row->conversation_id);

        return ['ok' => true, 'request' => $row];
    }

    public function attach(BillPaymentRequest $row, $bytes, $name, $mime)
    {
        $check = $this->media->check($name, $mime, strlen((string) $bytes), false);
        if (empty($check['ok'])) {
            return ['ok' => false, 'error' => $check['error']];
        }
        $dir = storage_path('app/property-bills/'.$row->id);
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $stored = bin2hex(random_bytes(8)).'.'.$check['ext'];
        $path = $dir.'/'.$stored;
        file_put_contents($path, $bytes);
        DB::table('bill_payment_attachments')->insert([
            'bill_payment_request_id' => $row->id,
            'original_name' => $check['name'],
            'mime' => $check['mime'],
            'size_bytes' => strlen((string) $bytes),
            'path' => $path,
            'extracted_note' => 'Image stored. Any reading of it is provisional until the customer confirms the account and amount.',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        if (! in_array($row->status, ['PAID', 'FAILED', 'CANCELLED', 'UNDER_REVIEW'], true)) {
            $row->status = 'AWAITING_DETAILS';
            $row->save();
        }
        $this->log->write('bill_attachment', 'bill', $row->id, ['mime' => $check['mime']], $row->conversation_id);

        return ['ok' => true, 'provisional' => true];
    }

    public function confirm(BillPaymentRequest $row)
    {
        if ($row->status === 'PAID') {
            return ['ok' => true, 'request' => $row, 'message' => 'This request is already paid.'];
        }
        if (! $row->provider || ! $row->account_reference || (float) $row->amount <= 0) {
            $row->status = 'AWAITING_DETAILS';
            $row->save();

            return ['ok' => false, 'error' => 'incomplete', 'request' => $row];
        }
        $row->status = 'UNDER_REVIEW';
        $row->confirmed_at = Carbon::now();
        $row->service_fee = $this->fee();
        $row->save();
        $this->log->write('bill_confirmed', 'bill', $row->id, [
            'status' => $row->status,
            'amount' => $row->amount,
            'fee' => $row->service_fee,
            'debited' => false,
        ], $row->conversation_id);

        return ['ok' => true, 'request' => $row, 'debited' => false];
    }

    public function applyProviderResult(BillPaymentRequest $row, $status, $reference, $eventId)
    {
        $eventId = trim((string) $eventId);
        if ($eventId === '') {
            return ['ok' => false, 'error' => 'missing_event'];
        }
        $existing = DB::table('bill_payment_events')->where('provider_event_id', $eventId)->first();
        if ($existing) {
            $this->log->write('bill_duplicate_event', 'bill', $row->id, ['event' => $eventId]);

            return ['ok' => true, 'duplicate' => true, 'request' => BillPaymentRequest::find($row->id)];
        }
        $status = strtoupper((string) $status);
        DB::table('bill_payment_events')->insert([
            'bill_payment_request_id' => $row->id,
            'provider_event_id' => $eventId,
            'provider_status' => $status,
            'provider_reference' => $reference,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        if ($status === 'SUCCESS') {
            if ($row->status !== 'PAID') {
                $row->status = 'PAID';
                $row->provider_reference = $reference;
                $row->completed_at = Carbon::now();
                $row->failure_reason = null;
                $row->save();
            }
        } elseif ($status === 'FAILED') {
            if ($row->status !== 'PAID') {
                $row->status = 'FAILED';
                $row->failure_reason = 'The payment provider reported a failure.';
                $row->processed_at = Carbon::now();
                $row->save();
            }
        } else {
            if ($row->status !== 'PAID') {
                $row->status = 'PENDING_CONFIRMATION';
                $row->provider_reference = $reference ?: $row->provider_reference;
                $row->save();
            }
        }
        $this->log->write('bill_provider_result', 'bill', $row->id, [
            'status' => $row->status,
            'event' => $eventId,
        ]);

        return ['ok' => true, 'duplicate' => false, 'request' => $row->fresh()];
    }

    public function reconcile(BillPaymentRequest $row)
    {
        $latest = DB::table('bill_payment_events')->where('bill_payment_request_id', $row->id)->orderByDesc('id')->first();
        if (! $latest) {
            if ($row->status !== 'PAID') {
                $row->status = 'PENDING_CONFIRMATION';
                $row->save();
            }
            $this->log->write('bill_reconcile', 'bill', $row->id, ['result' => 'no_event', 'debited' => false]);

            return ['ok' => true, 'request' => $row->fresh(), 'debited' => false];
        }

        $result = $this->applyProviderResult($row->fresh(), $latest->provider_status, $latest->provider_reference, 'reconcile-'.$latest->provider_event_id);
        $result['debited'] = false;

        return $result;
    }

    public function fee()
    {
        return round((float) config('services.property.service_fee', 0), 2);
    }

    public function category($text)
    {
        $t = strtolower((string) $text);
        foreach ($this->types() as $type) {
            if ($type !== 'other' && strpos($t, $type) !== false) {
                return $type;
            }
        }
        if (strpos($t, 'dstv') !== false || strpos($t, 'canal') !== false) {
            return 'tv';
        }

        return 'other';
    }

    protected function amountFrom($text)
    {
        if (preg_match('/\b(\d{3,9})\b/', (string) $text, $m)) {
            return round((float) $m[1], 2);
        }

        return null;
    }
}
