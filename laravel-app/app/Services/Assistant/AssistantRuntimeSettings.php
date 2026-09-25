<?php

namespace App\Services\Assistant;

use App\WhatsApp\WhatsAppSetting;

class AssistantRuntimeSettings
{
    public static function flag($key, $defaultOn = false)
    {
        $saved = WhatsAppSetting::getValue($key, $defaultOn ? '1' : '0');

        return $saved === '1' || $saved === 'true';
    }

    public static function aiFirst()
    {
        return self::flag('ai_first', false);
    }

    public static function manualReplyTakesOver()
    {
        return self::flag('manual_reply_takes_over', true);
    }

    public static function collectUnknownName()
    {
        return self::flag('assistant_collect_name', true);
    }

    public static function greetByName()
    {
        return self::flag('assistant_greet_by_name', true);
    }

    public static function handoverUserId()
    {
        return (int) WhatsAppSetting::getValue('default_handover_user_id', '0');
    }

    public static function historyLimit()
    {
        $saved = (int) WhatsAppSetting::getValue('assistant_history_limit', '0');
        $base = $saved > 0 ? $saved : (int) config('assistant.history_limit');

        return max(2, min(20, $base));
    }

    public static function maxClarifications()
    {
        $saved = (int) WhatsAppSetting::getValue('assistant_max_clarifications', '0');
        $base = $saved > 0 ? $saved : (int) config('assistant.max_clarifications');

        return max(1, min(8, $base));
    }
}
