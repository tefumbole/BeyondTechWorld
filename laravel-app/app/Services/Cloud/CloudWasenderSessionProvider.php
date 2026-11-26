<?php

namespace App\Services\Cloud;

use App\Cloud\CloudWhatsAppConnection;

/**
 * Creates one Wasender session for a customer company.
 * Uses the personal access token, never the staff session key.
 */
class CloudWasenderSessionProvider
{
    public function provision(CloudWhatsAppConnection $connection)
    {
        $phone = trim((string) $connection->phone_number);
        if ($phone === '') {
            return [];
        }
        $created = $this->post('/whatsapp-sessions', [
            'name' => substr((string) ($connection->display_name ?: 'Mbole AI'), 0, 80),
            'phone_number' => $phone,
            'account_protection' => true,
            'log_messages' => true,
            'webhook_url' => url('/api/webhooks/wasender'),
            'webhook_enabled' => true,
            'webhook_events' => ['messages.received', 'messages.update', 'session.status', 'message.sent'],
        ]);
        $data = isset($created['data']) && is_array($created['data']) ? $created['data'] : [];
        $id = isset($data['id']) ? (string) $data['id'] : '';
        if ($id === '' || $id === trim((string) config('services.whatsapp.wasender_session_id'))) {
            return [];
        }
        $connection->provider_connection_id = $id;
        $connection->rememberSecret(
            isset($data['api_key']) ? (string) $data['api_key'] : '',
            isset($data['webhook_secret']) ? (string) $data['webhook_secret'] : ''
        );
        $linked = $this->post('/whatsapp-sessions/'.$id.'/connect', ['linkMethod' => 'qr']);
        $linkData = isset($linked['data']) && is_array($linked['data']) ? $linked['data'] : [];

        return [
            'session_id' => $id,
            'qr' => isset($linkData['qrCode']) ? (string) $linkData['qrCode'] : '',
            'pairing_code' => $this->pairingCode($linkData),
        ];
    }

    public function qr(CloudWhatsAppConnection $connection)
    {
        $id = trim((string) $connection->provider_connection_id);
        if ($id === '') {
            return null;
        }
        $fetched = $this->get('/whatsapp-sessions/'.$id.'/qrcode');
        $data = isset($fetched['data']) && is_array($fetched['data']) ? $fetched['data'] : [];

        return isset($data['qrCode']) ? (string) $data['qrCode'] : null;
    }

    public function pairingCode(array $data)
    {
        foreach (['pairingCode', 'pairing_code', 'pairCode'] as $key) {
            if (! empty($data[$key]) && is_string($data[$key])) {
                return trim($data[$key]);
            }
        }

        return '';
    }

    public function status(CloudWhatsAppConnection $connection)
    {
        $id = trim((string) $connection->provider_connection_id);
        if ($id === '') {
            return 'ERROR';
        }
        $fetched = $this->get('/whatsapp-sessions/'.$id);
        $data = isset($fetched['data']) && is_array($fetched['data']) ? $fetched['data'] : [];
        $status = strtolower((string) (isset($data['status']) ? $data['status'] : ''));
        if (in_array($status, ['connected', 'active'], true)) {
            return 'CONNECTED';
        }
        if (in_array($status, ['need_scan', 'awaiting_qr', 'qr'], true)) {
            return 'AWAITING_QR';
        }
        if ($status === '') {
            return 'AWAITING_QR';
        }

        return 'ERROR';
    }

    public function reportedPhone(CloudWhatsAppConnection $connection)
    {
        $id = trim((string) $connection->provider_connection_id);
        if ($id === '') {
            return '';
        }
        $fetched = $this->get('/whatsapp-sessions/'.$id);
        $data = isset($fetched['data']) && is_array($fetched['data']) ? $fetched['data'] : [];

        return isset($data['phone_number']) ? (string) $data['phone_number'] : '';
    }

    public function disconnect(CloudWhatsAppConnection $connection)
    {
        $id = trim((string) $connection->provider_connection_id);
        if ($id === '') {
            return true;
        }
        $this->post('/whatsapp-sessions/'.$id.'/logout', []);

        return true;
    }

    protected function post($path, array $body)
    {
        return $this->call('POST', $path, $body);
    }

    protected function get($path)
    {
        return $this->call('GET', $path, null);
    }

    protected function call($method, $path, $body)
    {
        $token = trim((string) config('services.whatsapp.wasender_personal_token'));
        $staff = trim((string) config('services.whatsapp.wasender_api_key'));
        if ($token === '' || $token === $staff) {
            return [];
        }
        $base = rtrim((string) config('services.whatsapp.wasender_base_url', 'https://wasenderapi.com/api'), '/');
        $ch = curl_init($base.$path);
        $headers = [
            'Authorization: Bearer '.$token,
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($body ?: new \stdClass());
        }
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        curl_close($ch);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
