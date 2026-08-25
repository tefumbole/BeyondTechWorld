<?php

namespace App\Support;

class InternshipReportQr
{
    public static function token(array $data): string
    {
        $payload = [
            'n' => (string) ($data['name'] ?? ''),
            'd' => (string) ($data['duration'] ?? ''),
            'm' => (string) ($data['matricule'] ?? ''),
            'v' => 1,
        ];
        $raw = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $sig = substr(hash_hmac('sha256', $raw, self::secret()), 0, 24);

        return $raw.'.'.$sig;
    }

    public static function url(array $data): string
    {
        return url('/verify/internship/'.self::token($data));
    }

    public static function decode($token): ?array
    {
        $token = (string) $token;
        $pos = strrpos($token, '.');
        if ($pos === false) {
            return null;
        }
        $raw = substr($token, 0, $pos);
        $sig = substr($token, $pos + 1);
        $expect = substr(hash_hmac('sha256', $raw, self::secret()), 0, 24);
        if (! hash_equals($expect, $sig)) {
            return null;
        }
        $pad = strlen($raw) % 4;
        if ($pad) {
            $raw .= str_repeat('=', 4 - $pad);
        }
        $json = json_decode(base64_decode(strtr($raw, '-_', '+/')), true);
        if (! is_array($json) || empty($json['n'])) {
            return null;
        }

        return [
            'name' => $json['n'],
            'duration' => $json['d'] ?? '',
            'matricule' => $json['m'] ?? '',
            'status' => 'Valid',
        ];
    }

    protected static function secret(): string
    {
        return (string) config('app.key');
    }
}
