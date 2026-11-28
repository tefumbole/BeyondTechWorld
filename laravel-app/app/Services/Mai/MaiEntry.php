<?php

namespace App\Services\Mai;

use App\Mai\MaiSession;
use App\Services\Cloud\MaiWhatsAppEngine;
use App\User;
use App\WhatsApp\WhatsAppConversation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * One decision for every customer entry: inbox, resume linking, or sign in.
 * Reading status does not start a second pairing attempt.
 */
class MaiEntry
{
    public function kind($user = null)
    {
        $user = $user ?: Auth::user();
        if (! $user) {
            return 'signin';
        }
        if (MaiBusinessLine::is($user->phone)) {
            return 'staff';
        }
        $phone = $this->phone($user);
        if ($phone === '') {
            return 'link';
        }
        $state = app(MaiWhatsAppEngine::class)->status($phone, false);
        $status = isset($state['status']) ? (string) $state['status'] : 'NOT_CONNECTED';
        if ($this->connected($phone, $state)) {
            return 'inbox';
        }
        if (in_array($status, ['CONNECTING', 'DISCONNECTED'], true) && $this->remembered($user)) {
            return 'reconnect';
        }

        return 'link';
    }

    public function connected($expected, array $state)
    {
        if (($state['status'] ?? '') !== 'CONNECTED') {
            return false;
        }
        $phone = preg_replace('/\D/', '', (string) (isset($state['phone']) ? $state['phone'] : ''));
        if ($phone === '' || substr($phone, -8) !== substr((string) $expected, -8)) {
            return false;
        }
        if (MaiBusinessLine::is($phone)) {
            return false;
        }

        return true;
    }

    public function phone($user)
    {
        $digits = preg_replace('/\D/', '', (string) $user->phone);
        if (preg_match('/^[1-9][0-9]{7,14}$/', $digits)) {
            return $digits;
        }
        $asked = preg_replace('/\D/', '', (string) session('mai_link_phone'));
        if (preg_match('/^[1-9][0-9]{7,14}$/', $asked) && ! MaiBusinessLine::is($asked)) {
            return $asked;
        }

        return '';
    }

    protected function remembered($user)
    {
        if (! Schema::hasTable('mai_sessions')) {
            return false;
        }
        $session = MaiSession::where('user_id', $user->id)->where('status', '!=', 'UNLINKED')->orderByDesc('id')->first();
        if (! $session) {
            return false;
        }
        if ($session->status === 'CONNECTED') {
            return true;
        }
        if (! Schema::hasTable('whatsapp_conversations') || ! Schema::hasColumn('whatsapp_conversations', 'mai_session_id')) {
            return false;
        }

        return WhatsAppConversation::where('mai_session_id', $session->id)->exists();
    }
}
