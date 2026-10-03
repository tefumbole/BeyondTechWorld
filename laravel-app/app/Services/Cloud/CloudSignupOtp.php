<?php

namespace App\Services\Cloud;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * Phone proof for public signup. The code is sent on WhatsApp.
 * Tests do not call the provider.
 */
class CloudSignupOtp
{
    public function send($phone)
    {
        $normalized = app(CloudTrialEligibility::class)->normalizePhone($phone);
        if ($normalized === '') {
            throw new \RuntimeException('Enter a phone number.');
        }
        $countKey = 'cloud-signup-otp-count:'.$normalized;
        $count = (int) Cache::get($countKey, 0);
        if ($count >= 5) {
            throw new \RuntimeException('Too many codes were sent to this phone. Wait and try again.');
        }
        $sentKey = 'cloud-signup-otp-sent:'.$normalized;
        $sentAt = (int) Cache::get($sentKey, 0);
        $elapsed = now()->getTimestamp() - $sentAt;
        if ($sentAt && $elapsed < 120) {
            throw new \RuntimeException('You can request another code in '.(120 - $elapsed).' seconds.');
        }
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put($this->key($normalized), [
            'hash' => Hash::make($code),
            'attempts' => 0,
        ], now()->addMinutes(10));
        Cache::put($countKey, $count + 1, now()->addHour());
        Cache::put($sentKey, now()->getTimestamp(), now()->addMinutes(10));
        if (! app()->environment('testing')) {
            $result = app(\App\Services\Messaging\NotificationRouter::class)
                ->sendWhatsAppOtp($normalized, $code, 'signup', 10);
            if (empty($result['success'])) {
                Cache::forget($this->key($normalized));
                Cache::forget($sentKey);
                throw new \RuntimeException('We could not send the verification code on WhatsApp.');
            }
        }

        return $code;
    }

    public function verify($phone, $code)
    {
        $normalized = app(CloudTrialEligibility::class)->normalizePhone($phone);
        $row = Cache::get($this->key($normalized));
        if (! is_array($row) || empty($row['hash'])) {
            throw new \RuntimeException('Request a new code.');
        }
        $attempts = isset($row['attempts']) ? (int) $row['attempts'] : 0;
        if ($attempts >= 5) {
            Cache::forget($this->key($normalized));
            throw new \RuntimeException('That code is no longer valid. Request a new one.');
        }
        if (! Hash::check(trim((string) $code), $row['hash'])) {
            $row['attempts'] = $attempts + 1;
            Cache::put($this->key($normalized), $row, now()->addMinutes(10));
            throw new \RuntimeException('That code does not match.');
        }
        Cache::forget($this->key($normalized));
        session([
            'cloud_phone_verified' => [
                'phone' => $normalized,
            ],
        ]);

        return $normalized;
    }

    public function matches($phone)
    {
        $verified = session('cloud_phone_verified');
        $normalized = app(CloudTrialEligibility::class)->normalizePhone($phone);
        if (! is_array($verified) || empty($verified['phone']) || $normalized === '') {
            return false;
        }

        return hash_equals((string) $verified['phone'], (string) $normalized);
    }

    protected function key($phone)
    {
        return 'cloud-signup-otp:'.$phone;
    }
}
