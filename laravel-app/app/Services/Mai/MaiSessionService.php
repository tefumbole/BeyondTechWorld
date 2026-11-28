<?php

namespace App\Services\Mai;

use App\Mai\MaiApiKey;
use App\Mai\MaiSession;
use App\User;

class MaiSessionService
{
    protected $link;

    public function __construct(MaiLinkClient $link)
    {
        $this->link = $link;
    }

    public function open(User $user, $phone, $requestedProvider = '')
    {
        $this->ready();
        app(MaiProviderRouter::class)->assertCustomerProvider($requestedProvider);
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (! preg_match('/^[1-9][0-9]{7,14}$/', $digits)) {
            throw new \InvalidArgumentException('Enter the WhatsApp number you will link.');
        }
        if (MaiBusinessLine::is($digits)) {
            throw new \InvalidArgumentException('That number is the business line and cannot be linked here.');
        }
        if (! MaiPilot::allows($digits)) {
            throw new \InvalidArgumentException('This WhatsApp is not in the current MAI pilot.');
        }
        $limit = config('cloud.mai_customer.session_limit');
        if ($limit !== null && $limit !== '' && (int) $limit > 0) {
            $openSessions = MaiSession::where('user_id', $user->id)->where('status', '!=', 'UNLINKED')->count();
            if ($openSessions >= (int) $limit) {
                throw new \InvalidArgumentException('This plan has no free session left.');
            }
        }
        $session = new MaiSession();
        $session->public_id = 'c'.bin2hex(random_bytes(16));
        $session->user_id = $user->id;
        $session->provider = MaiProviderRouter::MAI;
        $session->worker_session_id = 's'.bin2hex(random_bytes(16));
        $session->expected_phone = $digits;
        $session->status = 'PENDING';
        $session->attempt_id = 'a'.bin2hex(random_bytes(8));
        $session->pairing_expires_at = now()->addMinutes(3);
        $session->ai_enabled = 0;
        $session->save();
        $state = $this->link->open($session->worker_session_id, $digits);
        $this->apply($session, is_array($state) ? $state : [], $session->attempt_id);

        return $session->fresh();
    }

    public function pair(MaiSession $session)
    {
        $this->ready();
        $this->replaceAttempt($session);
        $state = $this->link->pair($session->worker_session_id);
        if (is_array($state) && ! empty($state['pairing_code'])) {
            $session->pairing_code = substr((string) $state['pairing_code'], 0, 32);
            $session->save();
        }
        $this->apply($session, is_array($state) ? $state : [], $session->attempt_id);

        return $session->fresh();
    }

    public function refresh(MaiSession $session, $attemptId = null)
    {
        $state = $this->link->status($session->worker_session_id);
        $this->apply($session, is_array($state) ? $state : [], $attemptId ?: $session->attempt_id);

        return $session->fresh();
    }

    public function disconnect(MaiSession $session)
    {
        $this->link->disconnect($session->worker_session_id);
        $session->status = 'UNLINKED';
        $session->pairing_code = null;
        $session->unlinked_at = now();
        $session->last_error = 'Link the phone again to send.';
        $session->save();

        return $session->fresh();
    }

    public function apply(MaiSession $session, array $state, $attemptId)
    {
        if ($attemptId !== null && (string) $attemptId !== (string) $session->attempt_id) {
            return $session;
        }
        $reported = isset($state['status']) ? strtoupper((string) $state['status']) : '';
        $phone = isset($state['phone']) ? preg_replace('/\D+/', '', (string) $state['phone']) : '';
        if ($reported === 'CONNECTED') {
            if (! $session->pairingOpen()) {
                $session->status = 'EXPIRED';
                $session->pairing_code = null;
                $session->last_error = 'That link expired. Start again.';
                $session->save();
                $this->link->disconnect($session->worker_session_id);

                return $session;
            }
            if ($phone === '' || $phone !== (string) $session->expected_phone) {
                $session->status = 'WRONG_NUMBER';
                $session->pairing_code = null;
                $session->last_error = 'The linked WhatsApp is a different number.';
                $session->save();
                $this->link->disconnect($session->worker_session_id);

                return $session;
            }
            if (MaiBusinessLine::is($phone)) {
                $session->status = 'WRONG_NUMBER';
                $session->last_error = 'That number is the business line.';
                $session->save();
                $this->link->disconnect($session->worker_session_id);

                return $session;
            }
            $session->status = 'CONNECTED';
            $session->phone_number = $phone;
            $session->pairing_code = null;
            $session->connected_at = $session->connected_at ?: now();
            $session->last_error = null;
            $session->save();

            return $session;
        }
        if ($reported === 'UNLINKED' || $reported === 'DISCONNECTED') {
            $session->status = 'UNLINKED';
            $session->unlinked_at = now();
            $session->pairing_code = null;
            $session->last_error = 'Link the phone again to send.';
            $session->save();

            return $session;
        }
        if ($reported === 'AWAITING_QR' || $reported === 'PENDING') {
            $session->status = 'AWAITING_QR';
            if (! empty($state['pairing_code'])) {
                $session->pairing_code = substr((string) $state['pairing_code'], 0, 32);
            }
            $session->save();
        } elseif (! empty($state['error'])) {
            $session->last_error = substr((string) $state['error'], 0, 191);
            $session->save();
        }

        return $session;
    }

    public function issueKey(User $user, $name, MaiSession $session = null, $scopes = null)
    {
        $this->ready();
        $plain = 'mai_'.bin2hex(random_bytes(20));
        $key = new MaiApiKey();
        $key->user_id = $user->id;
        $key->name = substr(trim((string) $name) !== '' ? trim((string) $name) : 'Whats MAI', 0, 80);
        $key->prefix = substr($plain, 0, 12);
        $key->key_hash = hash('sha256', $plain);
        $allowed = ['sessions', 'messages', 'webhooks'];
        $chosen = [];
        foreach (array_filter(array_map('trim', explode(',', (string) ($scopes !== null ? $scopes : 'sessions,messages,webhooks')))) as $scope) {
            if (in_array($scope, $allowed, true)) {
                $chosen[] = $scope;
            }
        }
        $key->scopes = $chosen ? implode(',', $chosen) : 'messages';
        if ($session && \Illuminate\Support\Facades\Schema::hasColumn('mai_api_keys', 'mai_session_id')) {
            $key->mai_session_id = $session->id;
            $key->cloud_tenant_id = $session->cloud_tenant_id;
        }
        $key->save();

        return ['key' => $key, 'plain' => $plain];
    }

    public function rotateKey(User $user, MaiApiKey $key)
    {
        if ((int) $key->user_id !== (int) $user->id || $key->revoked_at) {
            return null;
        }
        $session = null;
        if (! empty($key->mai_session_id)) {
            $session = MaiSession::find($key->mai_session_id);
        }
        $issued = $this->issueKey($user, $key->name, $session, $key->scopes);
        $this->revokeKey($user, $key);

        return $issued;
    }

    public function revokeKey(User $user, MaiApiKey $key)
    {
        if ((int) $key->user_id !== (int) $user->id) {
            return false;
        }
        $key->revoked_at = now();
        $key->save();

        return true;
    }

    public function present(MaiSession $session, $qr = null)
    {
        return [
            'id' => $session->public_id,
            'provider' => MaiProviderRouter::MAI,
            'status' => $session->status,
            'phone' => $session->phone_number,
            'expected_phone' => $session->expected_phone,
            'pairing_code' => $session->status === 'CONNECTED' ? null : $session->pairing_code,
            'qr' => $session->status === 'AWAITING_QR' ? $qr : null,
            'ai_enabled' => (bool) $session->ai_enabled,
            'error' => $session->last_error,
            'capabilities' => MaiCapabilities::matrix(),
        ];
    }

    protected function replaceAttempt(MaiSession $session)
    {
        $session->attempt_id = 'a'.bin2hex(random_bytes(8));
        $session->pairing_expires_at = now()->addMinutes(3);
        $session->pairing_code = null;
        $session->save();
    }

    protected function ready()
    {
        if (! config('cloud.mai_customer.enabled')) {
            throw new \RuntimeException('Whats MAI customer signup is not open.');
        }
    }
}
