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
    public function inviteAssigned(Event $event)
    {
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
            $body .= "\nAccept your place:\n{$url}\n\n"
                ."If you cannot take this job, open the same link and choose Reject.\n"
                .'After you accept, a code is sent to this phone so you can open the event and your timesheet.';
            if ($attempted) {
                usleep(5000000);
            }
            $attempted = true;
            try {
                $result = app(NotificationRouter::class)->sendWhatsAppText($phone, $body);
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
                usleep(5000000);
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

    public function markAccepted(EventAssignment $assignment)
    {
        $assignment->acceptance_status = 'accepted';
        $assignment->accepted_at = now();
        $assignment->save();
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
