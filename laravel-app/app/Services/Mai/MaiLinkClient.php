<?php

namespace App\Services\Mai;

/**
 * Talks only to the private Baileys worker. It never calls WaSender.
 */
class MaiLinkClient
{
    public function open($workerId, $phone)
    {
        return $this->call('POST', '/sessions', [
            'session_id' => $workerId,
            'phone' => $phone,
        ]);
    }

    public function pair($workerId)
    {
        return $this->call('POST', '/sessions/'.$workerId.'/pair', []);
    }

    public function status($workerId)
    {
        return $this->call('GET', '/sessions/'.$workerId, null);
    }

    public function send($workerId, $to, $text, array $quote = null)
    {
        $body = [
            'to' => $to,
            'text' => $text,
        ];
        if ($quote && ! empty($quote['id'])) {
            $body['quote'] = [
                'id' => substr((string) $quote['id'], 0, 128),
                'from_me' => ! empty($quote['from_me']),
                'text' => isset($quote['text']) ? substr((string) $quote['text'], 0, 300) : '',
            ];
        }

        return $this->call('POST', '/sessions/'.$workerId.'/send', $body);
    }

    public function disconnect($workerId)
    {
        return $this->call('DELETE', '/sessions/'.$workerId, null);
    }

    public function call($method, $path, $body)
    {
        $transport = config('cloud.mai_customer.transport');
        if (is_callable($transport)) {
            $payload = call_user_func($transport, $method, $path, $body);

            return is_array($payload) ? $payload : ['success' => false, 'error' => 'The link did not answer.'];
        }
        if (app()->runningUnitTests()) {
            return ['success' => false, 'error' => 'The link is not configured for this test.'];
        }

        return $this->http($method, $path, $body);
    }

    protected function http($method, $path, $body)
    {
        $base = rtrim((string) config('cloud.mai_customer.link_url', 'http://127.0.0.1:3921'), '/');
        $host = parse_url($base, PHP_URL_HOST);
        if ($host !== '127.0.0.1' && $host !== 'localhost') {
            return ['success' => false, 'error' => 'The WhatsApp link must stay on this server.'];
        }
        $token = $this->token();
        $ch = curl_init($base.$path);
        $headers = ['Accept: application/json', 'Authorization: Bearer '.$token];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($errno === 28) {
            return ['timeout' => true, 'success' => false, 'error' => 'The link timed out.'];
        }
        if ($raw === false || $raw === '') {
            return ['success' => false, 'error' => 'The link is not available.'];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : ['success' => false, 'error' => 'The link did not answer.'];
    }

    protected function token()
    {
        $env = getenv('MAI_WORKER_TOKEN');
        if (is_string($env) && $env !== '') {
            return $env;
        }
        $path = storage_path('app/whatsapp-link/token');
        if (! is_file($path)) {
            return '';
        }
        $token = @file_get_contents($path);

        return is_string($token) ? trim($token) : '';
    }
}
