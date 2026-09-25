<?php

namespace App\Services\Calendar;

use App\Contracts\Calendar\CalendarProviderInterface;

class GoogleCalendarProvider implements CalendarProviderInterface
{
    public function isConfigured()
    {
        return trim((string) config('services.calendar.client_id')) !== ''
            && trim((string) config('services.calendar.client_secret')) !== ''
            && trim((string) config('services.calendar.refresh_token')) !== ''
            && trim((string) config('services.calendar.calendar_id')) !== '';
    }

    public function createEvent(array $event)
    {
        return $this->write('POST', null, $event);
    }

    public function updateEvent($eventId, array $event)
    {
        return $this->write('PUT', $eventId, $event);
    }

    public function cancelEvent($eventId)
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'configured' => false, 'error' => 'not_configured'];
        }
        $token = $this->accessToken();
        if ($token === null) {
            return ['success' => false, 'configured' => true, 'error' => 'token_failed'];
        }
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 15]);
            $client->request('DELETE', $this->eventUrl($eventId), [
                'headers' => ['Authorization' => 'Bearer '.$token],
                'http_errors' => false,
            ]);

            return ['success' => true, 'configured' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'configured' => true, 'error' => 'calendar_failed'];
        }
    }

    public function getEvent($eventId)
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'configured' => false, 'error' => 'not_configured'];
        }
        $token = $this->accessToken();
        if ($token === null) {
            return ['success' => false, 'configured' => true, 'error' => 'token_failed'];
        }
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 15]);
            $response = $client->request('GET', $this->eventUrl($eventId), [
                'headers' => ['Authorization' => 'Bearer '.$token],
                'http_errors' => false,
            ]);
            $body = json_decode((string) $response->getBody(), true);
            if ($response->getStatusCode() >= 400 || ! is_array($body)) {
                return ['success' => false, 'configured' => true, 'error' => 'calendar_failed'];
            }

            return ['success' => true, 'configured' => true, 'event' => $body];
        } catch (\Exception $e) {
            return ['success' => false, 'configured' => true, 'error' => 'calendar_failed'];
        }
    }

    protected function write($method, $eventId, array $event)
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'configured' => false, 'error' => 'not_configured'];
        }
        $token = $this->accessToken();
        if ($token === null) {
            return ['success' => false, 'configured' => true, 'error' => 'token_failed'];
        }
        $payload = [
            'summary' => isset($event['summary']) ? $event['summary'] : 'Appointment',
            'description' => isset($event['description']) ? $event['description'] : '',
            'location' => isset($event['location']) ? $event['location'] : '',
            'start' => ['dateTime' => $event['start'], 'timeZone' => config('app.timezone')],
            'end' => ['dateTime' => $event['end'], 'timeZone' => config('app.timezone')],
        ];
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 15]);
            $response = $client->request($method, $this->eventUrl($eventId), [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($payload),
                'http_errors' => false,
            ]);
            $body = json_decode((string) $response->getBody(), true);
            if ($response->getStatusCode() >= 400 || ! is_array($body) || empty($body['id'])) {
                return ['success' => false, 'configured' => true, 'error' => 'calendar_failed'];
            }

            return ['success' => true, 'configured' => true, 'event_id' => (string) $body['id']];
        } catch (\Exception $e) {
            return ['success' => false, 'configured' => true, 'error' => 'calendar_failed'];
        }
    }

    protected function accessToken()
    {
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 15]);
            $response = $client->request('POST', 'https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'client_id' => config('services.calendar.client_id'),
                    'client_secret' => config('services.calendar.client_secret'),
                    'refresh_token' => config('services.calendar.refresh_token'),
                    'grant_type' => 'refresh_token',
                ],
                'http_errors' => false,
            ]);
            $body = json_decode((string) $response->getBody(), true);
            if (! is_array($body) || empty($body['access_token'])) {
                return null;
            }

            return $body['access_token'];
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function eventUrl($eventId)
    {
        $calendar = rawurlencode((string) config('services.calendar.calendar_id'));
        $base = 'https://www.googleapis.com/calendar/v3/calendars/'.$calendar.'/events';
        if ($eventId) {
            return $base.'/'.rawurlencode((string) $eventId);
        }

        return $base;
    }
}
