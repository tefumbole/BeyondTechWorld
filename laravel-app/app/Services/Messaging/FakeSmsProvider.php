<?php

namespace App\Services\Messaging;

use App\Contracts\Sms\SmsProviderInterface;

/**
 * In-memory provider for tests. It does not open a network connection.
 */
class FakeSmsProvider implements SmsProviderInterface
{
    public $mode = 'success';

    public $sent = [];

    protected $statuses = [];

    public function name()
    {
        return 'fake';
    }

    public function send(array $message)
    {
        $this->sent[] = $message;
        $id = 'fake-'.count($this->sent);
        if ($this->mode === 'permanent') {
            return $this->result(false, 'FAILED', null, 'permanent_recipient', 'destination_unavailable');
        }
        if ($this->mode === 'temporary') {
            return $this->result(false, 'FAILED', null, 'temporary', 'provider_outage');
        }
        if ($this->mode === 'reject') {
            return $this->result(false, 'REJECTED', null, 'permanent_recipient', 'rejected');
        }
        $this->statuses[$id] = 'SENT';

        return $this->result(true, 'SENT', $id, null, null);
    }

    public function markDelivered($providerMessageId)
    {
        $this->statuses[$providerMessageId] = 'DELIVERED';
    }

    public function markFailed($providerMessageId)
    {
        $this->statuses[$providerMessageId] = 'FAILED';
    }

    public function getStatus($providerMessageId)
    {
        if (! isset($this->statuses[$providerMessageId])) {
            return null;
        }

        return ['status' => $this->statuses[$providerMessageId], 'raw' => $this->statuses[$providerMessageId]];
    }

    public function verifyWebhook(array $headers, $rawBody)
    {
        $secret = (string) config('messaging.sms.webhook_secret');
        $given = isset($headers['x-messaging-signature']) ? (string) $headers['x-messaging-signature'] : '';

        return $secret !== '' && hash_equals($secret, $given);
    }

    protected function result($accepted, $status, $id, $class, $code)
    {
        return [
            'accepted' => $accepted,
            'status' => $status,
            'provider_message_id' => $id,
            'failure_class' => $class,
            'failure_code' => $code,
            'segments' => null,
        ];
    }
}
