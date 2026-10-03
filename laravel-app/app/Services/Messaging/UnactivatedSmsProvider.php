<?php

namespace App\Services\Messaging;

use App\Contracts\Sms\SmsProviderInterface;

/**
 * Names a future provider without calling it.
 */
class UnactivatedSmsProvider implements SmsProviderInterface
{
    protected $providerName;

    public function __construct($providerName)
    {
        $this->providerName = (string) $providerName;
    }

    public function name()
    {
        return $this->providerName;
    }

    public function send(array $message)
    {
        return [
            'accepted' => false,
            'status' => 'FAILED',
            'provider_message_id' => null,
            'failure_class' => 'temporary',
            'failure_code' => 'provider_not_activated',
            'segments' => null,
        ];
    }

    public function getStatus($providerMessageId)
    {
        return null;
    }

    public function verifyWebhook(array $headers, $rawBody)
    {
        return false;
    }
}
