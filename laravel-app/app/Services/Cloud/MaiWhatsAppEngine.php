<?php

namespace App\Services\Cloud;

/**
 * One WhatsApp session per phone, on Beyond's own link engine.
 * A company is not required. The phone must still approve the link.
 * This never uses Beyond's Wasender session.
 */
class MaiWhatsAppEngine
{
    public function open($phone)
    {
        $digits = $this->digits($phone);
        if ($digits === '') {
            return $this->blank('ERROR');
        }
        $payload = $this->request('POST', '/sessions', ['phone' => $digits], 20);

        return $this->shape($payload);
    }

    public function status($phone, $withQr = false)
    {
        $id = $this->id($phone);
        if ($id === '') {
            return $this->blank('ERROR');
        }
        $path = '/sessions/'.$id;
        if ($withQr) {
            $path .= '?qr=1';
        }

        return $this->shape($this->request('GET', $path, null, 8));
    }

    public function pair($phone)
    {
        $id = $this->id($phone);
        if ($id === '') {
            return $this->blank('ERROR');
        }

        return $this->shape($this->request('POST', '/sessions/'.$id.'/pair', [], 25));
    }

    public function chats($phone)
    {
        $id = $this->id($phone);
        if ($id === '') {
            return [];
        }
        $payload = $this->request('GET', '/sessions/'.$id.'/chats', null, 8);
        if (! is_array($payload) || empty($payload['chats']) || ! is_array($payload['chats'])) {
            return [];
        }
        $rows = [];
        foreach ($payload['chats'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $phoneDigits = isset($row['phone']) ? preg_replace('/\D/', '', (string) $row['phone']) : '';
            $jid = isset($row['jid']) ? (string) $row['jid'] : '';
            if (! preg_match('/^([1-9][0-9]{6,16}@s\.whatsapp\.net|[0-9]+(-[0-9]+)?@g\.us)$/', $jid)) {
                continue;
            }
            $rows[] = [
                'jid' => $jid,
                'name' => isset($row['name']) ? (string) $row['name'] : '',
                'phone' => $phoneDigits,
                'preview' => isset($row['preview']) ? (string) $row['preview'] : '',
                'unread' => isset($row['unread']) ? (int) $row['unread'] : 0,
                'at' => isset($row['at']) ? (int) $row['at'] : 0,
            ];
        }

        return $rows;
    }

    public function messages($phone)
    {
        $id = $this->id($phone);
        if ($id === '') {
            return [];
        }
        $payload = $this->request('GET', '/sessions/'.$id.'/messages', null, 8);
        if (! is_array($payload) || empty($payload['messages']) || ! is_array($payload['messages'])) {
            return [];
        }
        $rows = [];
        foreach ($payload['messages'] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $text = isset($row['text']) ? trim((string) $row['text']) : '';
            if ($text === '') {
                continue;
            }
            $rows[] = [
                'id' => isset($row['id']) ? (string) $row['id'] : '',
                'from' => isset($row['from']) ? (string) $row['from'] : '',
                'from_me' => ! empty($row['from_me']),
                'text' => function_exists('mb_substr') ? mb_substr($text, 0, 1000) : substr($text, 0, 1000),
                'at' => isset($row['at']) ? (string) $row['at'] : '',
            ];
        }

        return $rows;
    }

    public function send($phone, $to, $text)
    {
        $id = $this->id($phone);
        if ($id === '') {
            return ['success' => false, 'error' => 'Enter a phone number.'];
        }
        $body = function_exists('mb_substr') ? mb_substr(trim((string) $text), 0, 1000) : substr(trim((string) $text), 0, 1000);
        $target = trim((string) $to);
        if (substr($target, -5) !== '@g.us') {
            $target = preg_replace('/\D/', '', $target);
        }
        $payload = $this->request('POST', '/sessions/'.$id.'/send', [
            'to' => $target,
            'text' => $body,
        ], 20);
        if (! is_array($payload) || empty($payload['success'])) {
            $error = is_array($payload) && isset($payload['error']) ? (string) $payload['error'] : 'WhatsApp is not linked for this phone yet.';

            return ['success' => false, 'error' => $error];
        }

        return ['success' => true];
    }

    protected function digits($phone)
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (! preg_match('/^[1-9][0-9]{7,14}$/', $digits)) {
            return '';
        }

        return $digits;
    }

    protected function id($phone)
    {
        $digits = $this->digits($phone);

        return $digits === '' ? '' : 'p'.$digits;
    }

    protected function blank($status)
    {
        return [
            'status' => $status,
            'phone' => null,
            'qr' => null,
            'pairing_code' => null,
            'error' => null,
        ];
    }

    protected function shape($payload)
    {
        if (! is_array($payload) || empty($payload['status'])) {
            return $this->blank('NOT_CONNECTED');
        }
        $qr = isset($payload['qr']) ? (string) $payload['qr'] : '';
        if (strpos($qr, 'data:image/') !== 0) {
            $qr = '';
        }

        return [
            'status' => (string) $payload['status'],
            'phone' => isset($payload['phone']) ? (string) $payload['phone'] : null,
            'qr' => $qr !== '' ? $qr : null,
            'pairing_code' => isset($payload['pairing_code']) && $payload['pairing_code'] !== '' ? (string) $payload['pairing_code'] : null,
            'error' => isset($payload['error']) && $payload['error'] !== '' ? (string) $payload['error'] : null,
        ];
    }

    protected function request($method, $path, $body, $timeout)
    {
        $transport = config('cloud.whatsapp.link_transport');
        if (is_callable($transport)) {
            return call_user_func($transport, $method, $path, $body);
        }
        if (app()->runningUnitTests()) {
            return null;
        }
        $token = $this->token();
        $base = rtrim((string) config('cloud.whatsapp.link_url', 'http://127.0.0.1:3921'), '/');
        $ch = curl_init($base.$path);
        $headers = [
            'Authorization: Bearer '.$token,
            'Accept: application/json',
        ];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($body !== null) {
            $encoded = json_encode($body);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
        }
        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = json_decode((string) $raw, true);
        if ($raw === false || $http < 200 || $http >= 300) {
            if (is_array($decoded) && ! empty($decoded['error'])) {
                return $decoded;
            }

            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    protected function token()
    {
        $path = storage_path('app/whatsapp-link/token');
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        if (! is_file($path)) {
            file_put_contents($path, bin2hex(random_bytes(32)));
            chmod($path, 0600);
        }

        return trim((string) file_get_contents($path));
    }
}
