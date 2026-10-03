<?php

namespace App\Services\Messaging;

use App\Contracts\Sms\SmsProviderInterface;

/**
 * Infobip SMS API v3. Credentials stay in config. This class does not log them.
 */
class InfobipSmsProvider implements SmsProviderInterface
{
    protected $http;

    public function __construct($http = null)
    {
        $this->http = $http;
    }

    public function name()
    {
        return 'infobip';
    }

    public function send(array $message)
    {
        $base = (string) config('messaging.sms.infobip.base_url');
        $key = (string) config('messaging.sms.infobip.api_key');
        $sender = isset($message['from']) && $message['from'] !== ''
            ? (string) $message['from']
            : (string) config('messaging.sms.infobip.sender');
        if ($base === '' || $key === '' || $sender === '') {
            return $this->refused('sender_not_configured', 'temporary');
        }

        $payload = [
            'messages' => [[
                'sender' => $sender,
                'destinations' => [[
                    'to' => ltrim((string) $message['to'], '+'),
                    'messageId' => (string) $message['reference'],
                ]],
                'content' => ['text' => (string) $message['body']],
            ]],
        ];
        $options = ['includeSmsCountInResponse' => true];
        $entity = (string) config('messaging.sms.infobip.entity_id');
        $application = (string) config('messaging.sms.infobip.application_id');
        if ($entity !== '' || $application !== '') {
            $options['platform'] = array_filter([
                'entityId' => $entity !== '' ? $entity : null,
                'applicationId' => $application !== '' ? $application : null,
            ]);
        }
        $payload['messages'][0]['options'] = $options;
        $delivery = (string) config('messaging.sms.infobip.delivery_url');
        if ($delivery !== '') {
            $payload['messages'][0]['webhooks'] = [
                'delivery' => [
                    'url' => $delivery,
                    'intermediateReport' => true,
                    'contentType' => 'application/json',
                ],
            ];
        }

        try {
            $raw = $this->post($base.'/sms/3/messages', [
                'Authorization' => 'App '.$key,
                'Accept' => 'application/json',
            ], $payload);
        } catch (\Exception $e) {
            return $this->refused('provider_timeout', 'temporary');
        }

        $body = json_decode((string) $raw, true);
        $row = is_array($body) && isset($body['messages'][0]) ? $body['messages'][0] : null;
        if (! is_array($row)) {
            return $this->refused('provider_rejected', 'temporary');
        }
        $group = isset($row['status']['groupName']) ? strtoupper((string) $row['status']['groupName']) : '';
        $name = isset($row['status']['name']) ? (string) $row['status']['name'] : $group;
        $id = isset($row['messageId']) ? (string) $row['messageId'] : null;
        $units = isset($row['messageCount']) ? (int) $row['messageCount'] : null;
        if (in_array($group, ['PENDING', 'ACCEPTED'], true)) {
            return [
                'accepted' => true,
                'status' => 'SENT',
                'provider_message_id' => $id,
                'failure_class' => null,
                'failure_code' => null,
                'segments' => $units,
                'provider_units' => $units,
                'raw_status' => $name,
            ];
        }
        $permanent = in_array($group, ['UNDELIVERABLE', 'REJECTED', 'EXPIRED'], true);

        return [
            'accepted' => false,
            'status' => $group === 'REJECTED' ? 'REJECTED' : 'FAILED',
            'provider_message_id' => $id,
            'failure_class' => $permanent ? 'permanent_recipient' : 'temporary',
            'failure_code' => $name !== '' ? $name : 'provider_rejected',
            'segments' => $units,
            'provider_units' => $units,
            'raw_status' => $name,
        ];
    }

    public function getStatus($providerMessageId)
    {
        return null;
    }

    public function verifyWebhook(array $headers, $rawBody)
    {
        if (config('messaging.sms.driver') !== 'infobip') {
            return false;
        }
        $decoded = json_decode((string) $rawBody, true);

        return is_array($decoded) && isset($decoded['results']) && is_array($decoded['results']);
    }

    public function deliveryReports($rawBody)
    {
        $decoded = json_decode((string) $rawBody, true);
        if (! is_array($decoded) || ! isset($decoded['results'])) {
            return [];
        }
        $reports = [];
        foreach ($decoded['results'] as $row) {
            if (! is_array($row) || empty($row['messageId'])) {
                continue;
            }
            $group = isset($row['status']['groupName']) ? strtoupper((string) $row['status']['groupName']) : '';
            $reports[] = [
                'provider_message_id' => (string) $row['messageId'],
                'status' => $this->normalizedStatus($group),
                'raw_status' => isset($row['status']['name']) ? (string) $row['status']['name'] : $group,
                'provider_units' => isset($row['smsCount']) ? (int) $row['smsCount'] : (isset($row['messageCount']) ? (int) $row['messageCount'] : null),
                'provider_cost' => isset($row['price']['pricePerMessage']) ? (float) $row['price']['pricePerMessage'] : null,
                'provider_currency' => isset($row['price']['currency']) ? (string) $row['price']['currency'] : null,
                'permanent' => isset($row['error']['permanent']) ? (bool) $row['error']['permanent'] : null,
            ];
        }

        return $reports;
    }

    protected function normalizedStatus($group)
    {
        if ($group === 'DELIVERED') {
            return 'DELIVERED';
        }
        if (in_array($group, ['UNDELIVERABLE', 'EXPIRED', 'REJECTED'], true)) {
            return 'FAILED';
        }
        if (in_array($group, ['PENDING', 'ACCEPTED'], true)) {
            return 'SENT';
        }

        return 'UNKNOWN';
    }

    protected function post($url, array $headers, array $payload)
    {
        if (is_callable($this->http)) {
            return call_user_func($this->http, $url, $headers, $payload);
        }
        if (! function_exists('curl_init')) {
            throw new \RuntimeException('timeout');
        }
        $lines = ['Content-Type: application/json'];
        foreach ($headers as $name => $value) {
            $lines[] = $name.': '.$value;
        }
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => (int) config('messaging.sms.infobip.connect_timeout', 5),
            CURLOPT_TIMEOUT => (int) config('messaging.sms.infobip.timeout', 15),
        ]);
        $raw = curl_exec($handle);
        $error = curl_errno($handle);
        curl_close($handle);
        if ($error) {
            throw new \RuntimeException('timeout');
        }

        return $raw === false ? '' : $raw;
    }

    protected function refused($code, $class)
    {
        return [
            'accepted' => false,
            'status' => 'FAILED',
            'provider_message_id' => null,
            'failure_class' => $class,
            'failure_code' => $code,
            'segments' => null,
        ];
    }
}
