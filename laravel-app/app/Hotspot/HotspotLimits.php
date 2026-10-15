<?php

namespace App\Hotspot;

/**
 * Pure hotspot rules: speeds, durations, and when a payment may turn access on.
 */
class HotspotLimits
{
    public static function rateLimit($uploadKbps, $downloadKbps)
    {
        $uploadOpen = self::unlimited($uploadKbps);
        $downloadOpen = self::unlimited($downloadKbps);
        if ($uploadOpen && $downloadOpen) {
            return '';
        }

        return ($uploadOpen ? '0' : self::bits($uploadKbps)).'/'.($downloadOpen ? '0' : self::bits($downloadKbps));
    }

    public static function speedLabel($uploadKbps, $downloadKbps)
    {
        $uploadOpen = self::unlimited($uploadKbps);
        $downloadOpen = self::unlimited($downloadKbps);
        if ($uploadOpen && $downloadOpen) {
            return 'Unlimited speed';
        }
        $upload = $uploadOpen ? 'Unlimited' : self::bits($uploadKbps);
        $download = $downloadOpen ? 'Unlimited' : self::bits($downloadKbps);

        return $upload.' up / '.$download.' down';
    }

    public static function unlimited($kbps)
    {
        return $kbps === null || $kbps === '' || (int) $kbps === 0;
    }

    public static function bits($kbps)
    {
        $kbps = (int) $kbps;
        if ($kbps <= 0) {
            return '0';
        }
        if ($kbps % 1024 === 0) {
            return ($kbps / 1024).'M';
        }

        return $kbps.'k';
    }

    public static function duration($seconds)
    {
        $seconds = max(0, (int) $seconds);
        $weeks = intdiv($seconds, 604800);
        $seconds %= 604800;
        $days = intdiv($seconds, 86400);
        $seconds %= 86400;
        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds, 60);
        $remain = $seconds % 60;
        $out = '';
        if ($weeks) {
            $out .= $weeks.'w';
        }
        if ($days) {
            $out .= $days.'d';
        }
        if ($hours) {
            $out .= $hours.'h';
        }
        if ($minutes) {
            $out .= $minutes.'m';
        }
        if ($remain || $out === '') {
            $out .= $remain.'s';
        }

        return $out;
    }

    public static function humanDuration($seconds)
    {
        $seconds = (int) $seconds;
        if ($seconds % 2592000 === 0 && $seconds >= 2592000) {
            $months = (int) ($seconds / 2592000);

            return $months.' '.($months === 1 ? 'month' : 'months');
        }
        if ($seconds % 604800 === 0 && $seconds >= 604800) {
            $weeks = (int) ($seconds / 604800);

            return $weeks.' '.($weeks === 1 ? 'week' : 'weeks');
        }
        if ($seconds % 86400 === 0 && $seconds >= 86400) {
            $days = (int) ($seconds / 86400);

            return $days.' '.($days === 1 ? 'day' : 'days');
        }
        if ($seconds % 3600 === 0 && $seconds >= 3600) {
            $hours = (int) ($seconds / 3600);

            return $hours.' '.($hours === 1 ? 'hour' : 'hours');
        }
        $minutes = max(1, (int) round($seconds / 60));

        return $minutes.' '.($minutes === 1 ? 'minute' : 'minutes');
    }

    public static function humanBytes($bytes)
    {
        $bytes = (int) $bytes;
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1).' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    public static function uptimeSeconds($value)
    {
        if (! preg_match_all('/(\d+)([wdhms])/', (string) $value, $matches, PREG_SET_ORDER)) {
            return 0;
        }
        $map = ['w' => 604800, 'd' => 86400, 'h' => 3600, 'm' => 60, 's' => 1];
        $total = 0;
        foreach ($matches as $part) {
            $total += (int) $part[1] * $map[$part[2]];
        }

        return $total;
    }

    /**
     * A browser redirect never grants access. Only a confirmed provider result does.
     */
    public static function mayActivate($providerConfirmed, $browserStatus = null)
    {
        return $providerConfirmed === true;
    }

    public static function endReason($expiresAt, $now, $dataUsed, $dataCap)
    {
        if ($expiresAt && $expiresAt <= $now) {
            return 'time';
        }
        if ($dataCap && (int) $dataUsed >= (int) $dataCap) {
            return 'data';
        }

        return null;
    }

    public static function acceptsManagementHost($host)
    {
        $host = trim((string) $host);
        if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        if ($host === '127.0.0.1') {
            return true;
        }

        return filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }

    public static function voucherCode()
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < 10; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }

    public static function profileName($packageId)
    {
        return 'btw-'.(int) $packageId;
    }
}
