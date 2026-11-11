<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * OTP hashes only. The code itself is never written to the database.
 */
class AntivirusOtp
{
    public static function issue($purpose, $subject)
    {
        $code = MemorableOtp::code();
        Cache::put(self::key($purpose, $subject), [
            'hash' => hash('sha256', $code),
            'tries' => 0,
        ], now()->addMinutes(10));

        return $code;
    }

    public static function check($purpose, $subject, $typed)
    {
        $key = self::key($purpose, $subject);
        $row = Cache::get($key);
        if (! is_array($row) || empty($row['hash'])) {
            return 'expired';
        }
        $tries = (int) (isset($row['tries']) ? $row['tries'] : 0);
        if ($tries >= 5) {
            Cache::forget($key);

            return 'locked';
        }
        $digits = preg_replace('/\D+/', '', (string) $typed);
        if (strlen($digits) !== 6 || ! hash_equals((string) $row['hash'], hash('sha256', $digits))) {
            $row['tries'] = $tries + 1;
            Cache::put($key, $row, now()->addMinutes(10));

            return 'mismatch';
        }
        Cache::forget($key);

        return 'ok';
    }

    public static function forget($purpose, $subject)
    {
        Cache::forget(self::key($purpose, $subject));
    }

    public static function message($result)
    {
        if ($result === 'expired') {
            return 'That code has expired. Request a new one.';
        }
        if ($result === 'locked') {
            return 'Too many tries. Request a new code.';
        }

        return 'That code does not match.';
    }

    protected static function key($purpose, $subject)
    {
        return 'antivirus-otp:'.sha1((string) $purpose.'|'.(string) $subject);
    }
}
