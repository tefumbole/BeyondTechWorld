<?php

namespace App\Services;

/**
 * Legacy facade — delegates to TwilioWhatsAppService / NotificationRouter.
 * Prefer App\Services\Messaging\NotificationRouter for new code.
 */
class WhatsAppService
{
    protected $twilio;

    public function __construct(TwilioWhatsAppService $twilio)
    {
        $this->twilio = $twilio;
    }

    public function sendMessage($to, $message)
    {
        return app(\App\Services\Messaging\NotificationRouter::class)
            ->sendWhatsAppText($to, $message);
    }

    public function sendContentTemplate($to, $contentSid, array $variables = [], $mediaUrl = null)
    {
        return $this->twilio->sendContentTemplate($to, $contentSid, $variables, $mediaUrl);
    }
}
