<?php

namespace App\Services\Voice;

use App\Contracts\Voice\VoiceProvider;
use App\Voice\VoiceCall;

/**
 * WhatsApp Business Calling control for a Twilio sender.
 * It never dials the public telephone network and never starts a recording.
 * Outbound WhatsApp calls stay off. No HTTP request is made from this class.
 */
class TwilioWhatsAppVoiceProvider implements VoiceProvider
{
    public function name()
    {
        return 'twilio-whatsapp';
    }

    public function transfer(VoiceCall $call, $target)
    {
        $target = trim((string) $target);
        if ($target === '' || stripos($target, 'whatsapp:') !== 0) {
            return ['ok' => false, 'reason' => 'pstn_not_allowed'];
        }
        if (! config('voice.twilio.outbound_calls')) {
            return ['ok' => false, 'reason' => 'whatsapp_handover_not_configured'];
        }

        return ['ok' => false, 'reason' => 'whatsapp_handover_not_configured'];
    }

    public function hangup(VoiceCall $call, $reason)
    {
        return ['ok' => true, 'reason' => (string) $reason, 'recording' => false];
    }

    public function placeOutbound($to)
    {
        return ['ok' => false, 'reason' => 'outbound_disabled'];
    }
}
