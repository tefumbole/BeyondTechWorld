<?php

namespace App\Support;

use App\Services\Messaging\NotificationRouter;
use App\Services\TwilioWhatsAppService;

/**
 * Copies of sent WhatsApp messages go to the super admin and to the staff
 * member who created the sale, quotation, grade, or other service.
 * Bulk announcements and reminders send one summary instead of one copy each.
 */
class TwilioAdminCopy
{
    const PHONE = '+237675321739';

    /** Customer and intern accounts are recipients, not staff creators. */
    const SKIP_ROLE_IDS = [5, 14];

    protected static $depth = 0;

    protected static $held = 0;

    protected static $extraPhones = [];

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
        return self::samePhone($phone, self::PHONE);
    }

    /**
     * Keep a staff creator on the copy list for the rest of this request.
     */
    public static function rememberUser($userId)
    {
        $phone = self::staffPhoneForUserId($userId);
        if ($phone === '') {
            return;
        }
        self::$extraPhones[$phone] = $phone;
    }

    public static function actingStaffPhone()
    {
        try {
            if (! \Illuminate\Support\Facades\Auth::check()) {
                return '';
            }
            $user = \Illuminate\Support\Facades\Auth::user();
        } catch (\Throwable $e) {
            return '';
        }
        if (! $user) {
            return '';
        }

        return self::staffPhoneForUser($user);
    }

    /**
     * Same approved template, sent to the super admin and the staff creator.
     */
    public static function mirrorTemplate($to, $contentSid, array $variables = [], $mediaUrl = null)
    {
        if (self::holdsCopies()) {
            return;
        }
        $recipients = self::copyRecipients($to);
        if ($recipients === []) {
            return;
        }

        self::$depth++;
        try {
            $twilio = app(TwilioWhatsAppService::class);
            foreach ($recipients as $phone) {
                try {
                    $twilio->sendContentTemplate($phone, $contentSid, $variables, $mediaUrl);
                } catch (\Throwable $e) {
                    \Log::warning('[twilio-admin-copy] template copy failed: '.$e->getMessage());
                }
            }
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
        if ($message === '' || self::holdsCopies()) {
            return;
        }
        $recipients = self::copyRecipients($to);
        if ($recipients === []) {
            return;
        }

        self::$depth++;
        try {
            $router = app(NotificationRouter::class);
            $text = self::clip("Copy of a message sent to ".$to.":\n\n".$message);
            foreach ($recipients as $phone) {
                try {
                    $router->sendWhatsAppText($phone, $text);
                } catch (\Throwable $e) {
                    \Log::warning('[twilio-admin-copy] text copy failed: '.$e->getMessage());
                }
            }
        } finally {
            self::$depth--;
        }
    }

    /**
     * One summary for a bulk announcement or reminder, to admin and the creator.
     */
    public static function sendSummary($text)
    {
        $text = self::clip($text);
        if ($text === '') {
            return false;
        }
        $ok = false;
        self::$depth++;
        try {
            $router = app(NotificationRouter::class);
            foreach (self::copyRecipients('') as $phone) {
                try {
                    $sent = $router->sendWhatsAppText($phone, $text);
                    $ok = $ok || ! empty($sent['success']);
                } catch (\Throwable $e) {
                    \Log::warning('[twilio-admin-copy] summary failed: '.$e->getMessage());
                }
            }
        } finally {
            self::$depth--;
        }

        return $ok;
    }

    protected static function copyRecipients($originalTo)
    {
        $phones = [self::PHONE => self::PHONE];
        foreach (self::$extraPhones as $phone) {
            $phones[$phone] = $phone;
        }
        $actor = self::actingStaffPhone();
        if ($actor !== '') {
            $phones[$actor] = $actor;
        }

        $out = [];
        $seen = [];
        foreach ($phones as $phone) {
            if (self::samePhone($phone, $originalTo)) {
                continue;
            }
            $digits = preg_replace('/\D+/', '', (string) $phone);
            if ($digits === '' || isset($seen[$digits])) {
                continue;
            }
            $seen[$digits] = true;
            $out[] = $phone;
        }

        return $out;
    }

    protected static function staffPhoneForUserId($userId)
    {
        $userId = (int) $userId;
        if ($userId < 1) {
            return '';
        }
        try {
            $user = \App\User::find($userId);
        } catch (\Throwable $e) {
            return '';
        }

        return $user ? self::staffPhoneForUser($user) : '';
    }

    protected static function staffPhoneForUser($user)
    {
        if (in_array((int) $user->role_id, self::SKIP_ROLE_IDS, true)) {
            return '';
        }
        if (method_exists($user, 'whatsappPhone')) {
            return trim((string) $user->whatsappPhone());
        }

        return trim((string) ($user->phone ?? ''));
    }

    protected static function samePhone($left, $right)
    {
        $a = preg_replace('/\D+/', '', (string) $left);
        $b = preg_replace('/\D+/', '', (string) $right);
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        $short = strlen($a) < strlen($b) ? $a : $b;
        $long = strlen($a) < strlen($b) ? $b : $a;

        return strlen($short) >= 8 && substr($long, -strlen($short)) === $short;
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
