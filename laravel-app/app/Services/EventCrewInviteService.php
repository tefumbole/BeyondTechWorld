<?php

namespace App\Services;

use App\CampayPayout;
use App\Event;
use App\EventAssignment;
use App\EventAutoPayment;
use App\EventTimesheet;
use App\EventTimesheetEntry;
use App\EventWorkerPayment;
use App\Services\EventPaymentService;
use App\Services\Messaging\NotificationRouter;
use App\Support\MemorableOtp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventCrewInviteService
{
    public function inviteAssigned(Event $event, $attachContract = true, $templateId = null)
    {
        $event = $event->fresh() ?: $event;
        $event->load('assignments.workerProfile.customer', 'assignments.workerProfile.user');
        $sent = 0;
        $warnings = [];
        $attempted = false;
        foreach ($event->assignments as $assignment) {
            if ($assignment->invited_at) {
                continue;
            }
            if ($this->isClient($event, $assignment)) {
                continue;
            }
            $phone = $this->phoneFor($assignment);
            if (! $phone) {
                $warnings[] = $assignment->workerProfile->displayName().' has no phone, so no selection message was sent.';
                continue;
            }
            if (! $assignment->invite_token) {
                $assignment->invite_token = Str::random(40);
            }
            $assignment->acceptance_status = $assignment->acceptance_status ?: 'pending';
            $assignment->invited_at = now();
            $assignment->save();

            $name = $assignment->workerProfile ? $assignment->workerProfile->displayName() : 'there';
            $start = $event->event_start_at ? $event->event_start_at->format('d M Y') : '';
            $end = $event->event_end_at ? $event->event_end_at->format('d M Y') : $start;
            $pay = number_format($this->dailyRate($assignment));
            $food = number_format($this->foodAmount($assignment));
            $url = url('/event-invite/'.$assignment->invite_token);
            $notes = trim((string) $event->internal_notes);
            $workLine = $this->workDatesLine($assignment);
            $setupLine = $this->setupText($event);
            $unpackLine = $this->datedList($event->unpack_dates);
            $body = "Hello {$name},\n\n"
                ."You have been selected for {$event->name}.\n"
                ."Dates: {$start} to {$end}\n"
                .($workLine !== '' ? "Your working days: {$workLine}\n" : '')
                .'Role: '.($assignment->assignment_role ?: 'Crew')."\n"
                ."Daily pay: {$pay} XAF\n"
                ."Food money: {$food} XAF\n";
            if ($setupLine !== '') {
                $body .= "\nSetup dates:\n{$setupLine}";
            }
            if ($unpackLine !== '') {
                $body .= "\nUnpacking dates:\n{$unpackLine}";
            }
            if ($notes !== '') {
                $body .= "\nNotes:\n{$notes}\n";
            }
            if ($attachContract) {
                $body .= "\nYour contract is attached. Open it, then use this link to accept the days you can work and sign:\n{$url}\n\n"
                    ."If you cannot take this job, open the same link and choose Reject.";
            } else {
                $body .= "\nOpen this link to accept the days you can work:\n{$url}\n\n"
                    ."If you cannot take this job, open the same link and choose Reject.";
            }
            $contractFile = null;
            $contractName = 'engineer-contract.pdf';
            try {
                $contracts = app(EventContractService::class);
                $contract = $attachContract ? $contracts->offerForAssignment($event, $assignment, $templateId) : null;
                if ($contract && $contract->status === \App\EventContract::STATUS_DRAFT) {
                    $contracts->markSent($contract);
                    $contract = $contract->fresh();
                }
                if ($contract && $contract->status !== \App\EventContract::STATUS_APPROVED) {
                    $relative = $contracts->generatePdf($contract);
                    $contract->signed_pdf_path = $relative;
                    $contract->save();
                    $contractFile = public_path($relative);
                    $contractName = $contract->reference_no.'.pdf';
                } elseif ($contract && $contract->signed_pdf_path) {
                    $contractFile = public_path($contract->signed_pdf_path);
                    $contractName = $contract->reference_no.'.pdf';
                }
            } catch (\Exception $e) {
                $contractFile = null;
            }
            if ($attempted) {
                usleep(400000);
            }
            $attempted = true;
            try {
                if ($contractFile && is_file($contractFile)) {
                    $result = app(NotificationRouter::class)->sendWhatsAppDocument($phone, $contractFile, $contractName, $body);
                } else {
                    $result = app(NotificationRouter::class)->sendWhatsAppText($phone, $body);
                }
            } catch (\Exception $e) {
                $result = ['success' => false];
            }
            if (empty($result['success']) || ! empty($result['skipped'])) {
                $warnings[] = 'The selection message to '.$name.' was not sent.';
                $assignment->invited_at = null;
                $assignment->save();
            } else {
                $sent++;
            }
        }

        return ['sent' => $sent, 'warnings' => $warnings];
    }

    public function isClient(Event $event, EventAssignment $assignment)
    {
        $profile = $assignment->workerProfile;
        if (! $profile) {
            return false;
        }
        if ($event->customer_id && (int) $profile->customer_id === (int) $event->customer_id) {
            return true;
        }
        $client = substr(preg_replace('/\D+/', '', (string) $event->client_telephone), -9);
        $phone = substr(preg_replace('/\D+/', '', $this->phoneFor($assignment)), -9);

        return $client !== '' && $phone !== '' && $client === $phone;
    }

    public function phoneFor(EventAssignment $assignment)
    {
        $profile = $assignment->workerProfile;
        if (! $profile) {
            return '';
        }
        $raw = $profile->telephone ?: optional($profile->customer)->phone_number ?: optional($profile->user)->phone;

        return app(CampayPayoutService::class)->momoNumber($raw) ?: preg_replace('/\D+/', '', (string) $raw);
    }

    public function dailyRate(EventAssignment $assignment)
    {
        $rate = (int) $assignment->event_daily_rate;
        if ($rate <= 0 && (int) $assignment->expected_days > 0) {
            $perDay = (int) round(((int) $assignment->expected_total) / (int) $assignment->expected_days);
            $rate = max(0, $perDay - (int) $assignment->food_per_day);
        }

        return $rate;
    }

    public function foodAmount(EventAssignment $assignment)
    {
        return (int) $assignment->food_per_day;
    }

    public function workDates(EventAssignment $assignment)
    {
        $clean = array();
        foreach (Event::parseWorkDays($assignment->work_dates) as $day) {
            $clean[] = $day['date'];
        }

        return $clean;
    }

    public function workDayEntries(EventAssignment $assignment)
    {
        return Event::parseWorkDays($assignment->work_dates);
    }

    public function offeredDayEntries(EventAssignment $assignment)
    {
        $byDate = array();
        foreach ($this->workDayEntries($assignment) as $day) {
            $byDate[$day['date']] = $day;
        }
        $event = $assignment->event;
        if ($event && $event->event_start_at) {
            $cursor = strtotime($event->event_start_at->toDateString());
            $last = strtotime($event->event_end_at ? $event->event_end_at->toDateString() : $event->event_start_at->toDateString());
            $guard = 0;
            while ($cursor && $last && $cursor <= $last && $guard < 62) {
                $date = date('Y-m-d', $cursor);
                if (! isset($byDate[$date])) {
                    $byDate[$date] = array(
                        'date' => $date,
                        'note' => '',
                        'description' => '',
                        'pay_amount' => null,
                        'pay_at' => '',
                    );
                }
                $cursor = strtotime('+1 day', $cursor);
                $guard++;
            }
        }
        ksort($byDate);

        return array_values($byDate);
    }

    protected function workDatesLine(EventAssignment $assignment)
    {
        $labels = array();
        foreach ($this->workDayEntries($assignment) as $day) {
            $label = date('d M', strtotime($day['date']));
            $extra = trim($day['note'].($day['description'] !== '' ? ' — '.$day['description'] : ''));
            if ($extra !== '') {
                $label .= ' ('.$extra.')';
            }
            $labels[] = $label;
        }

        return implode(', ', $labels);
    }

    public function dayAmount(EventAssignment $assignment)
    {
        return $this->dailyRate($assignment) + $this->foodAmount($assignment);
    }

    public function sendLoginCode(EventAssignment $assignment)
    {
        $phone = $this->phoneFor($assignment);
        if (! $phone) {
            return ['ok' => false, 'message' => 'This place has no phone number for a code.'];
        }
        $code = MemorableOtp::code();
        $result = app(NotificationRouter::class)->sendWhatsAppOtp($phone, $code, 'event-accept', 10);
        if (empty($result['success'])) {
            return ['ok' => false, 'message' => 'The code could not be sent to your WhatsApp.'];
        }

        return ['ok' => true, 'code' => $code, 'phone' => $phone];
    }

    public function setupText(Event $event)
    {
        return $this->datedList($event->setup_dates);
    }

    public function datedList($json)
    {
        $lines = array();
        foreach (Event::parseSetup($json) as $entry) {
            $line = date('d M Y', strtotime($entry['date']));
            if ($entry['note'] !== '') {
                $line .= ' — '.$entry['note'];
            }
            $lines[] = $line;
        }

        return $lines ? implode("\n", $lines)."\n" : '';
    }

    public function notifySetup(Event $event, array $assignmentIds)
    {
        $setup = $this->setupText($event);
        if ($setup === '' || ! count($assignmentIds)) {
            return array('sent' => 0, 'warnings' => array());
        }
        $event->load('assignments.workerProfile.customer', 'assignments.workerProfile.user');
        $sent = 0;
        $warnings = array();
        $attempted = false;
        foreach ($event->assignments as $assignment) {
            if (! in_array((string) $assignment->id, array_map('strval', $assignmentIds), true)) {
                continue;
            }
            if ($assignment->acceptance_status === 'rejected' || $this->isClient($event, $assignment)) {
                continue;
            }
            $phone = $this->phoneFor($assignment);
            $name = $assignment->workerProfile ? $assignment->workerProfile->displayName() : 'there';
            if (! $phone) {
                $warnings[] = $name.' has no phone, so the setup dates were not sent.';
                continue;
            }
            $body = "Hello {$name},\n\n"
                ."Setup dates for {$event->name}:\n"
                .$setup
                ."\nOpen your place:\n".url('/event-invite/'.$assignment->invite_token);
            if ($attempted) {
                usleep(400000);
            }
            $attempted = true;
            try {
                $result = app(NotificationRouter::class)->sendWhatsAppText($phone, $body);
            } catch (\Exception $e) {
                $result = array('success' => false);
            }
            if (empty($result['success']) || ! empty($result['skipped'])) {
                $warnings[] = 'The setup dates were not sent to '.$name.'.';
            } else {
                $sent++;
            }
        }

        return array('sent' => $sent, 'warnings' => $warnings);
    }

    public function notifyUpdate(Event $event, array $assignmentIds)
    {
        if (! count($assignmentIds)) {
            return ['sent' => 0, 'warnings' => []];
        }
        $event = $event->fresh() ?: $event;
        $event->load('assignments.workerProfile.customer', 'assignments.workerProfile.user');
        $wanted = array_map('strval', $assignmentIds);
        $sent = 0;
        $warnings = [];
        $attempted = false;
        $start = $event->event_start_at ? $event->event_start_at->format('d M Y H:i') : '';
        $end = $event->event_end_at ? $event->event_end_at->format('d M Y H:i') : $start;
        $setupLine = $this->setupText($event);
        $unpackLine = $this->datedList($event->unpack_dates);
        $notes = trim((string) $event->internal_notes);
        foreach ($event->assignments as $assignment) {
            if (! in_array((string) $assignment->id, $wanted, true)) {
                continue;
            }
            if ($assignment->acceptance_status === 'rejected' || $this->isClient($event, $assignment)) {
                continue;
            }
            $phone = $this->phoneFor($assignment);
            $name = $assignment->workerProfile ? $assignment->workerProfile->displayName() : 'there';
            if (! $phone) {
                $warnings[] = $name.' has no phone, so the update was not sent.';
                continue;
            }
            $pay = number_format($this->dailyRate($assignment));
            $food = number_format($this->foodAmount($assignment));
            $workLine = $this->workDatesLine($assignment);
            $body = "Hello {$name},\n\n"
                ."{$event->name} was updated.\n"
                ."Dates: {$start} to {$end}\n"
                .($workLine !== '' ? "Your working days: {$workLine}\n" : '')
                .'Role: '.($assignment->assignment_role ?: 'Crew')."\n"
                ."Daily pay: {$pay} XAF\n"
                ."Food money: {$food} XAF\n";
            if ($event->venue) {
                $body .= 'Venue: '.$event->venue."\n";
            }
            if ($setupLine !== '') {
                $body .= "\nSetup dates:\n{$setupLine}";
            }
            if ($unpackLine !== '') {
                $body .= "\nUnpacking dates:\n{$unpackLine}";
            }
            if ($notes !== '') {
                $body .= "\nNotes:\n{$notes}\n";
            }
            if ($assignment->acceptance_status === 'accepted') {
                $body .= "\nYou already accepted this job. This message is an update only.";
            } else {
                if (! $assignment->invite_token) {
                    $assignment->invite_token = Str::random(40);
                    $assignment->save();
                }
                $url = url('/event-invite/'.$assignment->invite_token);
                $body .= "\nAccept your place:\n{$url}\n\n"
                    .'If you cannot take this job, open the same link and choose Reject.';
            }
            if ($attempted) {
                usleep(400000);
            }
            $attempted = true;
            try {
                $result = app(NotificationRouter::class)->sendWhatsAppText($phone, $body);
            } catch (\Exception $e) {
                $result = ['success' => false];
            }
            if (empty($result['success']) || ! empty($result['skipped'])) {
                $warnings[] = 'The update was not sent to '.$name.'.';
            } else {
                $sent++;
            }
        }

        return ['sent' => $sent, 'warnings' => $warnings];
    }

    public function rejectJob(EventAssignment $assignment)
    {
        if ($assignment->acceptance_status === 'rejected') {
            return;
        }
        $assignment->load('event', 'workerProfile.customer', 'workerProfile.user');
        $assignment->acceptance_status = 'rejected';
        if ($assignment->payment_status !== 'paid') {
            $assignment->payment_status = 'cancelled';
        }
        $assignment->save();

        EventWorkerPayment::where('assignment_id', $assignment->id)
            ->where('status', '!=', EventWorkerPayment::STATUS_PAID)
            ->update(['status' => EventWorkerPayment::STATUS_CANCELLED]);

        EventAutoPayment::where('assignment_id', $assignment->id)
            ->whereIn('status', ['pending_approval', 'scheduled', 'failed'])
            ->update([
                'status' => 'cancelled',
                'result' => 'The engineer rejected the job.',
            ]);

        $phone = $this->phoneFor($assignment);
        $eventName = $assignment->event ? $assignment->event->name : '';
        if ($phone && $eventName !== '') {
            CampayPayout::where('phone', $phone)
                ->where('status', 'pending')
                ->where('note', 'like', '%'.$eventName.'%')
                ->update([
                    'status' => 'failed',
                    'error' => 'Engineer rejected the job.',
                ]);
        }

        if ($assignment->event) {
            app(EventWorkforceService::class)->refreshLabourBudgetAllocation($assignment->event);
        }
    }

    public function keepDays(EventAssignment $assignment, array $chosenDates)
    {
        $entries = $this->offeredDayEntries($assignment);
        if (! count($entries)) {
            return $assignment;
        }
        $offered = array();
        foreach ($entries as $day) {
            $offered[$day['date']] = $day;
        }
        $kept = array();
        foreach ($chosenDates as $date) {
            $date = substr(trim((string) $date), 0, 10);
            if (isset($offered[$date])) {
                $kept[$date] = $offered[$date];
            }
        }
        $kept = array_values($kept);
        $stored = array();
        foreach ($this->workDayEntries($assignment) as $day) {
            $stored[$day['date']] = true;
        }
        $same = count($kept) === count($stored);
        foreach ($kept as $day) {
            if (! isset($stored[$day['date']])) {
                $same = false;
            }
        }
        if (! count($kept) || $same) {
            return $assignment;
        }
        usort($kept, function ($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        $daily = $this->dailyRate($assignment) + $this->foodAmount($assignment);
        $total = 0;
        $keptDates = array();
        foreach ($kept as $day) {
            $total += $day['pay_amount'] !== null ? (int) $day['pay_amount'] : $daily;
            $keptDates[] = $day['date'];
        }
        $assignment->work_dates = json_encode($kept);
        $assignment->expected_days = count($kept);
        $assignment->expected_total = $total;
        $assignment->work_start_date = $keptDates[0];
        $assignment->work_end_date = $keptDates[count($keptDates) - 1];
        $assignment->save();

        $dropped = array();
        foreach ($offered as $date => $day) {
            if (! in_array($date, $keptDates, true)) {
                $dropped[] = $date;
            }
        }
        if (count($dropped)) {
            EventAutoPayment::where('assignment_id', $assignment->id)
                ->whereIn('status', ['pending_approval', 'scheduled', 'failed'])
                ->get()
                ->each(function ($payment) use ($dropped) {
                    $when = $payment->pay_at ? $payment->pay_at->toDateString() : '';
                    if ($when !== '' && in_array($when, $dropped, true)) {
                        $payment->status = 'cancelled';
                        $payment->result = 'The engineer did not accept this day.';
                        $payment->save();
                    }
                });
            $pending = EventWorkerPayment::where('assignment_id', $assignment->id)
                ->where('status', '!=', EventWorkerPayment::STATUS_PAID)
                ->get();
            foreach ($pending as $payment) {
                $note = (string) $payment->notes;
                foreach ($dropped as $date) {
                    if (strpos($note, $date.' | ') === 0) {
                        $payment->status = EventWorkerPayment::STATUS_CANCELLED;
                        $payment->save();
                        break;
                    }
                }
            }
        }
        if ($assignment->event) {
            app(EventWorkforceService::class)->refreshLabourBudgetAllocation($assignment->event);
        }

        return $assignment->fresh();
    }

    public function markAccepted(EventAssignment $assignment)
    {
        $assignment->acceptance_status = 'accepted';
        $assignment->accepted_at = now();
        $assignment->save();
        $this->notifyAccepted($assignment);
    }

    protected function acceptedWhen(Event $event)
    {
        $start = $event->event_start_at;
        $end = $event->event_end_at;
        if (! $start) {
            return '';
        }
        if (! $end || $start->equalTo($end)) {
            return $start->format('d M Y, H:i');
        }
        if ($start->toDateString() === $end->toDateString()) {
            return $start->format('d M Y').', '.$start->format('H:i').'–'.$end->format('H:i');
        }

        return $start->format('d M Y H:i').' – '.$end->format('d M Y H:i');
    }

    public function notifyAccepted(EventAssignment $assignment)
    {
        $assignment->load('workerProfile.customer', 'workerProfile.user');
        $event = Event::find($assignment->event_id);
        $phone = $this->phoneFor($assignment);
        if (! $event || ! $phone) {
            return;
        }
        $name = $assignment->workerProfile ? $assignment->workerProfile->displayName() : 'there';
        $when = $this->acceptedWhen($event);
        $role = $assignment->assignment_role ?: 'Crew';
        $days = $this->workDatesLine($assignment);
        $pay = number_format((int) $assignment->expected_total);
        $body = \App\Support\WhatsAppMessage::statusBlock('✅', 'Job accepted')
            .'*'.$event->name."*\n\n"
            .'Hello '.$name.",\n"
            .$role.($when !== '' ? ' · '.$when : '')."\n"
            .($days !== '' ? 'Days: '.$days."\n" : '')
            .'Pay: '.$pay." XAF\n"
            .'Your place is confirmed.';
        try {
            app(NotificationRouter::class)->sendWhatsAppText($phone, $body);
        } catch (\Exception $e) {
            \Log::warning('Accepted-job notice was not sent: '.$e->getMessage());
        }
    }

    public function submitDay(EventAssignment $assignment, $date, $activity, $hours, $phone)
    {
        $event = $assignment->event;
        $amount = $this->dayAmount($assignment);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('This place has no daily amount to request.');
        }
        $allowed = $this->workDates($assignment);
        if (count($allowed)) {
            if (! in_array($date, $allowed, true)) {
                throw new \InvalidArgumentException('That date is not one of your working days.');
            }
        } else {
            $start = $event->event_start_at ? $event->event_start_at->toDateString() : null;
            $end = $event->event_end_at ? $event->event_end_at->toDateString() : $start;
            if ($start && ($date < $start || ($end && $date > $end))) {
                throw new \InvalidArgumentException('That date is outside this event.');
            }
        }
        $marker = $date.' | ';
        $already = EventWorkerPayment::where('assignment_id', $assignment->id)
            ->where('notes', 'like', $marker.'%')
            ->exists();
        if ($already) {
            throw new \InvalidArgumentException('A payment request for that day is already in.');
        }

        return DB::transaction(function () use ($assignment, $event, $amount, $marker, $date, $activity, $hours, $phone) {
        $timesheet = EventTimesheet::firstOrCreate(
            ['assignment_id' => $assignment->id],
            [
                'event_id' => $event->id,
                'worker_profile_id' => $assignment->worker_profile_id,
                'status' => EventTimesheet::STATUS_DRAFT,
                'period_start' => $event->event_start_at ? $event->event_start_at->toDateString() : $date,
                'period_end' => $event->event_end_at ? $event->event_end_at->toDateString() : $date,
            ]
        );
        EventTimesheetEntry::create([
            'timesheet_id' => $timesheet->id,
            'work_date' => $date,
            'hours' => $hours,
            'notes' => $activity,
        ]);
        $timesheet->status = EventTimesheet::STATUS_SUBMITTED;
        $timesheet->submitted_at = now();
        $timesheet->total_days = $timesheet->entries()->count();
        $timesheet->notes = $activity;
        $timesheet->save();

        $payment = app(EventPaymentService::class)->createPayment($event, $assignment, [
            'amount' => $amount,
            'payment_method' => 'mobile_money',
            'mobile_money_number' => $phone,
            'notes' => $marker.$activity,
        ]);
        $assignment->payment_status = 'pending';
        $assignment->timesheet_status = 'submitted';
        $assignment->save();

        if ($assignment->workerProfile && $phone) {
            $assignment->workerProfile->telephone = $phone;
            $assignment->workerProfile->save();
        }

        return $payment;
        });
    }
}
