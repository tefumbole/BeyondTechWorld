<?php

namespace App\Services\Calendar;

use App\Appointment\Appointment;
use App\Appointment\AppointmentActivity;
use App\Contracts\Calendar\CalendarProviderInterface;
use Carbon\Carbon;

class CalendarService
{
    protected $provider;

    public function __construct(CalendarProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    public function syncCreate(Appointment $appointment)
    {
        $result = $this->provider->createEvent($this->payload($appointment));

        return $this->store($appointment, $result, 'calendar_create');
    }

    public function syncUpdate(Appointment $appointment)
    {
        if (! $appointment->google_event_id) {
            return $this->syncCreate($appointment);
        }
        $result = $this->provider->updateEvent($appointment->google_event_id, $this->payload($appointment));

        return $this->store($appointment, $result, 'calendar_update');
    }

    public function syncCancel(Appointment $appointment)
    {
        if (! $appointment->google_event_id) {
            $appointment->google_sync_status = $this->provider->isConfigured() ? 'SYNCED' : 'NOT_CONFIGURED';
            $appointment->save();

            return $appointment;
        }
        $result = $this->provider->cancelEvent($appointment->google_event_id);
        $appointment->google_sync_status = ! empty($result['success']) ? 'SYNCED' : (! empty($result['configured']) ? 'FAILED' : 'NOT_CONFIGURED');
        $appointment->save();
        $this->audit($appointment, 'calendar_cancel', $appointment->google_sync_status);

        return $appointment;
    }

    public function applyNotification($token, $resourceState, $resourceUri)
    {
        $expected = (string) config('services.calendar.channel_token');
        if ($expected === '' || ! hash_equals($expected, (string) $token)) {
            return ['success' => false, 'error' => 'unauthorized'];
        }
        $eventId = $this->eventIdFromUri($resourceUri);
        if ($eventId === null) {
            return ['success' => false, 'error' => 'no_event'];
        }
        $appointment = Appointment::where('google_event_id', $eventId)->first();
        if (! $appointment) {
            return ['success' => false, 'error' => 'unknown_event'];
        }
        if ($resourceState === 'not_exists') {
            $appointment->status = Appointment::CANCELLED;
            $appointment->save();
            $this->audit($appointment, 'calendar_notification', 'cancelled');

            return ['success' => true, 'appointment_id' => $appointment->id];
        }
        $fetched = $this->provider->getEvent($eventId);
        if (empty($fetched['success']) || empty($fetched['event']['start']['dateTime'])) {
            $this->audit($appointment, 'calendar_notification', 'unchanged');

            return ['success' => true, 'appointment_id' => $appointment->id, 'changed' => false];
        }
        $appointment->starts_at = Carbon::parse($fetched['event']['start']['dateTime']);
        if (! empty($fetched['event']['end']['dateTime'])) {
            $appointment->ends_at = Carbon::parse($fetched['event']['end']['dateTime']);
        }
        $appointment->google_sync_status = 'SYNCED';
        $appointment->save();
        $this->audit($appointment, 'calendar_notification', 'updated');

        return ['success' => true, 'appointment_id' => $appointment->id, 'changed' => true];
    }

    protected function store(Appointment $appointment, array $result, $type)
    {
        if (empty($result['configured'])) {
            $appointment->google_sync_status = 'NOT_CONFIGURED';
        } elseif (! empty($result['success'])) {
            $appointment->google_sync_status = 'SYNCED';
            if (! empty($result['event_id'])) {
                $appointment->google_event_id = $result['event_id'];
            }
        } else {
            $appointment->google_sync_status = 'FAILED';
        }
        $appointment->save();
        $this->audit($appointment, $type, $appointment->google_sync_status);

        return $appointment;
    }

    protected function payload(Appointment $appointment)
    {
        return [
            'summary' => $appointment->purpose,
            'description' => 'ERP '.$appointment->reference,
            'location' => (string) $appointment->location,
            'start' => $appointment->starts_at->toIso8601String(),
            'end' => $appointment->ends_at->toIso8601String(),
        ];
    }

    protected function eventIdFromUri($uri)
    {
        if (! is_string($uri) || ! preg_match('#/events/([^/?]+)#', $uri, $match)) {
            return null;
        }

        return rawurldecode($match[1]);
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
