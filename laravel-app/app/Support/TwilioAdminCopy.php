<?php

namespace App\Support;

use App\Services\Messaging\NotificationRouter;
use App\Services\TwilioWhatsAppService;

/**
 * One admin number receives a copy of each Twilio WhatsApp message.
 * Bulk announcements and reminders send a single summary instead.
 */
class TwilioAdminCopy
{
    const PHONE = '+237675321739';

    protected static $depth = 0;

    protected static $held = 0;

    public static function holdsCopies()
    {
        return self::$depth > 0 || self::$held > 0;
    }

    public static function hold()
    {
        self::$held++;
    }

    public static function release()
    {
        self::$held = max(0, self::$held - 1);
    }

    public static function isAdmin($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        $admin = preg_replace('/\D+/', '', self::PHONE);

        return $digits !== '' && ($digits === $admin || substr($digits, -strlen($admin)) === $admin);
    }

    /**
     * Same approved template, sent once to the admin number.
     */
    public static function mirrorTemplate($to, $contentSid, array $variables = [], $mediaUrl = null)
    {
        if (self::holdsCopies() || self::isAdmin($to)) {
            return;
        }

        self::$depth++;
        try {
            app(TwilioWhatsAppService::class)->sendContentTemplate(self::PHONE, $contentSid, $variables, $mediaUrl);
        } catch (\Throwable $e) {
            \Log::warning('[twilio-admin-copy] template copy failed: '.$e->getMessage());
        } finally {
            self::$depth--;
        }
    }

    /**
     * Copy of a message that could not use an approved Twilio template.
     */
    public static function copyText($to, $message)
    {
        $message = trim((string) $message);
        if ($message === '' || self::holdsCopies() || self::isAdmin($to)) {
            return;
        }

        self::$depth++;
        try {
            app(NotificationRouter::class)->sendWhatsAppText(
                self::PHONE,
                self::clip("Copy of a message sent to ".$to.":\n\n".$message)
            );
        } catch (\Throwable $e) {
            \Log::warning('[twilio-admin-copy] text copy failed: '.$e->getMessage());
        } finally {
            self::$depth--;
        }
    }

    public static function clip($text, $max = 1400)
    {
        $text = trim((string) $text);
        if (function_exists('mb_strlen') && mb_strlen($text) > $max) {
            return rtrim(mb_substr($text, 0, $max - 1)).'…';
        }
        if (strlen($text) > $max) {
            return rtrim(substr($text, 0, $max - 1)).'…';
        }

        return $text;
    }
}
