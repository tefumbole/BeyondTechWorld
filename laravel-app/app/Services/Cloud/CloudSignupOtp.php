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
        if ($count >= 15) {
            throw new \RuntimeException('Too many codes were sent to this phone. Wait and try again.');
        }
        $sentKey = 'cloud-signup-otp-sent:'.$normalized;
        $pending = Cache::get($this->key($normalized));
        $sentAt = (int) Cache::get($sentKey, 0);
        $elapsed = now()->getTimestamp() - $sentAt;
        if (is_array($pending) && ! empty($pending['hash']) && $sentAt && $elapsed < 20) {
            throw new \RuntimeException('You can request another code in '.(20 - $elapsed).' seconds.');
        }
        $code = \App\Support\MemorableOtp::code();
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
                Cache::put($countKey, $count, now()->addHour());
                $shown = \App\Support\WhatsAppPhone::display($normalized);
                throw new \RuntimeException('We could not send the verification code to '.($shown !== '' ? $shown : 'this number').'.');
            }
        }

        return $code;
    }

    public function verify($phone, $code, $once = false)
    {
        $normalized = app(CloudTrialEligibility::class)->normalizePhone($phone);
        $row = Cache::get($this->key($normalized));
        if (! is_array($row) || empty($row['hash'])) {
            if ($once && $this->matches($normalized)) {
                return $normalized;
            }
            throw new \RuntimeException('Request a new code.');
        }
        $attempts = isset($row['attempts']) ? (int) $row['attempts'] : 0;
        if ($attempts >= 5) {
            Cache::forget($this->key($normalized));
            throw new \RuntimeException('That code is no longer valid. Request a new one.');
        }
        $digits = preg_replace('/\D+/', '', (string) $code);
        if (! Hash::check($digits, $row['hash'])) {
            $row['attempts'] = $attempts + 1;
            Cache::put($this->key($normalized), $row, now()->addMinutes(10));
            throw new \RuntimeException('That code does not match.');
        }
        Cache::forget($this->key($normalized));
        Cache::forget('cloud-signup-otp-sent:'.$normalized);
        session([
            'cloud_phone_verified' => [
                'phone' => $normalized,
            ],
        ]);

        return $normalized;
    }

    /**
     * Sent only after signup is finished. The link signs the person in.
     * It does not connect WhatsApp.
     */
    public function sendLoginLink($user, $phone)
    {
        $normalized = preg_replace('/\D/', '', (string) $phone);
        if (! preg_match('/^[1-9][0-9]{7,14}$/', $normalized) || \App\Services\Mai\MaiBusinessLine::is($normalized)) {
            return;
        }
        if (app()->environment('testing')) {
            session(['mai_phone_link_sent' => $normalized]);

            return;
        }
        $key = 'mai-login-link:'.$normalized;
        if (! \Cache::add($key, 1, now()->addHours(6))) {
            return;
        }
        $url = \URL::temporarySignedRoute('whatsapp.mai.enter', now()->addDays(14), ['user' => $user->id]);
        $body = "Whats MAI\nTap to sign in:\n".$url;
        try {
            $result = app(\App\Services\Messaging\NotificationRouter::class)->sendWhatsAppTextWithLink($normalized, $body);
        } catch (\Exception $e) {
            \Cache::forget($key);

            return;
        }
        if (empty($result['success'])) {
            \Cache::forget($key);

            return;
        }
        session(['mai_phone_link_sent' => $normalized]);
    }

    public function sendAppLinkOnce($phone)
    {
        $normalized = app(CloudTrialEligibility::class)->normalizePhone($phone);
        if ($normalized === '' || session('mai_phone_link_sent') === $normalized) {
            return;
        }
        $this->sendAppLink($normalized);
        session(['mai_phone_link_sent' => $normalized]);
    }

    public function sendAppLink($phone)
    {
        if (app()->environment('testing')) {
            return;
        }
        $normalized = app(CloudTrialEligibility::class)->normalizePhone($phone);
        if ($normalized === '') {
            return;
        }
        $user = app(\App\Services\Cloud\CloudOnboardingService::class)->accountForPhone($normalized);
        $url = $user
            ? \URL::temporarySignedRoute('whatsapp.mai.enter', now()->addDays(14), ['user' => $user->id])
            : url('/whatsapp-mai');
        $body = "Whats MAI\nOpen Whats MAI:\n".$url;
        try {
            app(\App\Services\Messaging\NotificationRouter::class)->sendWhatsAppTextWithLink($normalized, $body);
        } catch (\Exception $e) {
        }
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
