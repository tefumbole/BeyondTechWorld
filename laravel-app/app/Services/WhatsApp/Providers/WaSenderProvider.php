<?php

namespace App\Services\WhatsApp\Providers;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Services\BeyondWasenderService;
use App\Services\WhatsApp\WhatsAppSessionStatusService;

/**
 * Hub-facing WaSender adapter. Delegates sends to the existing production client
 * so the 5s lock / retry behaviour is unchanged.
 */
class WaSenderProvider implements WhatsAppProviderInterface
{
    protected $wasender;
    protected $sessionStatus;

    public function __construct(BeyondWasenderService $wasender, WhatsAppSessionStatusService $sessionStatus)
    {
        $this->wasender = $wasender;
        $this->sessionStatus = $sessionStatus;
    }

    public function sendText($phone, $message)
    {
        return $this->wasender->sendTextRaw($phone, $message);
    }

    public function sendDocument($phone, $localPath, $fileName = null, $caption = null)
    {
        return $this->wasender->sendDocument($phone, $localPath, $fileName, $caption);
    }

    public function sendImage($phone, $localPath, $caption = null)
    {
        return $this->wasender->sendImage($phone, $localPath, $caption);
    }

    public function sessionStatus()
    {
        return $this->sessionStatus->status();
    }

    public function isConfigured()
    {
        return $this->wasender->isConfigured();
    }
}
