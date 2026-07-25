<?php

namespace App\Support;

/**
 * Canonical WhatsApp phone normalization for Beyond Enterprise.
 *
 * Storage / send shape: digits with country code, no spaces/dashes/brackets.
 * Wasender E.164: +237681239720
 *
 * Local Cameroon mobiles (8–10 digits, typically 9 starting with 6) get +237.
 */
class WhatsAppPhone
{
    public static function countryCode()
    {
        $code = preg_replace('/\D/', '', (string) config('services.whatsapp.default_country_code', '237'));

        return $code !== '' ? $code : '237';
    }

    /**
     * Digits-only international number without leading +.
     * e.g. "237681239720"
     */
    public static function normalize($number)
    {
        $raw = trim((string) $number);
        if ($raw === '') {
            throw new \InvalidArgumentException('Phone number is missing');
        }

        // Strip spaces, dashes, brackets, dots, unicode separators, etc.
        $digits = preg_replace('/\D/', '', $raw);
        // Drop a single leading trunk 0 (e.g. 0675321739 → 675321739)
        if (strlen($digits) >= 9 && $digits[0] === '0') {
            $digits = ltrim($digits, '0');
        }

        if ($digits === '') {
            throw new \InvalidArgumentException('Phone number is missing');
        }

        $defaultCountryCode = self::countryCode();

        if ($digits === $defaultCountryCode) {
            throw new \InvalidArgumentException(
                'Phone number is incomplete. Enter a full mobile number (e.g. 675321739 or +237675321739).'
            );
        }

        if (self::looksInternational($digits)) {
            return self::dedupeCountryPrefix($digits, $defaultCountryCode);
        }

        // Local mobile without country code → default Cameroon (+237)
        if (strlen($digits) >= 8 && strlen($digits) <= 10) {
            return $defaultCountryCode.$digits;
        }

        throw new \InvalidArgumentException(
            'Invalid phone number "'.$raw.'". Use e.g. 675321739, 675-321-739, +237 6 81 23 97 20.'
        );
    }

    /**
     * Best-effort normalize for DB storage (never throws).
     * Returns digits with country code, or '' if empty.
     */
    public static function sanitizeForStorage($number)
    {
        $raw = trim((string) $number);
        if ($raw === '') {
            return '';
        }

        try {
            return self::normalize($raw);
        } catch (\InvalidArgumentException $e) {
            $digits = preg_replace('/\D/', '', $raw);
            if ($digits !== '' && $digits[0] === '0') {
                $digits = ltrim($digits, '0');
            }

            return $digits;
        }
    }

    /** E.164 for Wasender / Twilio WhatsApp: +237681239720 */
    public static function forWasender($number)
    {
        return '+'.self::normalize($number);
    }

    /** UI display: +237681239720 */
    public static function display($number)
    {
        $raw = trim((string) $number);
        if ($raw === '') {
            return '';
        }

        try {
            return '+'.self::normalize($raw);
        } catch (\InvalidArgumentException $e) {
            $digits = preg_replace('/\D/', '', $raw);

            return $digits !== '' ? '+'.$digits : $raw;
        }
    }

    /**
     * Combine country-code select + local input into E.164 (+…).
     * If the local part already includes a country code, that wins.
     */
    public static function combine($countryCode, $localNumber)
    {
        $local = trim((string) $localNumber);
        if ($local === '') {
            return '';
        }

        $localDigits = preg_replace('/\D/', '', $local);
        if ($localDigits !== '' && self::looksInternational($localDigits)) {
            return self::forWasender($local);
        }

        $code = preg_replace('/\D/', '', (string) $countryCode);
        if ($code === '') {
            $code = self::countryCode();
        }

        return self::forWasender($code.$localDigits);
    }

    private static function looksInternational($digits)
    {
        if (strlen($digits) < 11) {
            return false;
        }

        $countryCodes = [
            '234', '237', '233', '254', '255', '256', '260', '263', '251', '250', '243', '225',
            '221', '220', '228', '229', '230', '231', '232', '235', '236', '238', '239', '240',
            '241', '242', '244', '245', '246', '248', '249', '252', '253', '257', '258', '261',
            '262', '264', '265', '266', '267', '268', '269', '27', '30', '31', '32', '33', '34',
            '39', '44', '49', '1', '7', '20',
        ];

        usort($countryCodes, function ($a, $b) {
            return strlen($b) - strlen($a);
        });

        foreach ($countryCodes as $countryCode) {
            if (strpos($digits, $countryCode) === 0) {
                $local = substr($digits, strlen($countryCode));
                if (strlen($local) >= 7 && strlen($local) <= 12) {
                    return true;
                }
            }
        }

        return strlen($digits) >= 11 && strlen($digits) <= 15;
    }

    private static function dedupeCountryPrefix($digits, $defaultCountryCode)
    {
        $doublePrefix = $defaultCountryCode.$defaultCountryCode;
        while (strpos($digits, $doublePrefix) === 0) {
            $digits = substr($digits, strlen($defaultCountryCode));
        }

        return $digits;
    }
}
