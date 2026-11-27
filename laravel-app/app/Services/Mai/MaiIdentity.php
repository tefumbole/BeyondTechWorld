<?php

namespace App\Services\Mai;

use App\Mai\MaiPeer;
use App\Mai\MaiSession;
use Illuminate\Support\Facades\Schema;

/**
 * A display name belongs to one linked WhatsApp connection.
 * The shared contact row and the business address book are not used here.
 */
class MaiIdentity
{
    public function remember(MaiSession $session, array $row)
    {
        if (! Schema::hasTable('mai_peers')) {
            return null;
        }
        $jid = $this->jid($row);
        if ($jid === '') {
            return null;
        }
        $peer = MaiPeer::where('mai_session_id', $session->id)->where('remote_jid', $jid)->first();
        if (! $peer) {
            $peer = new MaiPeer();
            $peer->mai_session_id = $session->id;
            $peer->remote_jid = $jid;
        }
        $saved = $this->clip(isset($row['saved_name']) ? $row['saved_name'] : '');
        $profile = $this->clip(isset($row['profile_name']) ? $row['profile_name'] : '');
        if ($saved !== '') {
            $current = trim((string) $peer->saved_name);
            if ($current === '' || strlen($saved) >= strlen($current)) {
                $peer->saved_name = $saved;
            }
        }
        if ($profile !== '' && $profile !== $saved) {
            $peer->profile_name = $profile;
        }
        $phone = $this->phoneFromRow($row);
        if ($phone !== '') {
            $peer->phone_digits = $phone;
        }
        $lid = isset($row['lid']) ? preg_replace('/\D/', '', (string) $row['lid']) : '';
        if ($lid !== '' && $lid !== $phone) {
            $peer->lid = substr($lid, 0, 64);
        }
        $peer->save();
        $this->attachLid($session, $peer);

        return $peer;
    }

    public function display($conversation, $contact = null)
    {
        if (! $conversation || ! $conversation->mai_session_id) {
            return 'WhatsApp';
        }
        $peer = $this->peerFor($conversation, $contact);
        if ($peer && trim((string) $peer->saved_name) !== '') {
            return trim((string) $peer->saved_name);
        }
        if ($peer && trim((string) $peer->profile_name) !== '') {
            return trim((string) $peer->profile_name);
        }
        $phone = $peer ? preg_replace('/\D/', '', (string) $peer->phone_digits) : '';
        if ($phone !== '' && strlen($phone) >= 8 && strlen($phone) <= 13) {
            return '+'.$phone;
        }

        return 'WhatsApp';
    }

    public function trusted($conversation, $contact = null)
    {
        $peer = $this->peerFor($conversation, $contact);
        if (! $peer) {
            return '';
        }
        if (trim((string) $peer->saved_name) !== '') {
            return trim((string) $peer->saved_name);
        }

        return trim((string) $peer->profile_name);
    }

    public function peerFor($conversation, $contact = null)
    {
        if (! $conversation || ! $conversation->mai_session_id || ! Schema::hasTable('mai_peers')) {
            return null;
        }
        $phone = preg_replace('/\D/', '', (string) optional($contact)->normalized_phone);
        $query = MaiPeer::where('mai_session_id', (int) $conversation->mai_session_id);
        if ($phone !== '') {
            $found = (clone $query)->where('phone_digits', $phone)->first();
            if ($found) {
                return $found;
            }
            $found = (clone $query)->where('remote_jid', $phone.'@s.whatsapp.net')->first();
            if ($found) {
                return $found;
            }
            $found = (clone $query)->where('remote_jid', $phone.'@g.us')->first();
            if ($found) {
                return $found;
            }
            $found = (clone $query)->where('lid', $phone)->first();
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    protected function attachLid(MaiSession $session, MaiPeer $peer)
    {
        $lid = trim((string) $peer->lid);
        $phone = preg_replace('/\D/', '', (string) $peer->phone_digits);
        if ($lid === '' || $phone === '') {
            return;
        }
        $other = MaiPeer::where('mai_session_id', $session->id)->where('remote_jid', $lid.'@lid')->first();
        if (! $other || (int) $other->id === (int) $peer->id) {
            return;
        }
        if (trim((string) $peer->saved_name) === '' && trim((string) $other->saved_name) !== '') {
            $peer->saved_name = $other->saved_name;
        }
        if (trim((string) $peer->profile_name) === '' && trim((string) $other->profile_name) !== '') {
            $peer->profile_name = $other->profile_name;
        }
        $other->phone_digits = $phone;
        $other->save();
        $peer->save();
    }

    protected function jid(array $row)
    {
        $jid = isset($row['jid']) ? trim((string) $row['jid']) : '';
        if ($jid !== '' && (substr($jid, -15) === '@s.whatsapp.net' || substr($jid, -4) === '@lid' || substr($jid, -5) === '@g.us')) {
            return substr($jid, 0, 191);
        }
        $phone = $this->phoneFromRow($row);
        if ($phone !== '') {
            return $phone.'@s.whatsapp.net';
        }
        $lid = isset($row['lid']) ? preg_replace('/\D/', '', (string) $row['lid']) : '';
        if ($lid !== '') {
            return substr($lid.'@lid', 0, 191);
        }

        return '';
    }

    protected function phoneFromRow(array $row)
    {
        $jid = isset($row['jid']) ? (string) $row['jid'] : '';
        if ($jid !== '' && substr($jid, -4) === '@lid') {
            return '';
        }
        $phone = isset($row['phone']) ? preg_replace('/\D/', '', (string) $row['phone']) : '';
        if ($phone === '' && substr($jid, -15) === '@s.whatsapp.net') {
            $phone = preg_replace('/\D/', '', strtok($jid, '@'));
        }
        if ($phone === '' || strlen($phone) < 8 || strlen($phone) > 15) {
            return '';
        }

        return $phone;
    }

    protected function clip($value)
    {
        $value = trim((string) $value);
        if ($value === '' || preg_match('/^\+?[\d\s\-\(\)]+$/', $value)) {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($value, 0, 191) : substr($value, 0, 191);
    }
}
