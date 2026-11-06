<?php

namespace App\Services;

use App\CampayPayout;
use App\Event;
use App\EventAssignment;
use App\EventAutoPayment;
use App\EventWorkerPayment;
use App\Support\TwilioAdminCopy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EventAutoPay
{
    public function openFor(Event $event)
    {
        return EventAutoPayment::where('event_id', $event->id)
            ->whereIn('status', ['pending_approval', 'scheduled', 'paying', 'failed'])
            ->orderByDesc('id')
            ->first();
    }

    public function remember(Event $event, $payAt, $enabled, $isAdmin)
    {
        $current = $this->openFor($event);
        if (! $enabled) {
            if ($current && in_array($current->status, ['pending_approval', 'scheduled', 'failed'], true)) {
                $current->status = 'cancelled';
                $current->save();
            }

            return $current;
        }
        if ($payAt === null || $payAt === '') {
            return $current;
        }
        $when = \Carbon\Carbon::parse($payAt);
        if ($current && $current->status === 'paying') {
            return $current;
        }
        if (! $current || $current->status === 'cancelled') {
            $current = new EventAutoPayment();
            $current->event_id = $event->id;
            $current->attempt = 1;
            $current->requested_by = Auth::id();
        } elseif ($current->status === 'failed') {
            $current->attempt = (int) $current->attempt + 1;
        }
        $current->pay_at = $when;
        if ($isAdmin) {
            $current->status = 'scheduled';
            $current->approved_by = Auth::id();
            $current->approved_at = now();
        } else {
            $current->status = 'pending_approval';
            $current->approved_by = null;
            $current->approved_at = null;
        }
        $current->result = null;
        $current->save();
        if (! $isAdmin) {
            $this->askAdmin($event, $current);
        }

        return $current;
    }

    public function approve(EventAutoPayment $row, $userId)
    {
        if ($row->status !== 'pending_approval') {
            return $row;
        }
        $row->status = 'scheduled';
        $row->approved_by = $userId;
        $row->approved_at = now();
        $row->save();

        return $row;
    }

    public function reject(EventAutoPayment $row)
    {
        if (! in_array($row->status, ['pending_approval', 'scheduled'], true)) {
            return $row;
        }
        $row->status = 'cancelled';
        $row->save();

        return $row;
    }

    public function runDue()
    {
        $due = EventAutoPayment::whereIn('status', ['scheduled', 'paying'])
            ->where('pay_at', '<=', now())
            ->orderBy('id')
            ->limit(8)
            ->get();
        foreach ($due as $row) {
            EventAutoPayment::where('id', $row->id)->where('status', 'scheduled')->update(['status' => 'paying']);
            $this->pay($row->fresh());
        }
    }

    public function pay(EventAutoPayment $row)
    {
        $event = Event::find($row->event_id);
        if (! $event) {
            $row->status = 'failed';
            $row->result = 'The event is gone.';
            $row->save();

            return;
        }
        $service = app(CampayPayoutService::class);
        $notes = [];
        $pending = 0;
        $paid = 0;
        $assignments = EventAssignment::with('workerProfile.customer', 'workerProfile.user')
            ->where('event_id', $event->id)
            ->get();
        foreach ($assignments as $assignment) {
            $outcome = $this->payOne($service, $event, $assignment, $row);
            $notes[] = $outcome['note'];
            if ($outcome['state'] === 'pending') {
                $pending++;
            } elseif ($outcome['state'] === 'paid') {
                $paid++;
            }
        }
        $row->result = implode("\n", array_filter($notes));
        if ($pending > 0) {
            $row->status = 'paying';
        } elseif ($assignments->count() > 0 && $paid === $assignments->count()) {
            $row->status = 'paid';
            $row->paid_at = now();
        } else {
            $row->status = 'failed';
        }
        $row->save();
    }

    protected function payOne(CampayPayoutService $service, Event $event, EventAssignment $assignment, EventAutoPayment $row)
    {
        $profile = $assignment->workerProfile;
        $name = $profile ? $profile->displayName() : 'Engineer';
        if ($assignment->payment_status === 'paid') {
            return ['state' => 'paid', 'note' => $name.' was already paid.'];
        }
        $amount = (int) $assignment->expected_total;
        $reference = 'evpay-'.substr(md5($assignment->id.'|'.$row->id.'|'.$row->attempt), 0, 24);
        $existing = CampayPayout::where('external_reference', $reference)->first();
        if ($existing && $existing->status === 'paid') {
            $this->recordPaid($event, $assignment, $existing);

            return ['state' => 'paid', 'note' => $name.' was paid '.number_format($amount).' XAF.'];
        }
        if ($existing && $existing->status === 'pending' && $existing->campay_reference) {
            $this->refreshPayout($service, $existing);
            $existing = $existing->fresh();
            if ($existing->status === 'paid') {
                $this->recordPaid($event, $assignment, $existing);

                return ['state' => 'paid', 'note' => $name.' was paid '.number_format($amount).' XAF.'];
            }
            if ($existing->status === 'pending') {
                return ['state' => 'pending', 'note' => $name.' is still waiting for Mobile Money.'];
            }
        }
        if ($existing && $existing->status === 'failed') {
            return ['state' => 'failed', 'note' => $name.' was not paid. '.($existing->error ?: '')];
        }
        $raw = '';
        if ($profile) {
            $raw = $profile->telephone ?: optional($profile->customer)->phone_number;
            if (! $raw && $profile->user) {
                $raw = $profile->user->phone;
            }
        }
        $phone = $service->momoNumber($raw);
        if ($amount < 100) {
            return ['state' => 'failed', 'note' => $name.' was skipped because the total is under 100 XAF.'];
        }
        if (! $phone) {
            return ['state' => 'failed', 'note' => $name.' was skipped because there is no MTN or Orange number.'];
        }
        $payout = CampayPayout::create([
            'user_id' => $row->approved_by ?: $row->requested_by,
            'customer_id' => $profile ? $profile->customer_id : null,
            'person_name' => $name,
            'phone' => $phone,
            'amount' => $amount,
            'currency' => 'XAF',
            'note' => substr('Event '.$event->name, 0, 180),
            'external_reference' => $reference,
            'status' => 'pending',
        ]);
        try {
            $service->send([$payout]);
        } catch (\Throwable $e) {
            Log::warning('Event auto pay did not send: '.$e->getMessage());
            $payout->status = 'failed';
            $payout->error = substr($e->getMessage(), 0, 180);
            $payout->save();
        }
        $payout = $payout->fresh();
        if ($payout->status === 'paid') {
            $this->recordPaid($event, $assignment, $payout);

            return ['state' => 'paid', 'note' => $name.' was paid '.number_format($amount).' XAF.'];
        }
        if ($payout->status === 'pending') {
            return ['state' => 'pending', 'note' => $name.' is waiting for Mobile Money.'];
        }

        return ['state' => 'failed', 'note' => $name.' was not paid. '.($payout->error ?: '')];
    }

    protected function refreshPayout(CampayPayoutService $service, CampayPayout $row)
    {
        $body = $service->transaction($row->campay_reference);
        $status = strtoupper((string) (is_array($body) && isset($body['status']) ? $body['status'] : ''));
        if ($status === 'SUCCESSFUL' && $service->payoutDelivered(is_array($body) ? $body : [], (int) $row->amount)) {
            $row->status = 'paid';
            $row->error = null;
            $row->save();
        } elseif (in_array($status, ['FAILED', 'CANCELLED', 'CANCELED', 'REJECTED', 'EXPIRED'], true)) {
            $row->status = 'failed';
            $row->error = $status;
            $row->save();
        }
    }

    protected function recordPaid(Event $event, EventAssignment $assignment, CampayPayout $payout)
    {
        $assignment->payment_status = 'paid';
        $assignment->save();
        $already = EventWorkerPayment::where('assignment_id', $assignment->id)->where('status', EventWorkerPayment::STATUS_PAID)->exists();
        if (! $already) {
            EventWorkerPayment::create([
                'event_id' => $event->id,
                'assignment_id' => $assignment->id,
                'worker_profile_id' => $assignment->worker_profile_id,
                'reference_no' => app(EventPaymentService::class)->nextReferenceNo(),
                'amount' => (int) $payout->amount,
                'payment_method' => 'mobile_money',
                'mobile_money_number' => $payout->phone,
                'status' => EventWorkerPayment::STATUS_PAID,
                'paid_at' => now(),
                'notes' => 'Paid automatically for '.$event->name,
                'created_by' => $payout->user_id,
                'approved_by' => $payout->user_id,
            ]);
        }
        app(MomoMiniReceipt::class)->send([
            'phone' => $payout->phone,
            'name' => $payout->person_name,
            'amount' => $payout->amount,
            'reason' => 'Event '.$event->name,
            'reference' => $payout->external_reference,
            'date' => now(),
            'method' => 'Mobile Money',
        ]);
    }

    protected function askAdmin(Event $event, EventAutoPayment $row)
    {
        $who = Auth::user() ? Auth::user()->name : 'Someone';
        $when = $row->pay_at ? $row->pay_at->format('d M Y H:i') : '';
        $url = route('events.show', $event->id);
        $text = $who.' asked to pay the engineers for '.$event->name.' at '.$when.'. Approve it before that time: '.$url;
        try {
            app(ClientNoticeService::class)->send(TwilioAdminCopy::PHONE, $text);
        } catch (\Throwable $e) {
            Log::warning('Event auto-pay approval notice failed: '.$e->getMessage());
        }
    }
}
