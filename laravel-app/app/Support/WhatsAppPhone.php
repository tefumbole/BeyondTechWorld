<?php

namespace App\Support;

class WhatsAppPhone
{
    public static function countryCode()
    {
        return (string) config('services.whatsapp.default_country_code', '237');
    }

    public static function normalize($number)
    {
        $raw = trim((string) $number);
        if ($raw === '') {
            throw new \InvalidArgumentException('Customer phone number is missing');
        }

        $digits = preg_replace('/\D/', '', $raw);
        $digits = ltrim($digits, '0');

        if ($digits === '') {
            throw new \InvalidArgumentException('Customer phone number is missing');
        }

        $defaultCountryCode = self::countryCode();

        if ($digits === $defaultCountryCode) {
            throw new \InvalidArgumentException(
                'Customer phone number is incomplete. Update the customer with a full WhatsApp number (e.g. 675321739).'
            );
        }

        if (self::looksInternational($digits)) {
            return self::dedupeCountryPrefix($digits, $defaultCountryCode);
        }

        if (strlen($digits) >= 8 && strlen($digits) <= 10) {
            return $defaultCountryCode . $digits;
        }

        throw new \InvalidArgumentException(
            'Invalid phone number "' . $raw . '". Use full mobile number e.g. 675321739, 237675321739, +2348012345678.'
        );
    }

    public static function sanitizeForStorage($number)
    {
        $raw = trim((string) $number);
        if ($raw === '') {
            return '';
        }

        $raw = preg_replace('/\s+/', '', $raw);

        try {
            return self::normalize($raw);
        } catch (\InvalidArgumentException $e) {
            return preg_replace('/\D/', '', $raw);
        }
    }

    public static function forWasender($number)
    {
        return '+' . self::normalize($number);
    }

    public static function display($number)
    {
        try {
            $normalized = self::normalize($number);
        } catch (\InvalidArgumentException $e) {
            return trim((string) $number);
        }

        return '+' . $normalized;
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
        $doublePrefix = $defaultCountryCode . $defaultCountryCode;
        while (strpos($digits, $doublePrefix) === 0) {
            $digits = substr($digits, strlen($defaultCountryCode));
        }

        return $digits;
    }
}
