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
            ->whereNull('assignment_id')
            ->whereIn('status', ['pending_approval', 'scheduled', 'paying', 'failed'])
            ->orderByDesc('id')
            ->first();
    }

    public function openRows(Event $event)
    {
        return EventAutoPayment::with('assignment.workerProfile')
            ->where('event_id', $event->id)
            ->whereIn('status', ['pending_approval', 'scheduled', 'paying', 'failed', 'paid'])
            ->orderBy('pay_at')
            ->orderBy('id')
            ->get();
    }

    public function roomFor(EventAssignment $assignment)
    {
        $paid = (int) EventWorkerPayment::where('assignment_id', $assignment->id)
            ->where('status', EventWorkerPayment::STATUS_PAID)
            ->sum('amount');
        $waiting = (int) EventAutoPayment::where('assignment_id', $assignment->id)
            ->whereIn('status', ['pending_approval', 'scheduled', 'paying'])
            ->sum('amount');

        return max(0, (int) $assignment->expected_total - $paid - $waiting);
    }

    public function dueOnDay(EventAssignment $assignment, $date, $exceptAutoId = null)
    {
        $date = substr(trim((string) $date), 0, 10);
        $match = null;
        foreach (\App\Event::parseWorkDays($assignment->work_dates) as $day) {
            if ($day['date'] === $date) {
                $match = $day;
                break;
            }
        }
        if (! $match) {
            return 0;
        }
        $paid = EventWorkerPayment::where('assignment_id', $assignment->id)
            ->where('status', EventWorkerPayment::STATUS_PAID)
            ->where('notes', 'like', $date.' | %')
            ->exists();
        if ($paid) {
            return 0;
        }
        $waitingQuery = EventAutoPayment::where('assignment_id', $assignment->id)
            ->whereIn('status', ['pending_approval', 'scheduled', 'paying'])
            ->where('result', 'like', 'work_date:'.$date.'%');
        if ($exceptAutoId) {
            $waitingQuery->where('id', '!=', $exceptAutoId);
        }
        $waiting = $waitingQuery->exists();
        if ($waiting) {
            return 0;
        }
        $amount = $match['pay_amount'] !== null
            ? (int) $match['pay_amount']
            : ((int) $assignment->event_daily_rate + (int) $assignment->food_per_day);

        return $amount >= 100 ? $amount : 0;
    }

    public function unpaidDays(EventAssignment $assignment)
    {
        $rows = array();
        foreach (Event::parseWorkDays($assignment->work_dates) as $day) {
            $amount = $this->dueOnDay($assignment, $day['date']);
            if ($amount >= 100) {
                $rows[] = array('date' => $day['date'], 'amount' => $amount);
            }
        }
        usort($rows, function ($a, $b) {
            return strcmp($a['date'], $b['date']);
        });

        return $rows;
    }

    public function schedule(Event $event, $assignmentId, $amount, $payAt, $isAdmin, $workDate = null)
    {
        $assignment = EventAssignment::where('event_id', $event->id)->where('id', $assignmentId)->first();
        if (! $assignment || $assignment->acceptance_status === 'rejected') {
            return array('ok' => false, 'message' => 'That engineer is not on this event.');
        }
        $amount = (int) $amount;
        $workDate = $workDate ? substr(trim((string) $workDate), 0, 10) : null;
        if ($workDate) {
            $due = $this->dueOnDay($assignment, $workDate);
            if ($due < 100) {
                $name = $assignment->workerProfile ? $assignment->workerProfile->displayName() : 'This engineer';

                return array('ok' => false, 'message' => $name.' has no unpaid amount for that day.');
            }
            $amount = $due;
        } else {
            $room = $this->roomFor($assignment);
            if ($amount > $room) {
                $name = $assignment->workerProfile ? $assignment->workerProfile->displayName() : 'This engineer';

                return array('ok' => false, 'message' => $name.' has '.number_format($room).' XAF left to schedule.');
            }
        }
        if ($amount < 100) {
            return array('ok' => false, 'message' => 'A scheduled payment has to be at least 100 XAF.');
        }
        if ($payAt === null || $payAt === '') {
            return array('ok' => false, 'message' => 'Choose the time this part should be paid.');
        }
        $row = new EventAutoPayment();
        $row->event_id = $event->id;
        $row->assignment_id = $assignment->id;
        $row->amount = $amount;
        $row->pay_at = \Carbon\Carbon::parse($payAt);
        $row->attempt = 1;
        $row->requested_by = Auth::id();
        if ($workDate) {
            $row->result = 'work_date:'.$workDate;
        }
        if ($isAdmin) {
            $row->status = 'scheduled';
            $row->approved_by = Auth::id();
            $row->approved_at = now();
        } else {
            $row->status = 'pending_approval';
        }
        $row->save();
        if (! $isAdmin) {
            $this->askAdmin($event, $row);
        }

        return array('ok' => true, 'row' => $row);
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
        if (! in_array($row->status, ['pending_approval', 'scheduled', 'failed'], true)) {
            return $row;
        }
        $row->status = 'cancelled';
        $row->result = 'Removed from the payment schedule.';
        $row->save();

        return $row;
    }

    public function updateRow(EventAutoPayment $row, $amount, $payAt)
    {
        if (! in_array($row->status, ['pending_approval', 'scheduled', 'failed'], true)) {
            return ['ok' => false, 'message' => 'That schedule can no longer be changed.'];
        }
        $amount = (int) $amount;
        if ($amount < 100) {
            return ['ok' => false, 'message' => 'A scheduled payment has to be at least 100 XAF.'];
        }
        if ($payAt === null || $payAt === '') {
            return ['ok' => false, 'message' => 'Choose the date and time.'];
        }
        if ($row->assignment_id) {
            $assignment = EventAssignment::find($row->assignment_id);
            if (! $assignment) {
                return ['ok' => false, 'message' => 'That person is no longer on the event.'];
            }
            $paid = (int) EventWorkerPayment::where('assignment_id', $assignment->id)
                ->where('status', EventWorkerPayment::STATUS_PAID)
                ->sum('amount');
            $others = (int) EventAutoPayment::where('assignment_id', $assignment->id)
                ->where('id', '!=', $row->id)
                ->whereIn('status', ['pending_approval', 'scheduled', 'paying'])
                ->sum('amount');
            $dayPay = is_string($row->result) && strpos($row->result, 'work_date:') === 0;
            $room = max(0, (int) $assignment->expected_total - $paid - $others);
            if (! $dayPay && $amount > $room) {
                $name = $assignment->workerProfile ? $assignment->workerProfile->displayName() : 'This person';

                return ['ok' => false, 'message' => $name.' has '.number_format($room).' XAF left to schedule.'];
            }
        }
        $row->amount = $amount;
        $row->pay_at = \Carbon\Carbon::parse($payAt);
        if ($row->status === 'failed') {
            $row->status = 'scheduled';
            if (! (is_string($row->result) && strpos($row->result, 'work_date:') === 0)) {
                $row->result = null;
            }
        }
        $row->save();

        return ['ok' => true, 'row' => $row];
    }

    public function applyPercent(Event $event, $percent, $payAt, $isAdmin, array $onlyIds = null)
    {
        $percent = (int) $percent;
        if ($percent < 1 || $percent > 100) {
            return ['ok' => false, 'message' => 'Choose a percentage from 1 to 100.'];
        }
        if ($payAt === null || $payAt === '') {
            return ['ok' => false, 'message' => 'Choose the date and time for this percentage.'];
        }
        $event->load('assignments.workerProfile');
        $set = 0;
        $rows = [];
        $warnings = [];
        $wanted = $onlyIds === null ? null : array_map('strval', $onlyIds);
        foreach ($event->assignments as $assignment) {
            if ($assignment->acceptance_status === 'rejected') {
                continue;
            }
            if ($wanted !== null && ! in_array((string) $assignment->id, $wanted, true)) {
                continue;
            }
            EventAutoPayment::where('assignment_id', $assignment->id)
                ->whereIn('status', ['pending_approval', 'scheduled', 'failed'])
                ->update(['status' => 'cancelled', 'result' => 'Replaced by a percentage for everyone.']);
            $paid = (int) EventWorkerPayment::where('assignment_id', $assignment->id)
                ->where('status', EventWorkerPayment::STATUS_PAID)
                ->sum('amount');
            $paying = (int) EventAutoPayment::where('assignment_id', $assignment->id)
                ->where('status', 'paying')
                ->sum('amount');
            $due = max(0, (int) $assignment->expected_total - $paid - $paying);
            $amount = (int) round($due * $percent / 100);
            $name = $assignment->workerProfile ? $assignment->workerProfile->displayName() : 'A person';
            if ($amount < 100) {
                $warnings[] = $name.' was skipped because '.$percent.'% is under 100 XAF.';
                continue;
            }
            $scheduled = $this->schedule($event, $assignment->id, $amount, $payAt, $isAdmin);
            if (empty($scheduled['ok'])) {
                $warnings[] = $scheduled['message'];
            } else {
                $set++;
                $rows[] = $scheduled['row'];
            }
        }
        if ($set < 1) {
            return ['ok' => false, 'message' => $warnings ? implode(' ', $warnings) : 'Nobody had an amount left to schedule.'];
        }

        return ['ok' => true, 'set' => $set, 'rows' => $rows, 'warnings' => $warnings];
    }

    public function scheduleDay(Event $event, $workDate, $payAt, $isAdmin, array $onlyIds = null)
    {
        $workDate = substr(trim((string) $workDate), 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
            return ['ok' => false, 'message' => 'Choose the working day to pay.'];
        }
        if ($payAt === null || $payAt === '') {
            return ['ok' => false, 'message' => 'Choose when the money should be sent.'];
        }
        $event->load('assignments.workerProfile');
        $wanted = $onlyIds === null ? null : array_map('strval', $onlyIds);
        $set = 0;
        $rows = [];
        $warnings = [];
        foreach ($event->assignments as $assignment) {
            if ($assignment->acceptance_status === 'rejected') {
                continue;
            }
            if ($wanted !== null && ! in_array((string) $assignment->id, $wanted, true)) {
                continue;
            }
            $name = $assignment->workerProfile ? $assignment->workerProfile->displayName() : 'A person';
            $match = null;
            foreach (\App\Event::parseWorkDays($assignment->work_dates) as $day) {
                if ($day['date'] === $workDate) {
                    $match = $day;
                    break;
                }
            }
            if (! $match) {
                $warnings[] = $name.' does not work on that day.';
                continue;
            }
            $already = EventWorkerPayment::where('assignment_id', $assignment->id)
                ->where('status', EventWorkerPayment::STATUS_PAID)
                ->where('notes', 'like', $workDate.' | %')
                ->exists();
            if ($already) {
                $warnings[] = $name.' is already paid for that day.';
                continue;
            }
            $amount = $match['pay_amount'] !== null
                ? (int) $match['pay_amount']
                : ((int) $assignment->event_daily_rate + (int) $assignment->food_per_day);
            if ($amount < 100) {
                $warnings[] = $name.' was skipped because that day is under 100 XAF.';
                continue;
            }
            $scheduled = $this->schedule($event, $assignment->id, $amount, $payAt, $isAdmin, $workDate);
            if (empty($scheduled['ok'])) {
                $warnings[] = $scheduled['message'];
            } else {
                $set++;
                $rows[] = $scheduled['row'];
            }
        }
        if ($set < 1) {
            return ['ok' => false, 'message' => $warnings ? implode(' ', $warnings) : 'Nobody is due for that day.'];
        }

        return ['ok' => true, 'set' => $set, 'rows' => $rows, 'warnings' => $warnings];
    }

    public function schedulePicked(Event $event, array $picks, $payAt, $isAdmin)
    {
        if ($payAt === null || $payAt === '') {
            return ['ok' => false, 'message' => 'Choose when the money should be sent.'];
        }
        $event->load('assignments.workerProfile');
        $byId = array();
        foreach ($event->assignments as $assignment) {
            $byId[(string) $assignment->id] = $assignment;
        }
        $set = 0;
        $rows = array();
        $warnings = array();
        $seen = array();
        foreach ($picks as $pick) {
            $parts = explode('|', (string) $pick, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $assignmentId = $parts[0];
            $workDate = substr(trim($parts[1]), 0, 10);
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate) || ! isset($byId[$assignmentId])) {
                continue;
            }
            $key = $assignmentId.'|'.$workDate;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $assignment = $byId[$assignmentId];
            if ($assignment->acceptance_status === 'rejected') {
                continue;
            }
            $name = $assignment->workerProfile ? $assignment->workerProfile->displayName() : 'A person';
            $amount = $this->dueOnDay($assignment, $workDate);
            if ($amount < 100) {
                $warnings[] = $name.' has nothing left for '.\Carbon\Carbon::parse($workDate)->format('d M Y').'.';
                continue;
            }
            $scheduled = $this->schedule($event, $assignment->id, $amount, $payAt, $isAdmin, $workDate);
            if (empty($scheduled['ok'])) {
                $warnings[] = $scheduled['message'];
            } else {
                $set++;
                $rows[] = $scheduled['row'];
            }
        }
        if ($set < 1) {
            return ['ok' => false, 'message' => $warnings ? implode(' ', $warnings) : 'Select the people and the days to pay.'];
        }

        return ['ok' => true, 'set' => $set, 'rows' => $rows, 'warnings' => $warnings];
    }

    public function scheduleAllDays(Event $event, $payAt, $isAdmin, array $onlyIds = null)
    {
        $event->load('assignments.workerProfile');
        $wanted = $onlyIds === null ? null : array_map('strval', $onlyIds);
        $picks = array();
        foreach ($event->assignments as $assignment) {
            if ($assignment->acceptance_status === 'rejected') {
                continue;
            }
            if ($wanted !== null && ! in_array((string) $assignment->id, $wanted, true)) {
                continue;
            }
            foreach ($this->unpaidDays($assignment) as $day) {
                $picks[] = $assignment->id.'|'.$day['date'];
            }
        }

        return $this->schedulePicked($event, $picks, $payAt, $isAdmin);
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
            ->get()
            ->filter(function ($assignment) use ($row) {
                if ($assignment->acceptance_status === 'rejected') {
                    return false;
                }
                if ($row->assignment_id) {
                    return (string) $assignment->id === (string) $row->assignment_id;
                }

                return true;
            })
            ->values();
        if ($row->assignment_id && $assignments->count() === 0) {
            $row->status = 'cancelled';
            $row->result = 'The engineer rejected the job.';
            $row->save();

            return;
        }
        foreach ($assignments as $assignment) {
            $outcome = $this->payOne($service, $event, $assignment, $row);
            $notes[] = $outcome['note'];
            if ($outcome['state'] === 'pending') {
                $pending++;
            } elseif ($outcome['state'] === 'paid') {
                $paid++;
            }
        }
        $prefix = (is_string($row->result) && strpos($row->result, 'work_date:') === 0) ? substr($row->result, 0, 20)."\n" : '';
        $row->result = $prefix.implode("\n", array_filter($notes));
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
        $workDate = (is_string($row->result) && strpos($row->result, 'work_date:') === 0)
            ? substr($row->result, 10, 10)
            : '';
        if ($workDate !== '') {
            $amount = $this->dueOnDay($assignment, $workDate, $row->id);
            if ($amount < 100) {
                return ['state' => 'paid', 'note' => $name.' is already paid for '.$workDate.'.'];
            }
        } else {
            $alreadyPaid = (int) EventWorkerPayment::where('assignment_id', $assignment->id)
                ->where('status', EventWorkerPayment::STATUS_PAID)
                ->sum('amount');
            $remaining = max(0, (int) $assignment->expected_total - $alreadyPaid);
            if ($remaining < 100) {
                return ['state' => 'paid', 'note' => $name.' was already paid.'];
            }
            $amount = $row->amount ? min((int) $row->amount, $remaining) : $remaining;
        }
        $reference = 'evpay-'.substr(md5($assignment->id.'|'.($workDate !== '' ? $workDate : $row->id.'|'.$row->attempt)), 0, 24);
        $existing = CampayPayout::where('external_reference', $reference)->first();
        if ($existing && $existing->status === 'paid') {
            $this->recordPaid($event, $assignment, $existing, $workDate);

            return ['state' => 'paid', 'note' => $name.' was paid '.number_format($amount).' XAF.'];
        }
        if ($existing && $existing->status === 'pending') {
            if ($existing->campay_reference) {
                $this->refreshPayout($service, $existing);
                $existing = $existing->fresh();
            }
            if ($existing->status === 'paid') {
                $this->recordPaid($event, $assignment, $existing, $workDate);

                return ['state' => 'paid', 'note' => $name.' was paid '.number_format($amount).' XAF.'];
            }
            if ($existing->status === 'pending') {
                return ['state' => 'pending', 'note' => $name.' is still waiting for Mobile Money.'];
            }
        }
        if ($existing && $existing->status === 'failed' && $existing->campay_reference) {
            return ['state' => 'failed', 'note' => $name.' was not paid. '.($existing->error ?: '')];
        }
        if ($existing && $existing->status === 'failed') {
            $existing->delete();
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
            $this->recordPaid($event, $assignment, $payout, $workDate);

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

    protected function recordPaid(Event $event, EventAssignment $assignment, CampayPayout $payout, $workDate = null)
    {
        $marker = ($workDate ? $workDate.' | ' : '').'Auto '.$payout->external_reference;
        $already = EventWorkerPayment::where('assignment_id', $assignment->id)
            ->where('notes', $marker)
            ->exists();
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
                'notes' => $marker,
                'created_by' => $payout->user_id,
                'approved_by' => $payout->user_id,
            ]);
            app(MomoMiniReceipt::class)->send([
                'phone' => $payout->phone,
                'name' => $payout->person_name,
                'amount' => $payout->amount,
                'reason' => 'Event '.$event->name,
                'reference' => $payout->external_reference,
                'date' => now(),
                'method' => 'Mobile Money',
                'title' => 'Payment Confirmation',
                'skip_pdf' => true,
                'skip_admin_copy' => true,
            ]);
        }
        $paidSum = (int) EventWorkerPayment::where('assignment_id', $assignment->id)
            ->where('status', EventWorkerPayment::STATUS_PAID)
            ->sum('amount');
        $assignment->payment_status = $paidSum >= (int) $assignment->expected_total ? 'paid' : 'partial';
        $assignment->save();
    }

    protected function askAdmin(Event $event, EventAutoPayment $row)
    {
        $who = Auth::user() ? Auth::user()->name : 'Someone';
        $when = $row->pay_at ? $row->pay_at->format('d M Y H:i') : '';
        $url = route('events.show', $event->id);
        $part = $row->amount ? number_format((int) $row->amount).' XAF' : 'the engineer totals';
        $text = $who.' asked to pay '.$part.' for '.$event->name.' at '.$when.'. Approve it before that time: '.$url;
        try {
            app(ClientNoticeService::class)->send(TwilioAdminCopy::PHONE, $text);
        } catch (\Throwable $e) {
            Log::warning('Event auto-pay approval notice failed: '.$e->getMessage());
        }
    }
}
