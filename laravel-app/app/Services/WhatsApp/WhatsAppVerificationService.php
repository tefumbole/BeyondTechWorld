<?php

namespace App\Services\WhatsApp;

use App\WhatsApp\WhatsAppVerificationChallenge;
use App\WhatsApp\WhatsAppVerificationSession;
use Carbon\Carbon;

class WhatsAppVerificationService
{
    public function issue($contactId, $conversationId, $identityType, $identityId, $purpose, $providerMessageId = null)
    {
        $contactId = (int) $contactId;
        $purpose = (string) $purpose;
        $active = $this->activeChallenge($contactId);
        if ($active && $active->created_at && $active->created_at->gt(Carbon::now()->subSeconds($this->cooldownSeconds()))) {
            return ['ok' => false, 'error' => 'cooldown', 'challenge_id' => $active->id];
        }
        if ($this->recentCount($contactId, 1) >= $this->hourlyLimit()) {
            return ['ok' => false, 'error' => 'rate_limited'];
        }
        if ($this->recentCount($contactId, 24) >= $this->dailyLimit()) {
            return ['ok' => false, 'error' => 'rate_limited'];
        }
        WhatsAppVerificationChallenge::where('whatsapp_contact_id', $contactId)
            ->whereNull('verified_at')
            ->whereNull('invalidated_at')
            ->update(['invalidated_at' => Carbon::now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $challenge = WhatsAppVerificationChallenge::create([
            'whatsapp_contact_id' => $contactId,
            'conversation_id' => $conversationId ? (int) $conversationId : null,
            'identity_type' => (string) $identityType,
            'identity_id' => $identityId ? (int) $identityId : null,
            'purpose' => $purpose,
            'otp_hash' => $this->hash($code),
            'expires_at' => Carbon::now()->addMinutes($this->ttlMinutes()),
            'attempts' => 0,
            'max_attempts' => $this->maxAttempts(),
            'provider_message_id' => $providerMessageId ? (string) $providerMessageId : null,
        ]);

        return [
            'ok' => true,
            'code' => $code,
            'challenge_id' => $challenge->id,
            'ttl_minutes' => $this->ttlMinutes(),
        ];
    }

    public function verify($contactId, $code)
    {
        $challenge = $this->activeChallenge((int) $contactId);
        if (! $challenge) {
            $used = WhatsAppVerificationChallenge::where('whatsapp_contact_id', (int) $contactId)
                ->whereNotNull('verified_at')
                ->orderByDesc('id')
                ->first();
            if ($used && hash_equals((string) $used->otp_hash, $this->hash($this->digits($code)))) {
                return ['ok' => false, 'error' => 'replay'];
            }

            return ['ok' => false, 'error' => 'missing'];
        }
        if ($challenge->expires_at && $challenge->expires_at->lte(Carbon::now())) {
            $challenge->invalidated_at = Carbon::now();
            $challenge->save();

            return ['ok' => false, 'error' => 'expired'];
        }
        $digits = $this->digits($code);
        if ($digits === '' || ! hash_equals((string) $challenge->otp_hash, $this->hash($digits))) {
            $challenge->attempts = (int) $challenge->attempts + 1;
            if ($challenge->attempts >= (int) $challenge->max_attempts) {
                $challenge->invalidated_at = Carbon::now();
            }
            $challenge->save();

            return ['ok' => false, 'error' => $challenge->invalidated_at ? 'locked' : 'mismatch', 'attempts' => (int) $challenge->attempts];
        }
        $challenge->verified_at = Carbon::now();
        $challenge->invalidated_at = Carbon::now();
        $challenge->save();
        $session = WhatsAppVerificationSession::create([
            'whatsapp_contact_id' => $challenge->whatsapp_contact_id,
            'conversation_id' => $challenge->conversation_id,
            'challenge_id' => $challenge->id,
            'identity_type' => $challenge->identity_type,
            'identity_id' => $challenge->identity_id,
            'purpose' => $challenge->purpose,
            'method' => 'otp',
            'verified_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addMinutes($this->sessionMinutes()),
        ]);

        return ['ok' => true, 'session' => $session, 'challenge' => $challenge];
    }

    public function activeSession($contactId, $identityType, $identityId, $purpose)
    {
        $session = WhatsAppVerificationSession::where('whatsapp_contact_id', (int) $contactId)
            ->where('identity_type', (string) $identityType)
            ->where('identity_id', (int) $identityId)
            ->where('purpose', (string) $purpose)
            ->whereNull('invalidated_at')
            ->where('expires_at', '>', Carbon::now())
            ->orderByDesc('id')
            ->first();

        return $session && $session->isActive() ? $session : null;
    }

    public function invalidateChallenge($id)
    {
        $row = WhatsAppVerificationChallenge::find($id);
        if ($row && ! $row->invalidated_at) {
            $row->invalidated_at = Carbon::now();
            $row->save();
        }

        return $row;
    }

    public function invalidateContact($contactId)
    {
        $now = Carbon::now();
        WhatsAppVerificationChallenge::where('whatsapp_contact_id', (int) $contactId)
            ->whereNull('invalidated_at')
            ->update(['invalidated_at' => $now]);
        WhatsAppVerificationSession::where('whatsapp_contact_id', (int) $contactId)
            ->whereNull('invalidated_at')
            ->update(['invalidated_at' => $now]);
    }

    public function activeChallenge($contactId)
    {
        return WhatsAppVerificationChallenge::where('whatsapp_contact_id', (int) $contactId)
            ->whereNull('verified_at')
            ->whereNull('invalidated_at')
            ->orderByDesc('id')
            ->first();
    }

    public function hash($code)
    {
        return hash_hmac('sha256', (string) $code, (string) config('app.key'));
    }

    public function ttlMinutes()
    {
        return max(1, (int) config('services.whatsapp.otp_ttl_minutes', 5));
    }

    protected function digits($code)
    {
        if (preg_match('/(\d{6})/', (string) $code, $m)) {
            return $m[1];
        }

        return '';
    }

    protected function recentCount($contactId, $hours)
    {
        return WhatsAppVerificationChallenge::where('whatsapp_contact_id', (int) $contactId)
            ->where('created_at', '>=', Carbon::now()->subHours($hours))
            ->count();
    }

    protected function cooldownSeconds()
    {
        return max(10, (int) config('services.whatsapp.otp_resend_cooldown_seconds', 60));
    }

    protected function hourlyLimit()
    {
        return max(1, (int) config('services.whatsapp.otp_hourly_limit', 5));
    }

    protected function dailyLimit()
    {
        return max(1, (int) config('services.whatsapp.otp_daily_limit', 10));
    }

    protected function maxAttempts()
    {
        return max(1, (int) config('services.whatsapp.otp_max_attempts', 5));
    }

    protected function sessionMinutes()
    {
        return max(1, (int) config('services.whatsapp.verification_session_minutes', 20));
    }
}
