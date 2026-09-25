<?php

namespace App\Services\Appointment;

use App\Appointment\Appointment;
use App\Appointment\AppointmentActivity;
use App\Appointment\AppointmentAvailability;
use App\Appointment\AppointmentReminder;
use App\Services\Calendar\CalendarService;
use App\Services\WhatsApp\WhatsAppConversationService;
use App\User;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppSetting;
use Carbon\Carbon;

class AppointmentService
{
    protected $calendar;

    public function __construct(CalendarService $calendar)
    {
        $this->calendar = $calendar;
    }

    public function categories()
    {
        return [
            'Customer Consultation',
            'Site Visit',
            'Rental Consultation',
            'Technical Support',
            'Network Assessment',
            'Event Planning',
            'Training',
            'Management Meeting',
            'Other',
        ];
    }

    public function categoryFor($text)
    {
        $t = strtolower((string) $text);
        if (strpos($t, 'network') !== false) {
            return 'Network Assessment';
        }
        if (strpos($t, 'site visit') !== false) {
            return 'Site Visit';
        }
        if (strpos($t, 'rental') !== false) {
            return 'Rental Consultation';
        }
        if (strpos($t, 'training') !== false) {
            return 'Training';
        }
        if (strpos($t, 'event') !== false) {
            return 'Event Planning';
        }
        if (strpos($t, 'support') !== false) {
            return 'Technical Support';
        }
        if (strpos($t, 'management') !== false || strpos($t, 'meeting') !== false) {
            return 'Management Meeting';
        }

        return 'Customer Consultation';
    }

    public function resolveStaff($name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return ['user' => null, 'ambiguous' => false, 'label' => 'BeyondTechWorld'];
        }
        $rows = User::where('name', 'like', '%'.$name.'%')->where('is_deleted', false)->limit(5)->get();
        if ($rows->count() === 1) {
            return ['user' => $rows->first(), 'ambiguous' => false, 'label' => $rows->first()->name];
        }
        if ($rows->count() > 1) {
            return ['user' => null, 'ambiguous' => true, 'label' => $name];
        }

        return ['user' => null, 'ambiguous' => false, 'label' => $name];
    }

    public function slotsFor(Carbon $day, $staffUserId)
    {
        $query = AppointmentAvailability::where('enabled', true)->where('weekday', $day->dayOfWeek);
        if ($staffUserId) {
            $query->where(function ($q) use ($staffUserId) {
                $q->whereNull('staff_user_id')->orWhere('staff_user_id', $staffUserId);
            });
        } else {
            $query->whereNull('staff_user_id');
        }
        $rules = $query->orderBy('starts_time')->get();
        $slots = [];
        foreach ($rules as $rule) {
            $cursor = Carbon::parse($day->toDateString().' '.$rule->starts_time);
            $bound = Carbon::parse($day->toDateString().' '.$rule->ends_time);
            $minutes = max(15, (int) $rule->slot_minutes);
            while ($cursor->copy()->addMinutes($minutes)->lte($bound) && count($slots) < 3) {
                $end = $cursor->copy()->addMinutes($minutes);
                if ($end->gt(Carbon::now()) && ! $this->overlaps($cursor, $end, null)) {
                    $slots[] = [
                        'start' => $cursor->toDateTimeString(),
                        'end' => $end->toDateTimeString(),
                        'location' => $rule->location,
                    ];
                }
                $cursor->addMinutes($minutes);
            }
        }

        return $slots;
    }

    public function book(array $data)
    {
        $start = Carbon::parse($data['starts_at']);
        $end = Carbon::parse($data['ends_at']);
        $existing = Appointment::where('contact_id', isset($data['contact_id']) ? $data['contact_id'] : null)
            ->where('starts_at', $start)
            ->where('status', Appointment::CONFIRMED)
            ->first();
        if ($existing) {
            return $existing;
        }
        if ($this->overlaps($start, $end, null)) {
            return null;
        }
        $row = Appointment::create([
            'reference' => 'PENDING',
            'category' => $data['category'],
            'purpose' => $data['purpose'],
            'customer_id' => isset($data['customer_id']) ? $data['customer_id'] : null,
            'contact_id' => isset($data['contact_id']) ? $data['contact_id'] : null,
            'conversation_id' => isset($data['conversation_id']) ? $data['conversation_id'] : null,
            'staff_user_id' => isset($data['staff_user_id']) ? $data['staff_user_id'] : null,
            'staff_label' => isset($data['staff_label']) ? $data['staff_label'] : 'BeyondTechWorld',
            'location' => isset($data['location']) ? $data['location'] : null,
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => Appointment::CONFIRMED,
            'source' => 'WHATSAPP',
            'google_sync_status' => 'NOT_CONFIGURED',
        ]);
        $row->reference = 'APT-'.str_pad((string) $row->id, 4, '0', STR_PAD_LEFT);
        $row->save();
        $this->audit($row, 'created', $row->reference);
        $this->calendar->syncCreate($row);

        return $row->fresh();
    }

    public function cancel(Appointment $appointment)
    {
        $appointment->status = Appointment::CANCELLED;
        $appointment->save();
        $this->audit($appointment, 'cancelled', $appointment->reference);
        $this->calendar->syncCancel($appointment);

        return $appointment->fresh();
    }

    public function reschedule(Appointment $appointment, $start, $end, $location = null)
    {
        $startAt = Carbon::parse($start);
        $endAt = Carbon::parse($end);
        if ($this->overlaps($startAt, $endAt, $appointment->id)) {
            return null;
        }
        $appointment->starts_at = $startAt;
        $appointment->ends_at = $endAt;
        if ($location) {
            $appointment->location = $location;
        }
        $appointment->status = Appointment::CONFIRMED;
        $appointment->customer_response = null;
        $appointment->save();
        $this->audit($appointment, 'rescheduled', $appointment->reference);
        $this->calendar->syncUpdate($appointment);

        return $appointment->fresh();
    }

    public function recordResponse(Appointment $appointment, $response)
    {
        $appointment->customer_response = $response;
        $appointment->save();
        $this->audit($appointment, 'customer_response', $response);

        return $appointment;
    }

    public function upcomingForContact($contactId)
    {
        return Appointment::where('contact_id', $contactId)
            ->where('status', Appointment::CONFIRMED)
            ->where('starts_at', '>=', Carbon::now()->subHour())
            ->orderBy('starts_at')
            ->get();
    }

    public function confirmationText(Appointment $appointment)
    {
        $lines = [
            'APPOINTMENT CONFIRMED',
            '',
            $appointment->purpose,
            '',
            'Date: '.$appointment->starts_at->format('j F Y'),
            'Time: '.$appointment->starts_at->format('H:i'),
            'With: '.$appointment->staff_label,
            'Location: '.($appointment->location ?: 'To be confirmed'),
            '',
            'Reference: '.$appointment->reference,
        ];
        if ($appointment->google_sync_status === 'SYNCED') {
            $lines[] = 'This time is also on the connected Google Calendar.';
        } elseif ($appointment->google_sync_status === 'FAILED') {
            $lines[] = 'Google Calendar was not updated.';
        } else {
            $lines[] = 'This appointment is saved in BeyondTechWorld. Google Calendar is not connected, so it was not copied there.';
        }

        return implode("\n", $lines);
    }

    public function sendDueReminders()
    {
        $sent = 0;
        $intervals = $this->enabledIntervals();
        $rows = Appointment::where('status', Appointment::CONFIRMED)->where('starts_at', '>', Carbon::now())->get();
        foreach ($rows as $appointment) {
            foreach ($intervals as $hours) {
                $key = $hours.'h';
                $fireAt = $appointment->starts_at->copy()->subHours($hours);
                if (Carbon::now()->lt($fireAt) || Carbon::now()->gte($appointment->starts_at)) {
                    continue;
                }
                $already = AppointmentReminder::where('appointment_id', $appointment->id)->where('interval_key', $key)->first();
                if ($already) {
                    continue;
                }
                AppointmentReminder::create([
                    'appointment_id' => $appointment->id,
                    'interval_key' => $key,
                    'sent_at' => Carbon::now(),
                ]);
                $this->notify($appointment, $hours);
                $this->audit($appointment, 'reminder', $key);
                $sent++;
            }
        }

        return $sent;
    }

    public function enabledIntervals()
    {
        $raw = WhatsAppSetting::getValue('appointment_reminder_intervals', '24');
        $parts = preg_split('/\s*,\s*/', (string) $raw);
        $allowed = ['24', '4', '2', '1'];
        $out = [];
        foreach ($parts as $part) {
            if (in_array($part, $allowed, true)) {
                $out[] = (int) $part;
            }
        }
        if (count($out) === 0) {
            $out[] = 24;
        }

        return $out;
    }

    public function overlaps(Carbon $start, Carbon $end, $ignoreId)
    {
        $query = Appointment::where('status', Appointment::CONFIRMED)
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    protected function notify(Appointment $appointment, $hours)
    {
        $conversation = $appointment->conversation_id
            ? WhatsAppConversation::find($appointment->conversation_id)
            : null;
        if (! $conversation) {
            return;
        }
        if ((int) $hours === 24) {
            $body = 'Reminder: Your BeyondTechWorld consultation is on '.$appointment->starts_at->format('j F').' at '.$appointment->starts_at->format('g:i A').'.';
        } elseif ((int) $hours === 2) {
            $body = 'Your appointment begins in 2 hours.';
        } else {
            $body = 'Reminder: '.$appointment->reference.' is in '.$hours.' hours, at '.$appointment->starts_at->format('H:i').'.';
        }
        $body .= ' Reply CONFIRM, RESCHEDULE, or CANCEL.';
        app(WhatsAppConversationService::class)->ownerNotice($conversation, $body);
    }

    public function tool($name, array $params, array $context)
    {
        $contactId = isset($context['contact_id']) ? $context['contact_id'] : null;
        if ($name === 'get_my_appointments') {
            return ['success' => true, 'appointments' => $this->upcomingForContact($contactId)->pluck('reference')];
        }
        if ($name === 'check_appointment_availability') {
            $day = isset($params['day']) ? Carbon::parse($params['day']) : Carbon::tomorrow();
            $slots = $this->slotsFor($day, isset($params['staff_user_id']) ? $params['staff_user_id'] : null);

            return ['success' => true, 'slots' => $slots, 'invented' => false];
        }
        if ($name === 'cancel_appointment') {
            $row = Appointment::where('reference', isset($params['reference']) ? $params['reference'] : '')
                ->where('contact_id', $contactId)->first();
            if (! $row) {
                return ['success' => false, 'error' => 'not_found'];
            }
            $this->cancel($row);

            return ['success' => true, 'reference' => $row->reference];
        }

        return ['success' => false, 'error' => 'choice_required'];
    }

    protected function audit(Appointment $appointment, $type, $body)
    {
        AppointmentActivity::create([
            'appointment_id' => $appointment->id,
            'type' => $type,
            'body' => $body,
        ]);
    }
}
