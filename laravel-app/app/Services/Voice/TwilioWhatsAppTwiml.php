<?php

namespace App\Services\Voice;

/**
 * TwiML for a WhatsApp call. Recording and PSTN Dial are never emitted.
 */
class TwilioWhatsAppTwiml
{
    public function forResult(array $result)
    {
        if (! empty($result['duplicate'])) {
            return $this->hangup();
        }
        if (empty($result['ok'])) {
            return $this->reject();
        }
        $stream = trim((string) config('voice.twilio.stream_url'));
        if (config('voice.twilio.realtime_bridge') && $stream !== '') {
            return $this->stream($stream);
        }

        return $this->apology();
    }

    public function reject()
    {
        return $this->wrap('<Reject/>');
    }

    public function hangup()
    {
        return $this->wrap('<Hangup/>');
    }

    public function apology()
    {
        $text = htmlspecialchars("I'm sorry, I can't continue the assistant right now.", ENT_XML1, 'UTF-8');

        return $this->wrap('<Say>'.$text.'</Say><Hangup/>');
    }

    public function stream($url)
    {
        $safe = htmlspecialchars((string) $url, ENT_XML1, 'UTF-8');

        return $this->wrap('<Connect><Stream url="'.$safe.'"/></Connect>');
    }

    protected function wrap($body)
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Response>'.$body.'</Response>';
    }
}
