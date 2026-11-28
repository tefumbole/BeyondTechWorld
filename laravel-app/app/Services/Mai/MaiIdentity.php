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
            $peer->saved_name = $this->chooseSaved(trim((string) $peer->saved_name), $saved);
        }
        if ($profile !== '') {
            $peer->profile_name = $profile;
        }
        $phone = $this->phoneFromRow($row);
        $currentPhone = preg_replace('/\D/', '', (string) $peer->phone_digits);
        if ($phone !== '' && ($currentPhone === '' || $currentPhone === $phone)) {
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
        $saved = $peer ? trim((string) $peer->saved_name) : '';
        if ($saved !== '') {
            return $saved;
        }
        $profile = $peer ? trim((string) $peer->profile_name) : '';
        if ($profile !== '') {
            return $profile;
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
        if ($phone === '') {
            return null;
        }
        $candidates = (clone $query)->where(function ($rows) use ($phone) {
            $rows->where('phone_digits', $phone)
                ->orWhere('remote_jid', $phone.'@s.whatsapp.net')
                ->orWhere('remote_jid', $phone.'@g.us')
                ->orWhere('lid', $phone);
        })->get();
        $best = null;
        foreach ($candidates as $row) {
            if ($this->preferPeer($row, $best)) {
                $best = $row;
            }
        }
        if ($best && trim((string) $best->saved_name) !== '') {
            return $best;
        }
        $mapped = $this->mappedPeer($query, $phone);
        if ($mapped) {
            return $mapped;
        }

        return $best;
    }

    /**
     * The phone a chat should be stored under. A lid that is already tied to a
     * saved number uses that number, so the same person is not a second chat.
     */
    public function chatPhone(MaiSession $session, $digits)
    {
        $digits = preg_replace('/\D/', '', (string) $digits);
        if ($digits === '' || strlen($digits) < 8 || strlen($digits) > 15) {
            return '';
        }
        $alias = $this->aliasPhone($session, $digits.'@lid');
        if ($alias !== '' && $alias !== $digits && strlen($alias) >= 8 && strlen($alias) <= 13 && ! MaiBusinessLine::is($alias)) {
            return $alias;
        }

        return $digits;
    }

    public function aliasPhone(MaiSession $session, $jid)
    {
        $jid = (string) $jid;
        if (substr($jid, -4) !== '@lid' || ! Schema::hasTable('mai_peers')) {
            return '';
        }
        $lid = preg_replace('/\D/', '', strtok($jid, '@'));
        $peer = $this->mappedPeer(MaiPeer::where('mai_session_id', $session->id), $lid);

        return $peer ? preg_replace('/\D/', '', (string) $peer->phone_digits) : '';
    }

    protected function mappedPeer($query, $phone)
    {
        if ($phone === '') {
            return null;
        }
        $rows = (clone $query)->where('lid', $phone)->get();
        $best = null;
        foreach ($rows as $row) {
            $digits = preg_replace('/\D/', '', (string) $row->phone_digits);
            if ($digits === '' || $digits === $phone || strlen($digits) < 8 || strlen($digits) > 13 || trim((string) $row->saved_name) === '') {
                continue;
            }
            if ($this->preferPeer($row, $best)) {
                $best = $row;
            }
        }

        return $best;
    }

    protected function preferPeer($candidate, $current)
    {
        if (! $candidate) {
            return false;
        }
        if (! $current) {
            return true;
        }
        $candidateBook = $this->addressBookSaved($candidate);
        $currentBook = $this->addressBookSaved($current);
        if ($candidateBook !== $currentBook) {
            return $candidateBook;
        }
        $candidateSaved = $this->distinctSaved($candidate);
        $currentSaved = $this->distinctSaved($current);
        if ($candidateSaved !== $currentSaved) {
            return $candidateSaved;
        }

        return false;
    }

    protected function addressBookSaved($peer)
    {
        return substr((string) $peer->remote_jid, -15) === '@s.whatsapp.net' && trim((string) $peer->saved_name) !== '';
    }

    protected function distinctSaved($peer)
    {
        $saved = trim((string) $peer->saved_name);
        $profile = trim((string) $peer->profile_name);

        return $saved !== '' && $saved !== $profile;
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
        $otherSaved = trim((string) $other->saved_name);
        $otherProfile = trim((string) $other->profile_name);
        if (trim((string) $peer->saved_name) === '' && $otherSaved !== '' && $otherSaved !== $otherProfile) {
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

    public function chooseSaved($current, $incoming)
    {
        $now = trim((string) $current);
        $next = trim((string) $incoming);
        if ($next === '') {
            return $now;
        }
        if ($now === '' || $now === 'WhatsApp') {
            return $next;
        }
        $short = $this->plain($now);
        $long = $this->plain($next);
        if ($short !== '' && $long !== '' && strlen($long) > strlen($short) && strpos($long, $short) !== false) {
            return $next;
        }
        if ($short !== '' && $long !== '' && strlen($short) > strlen($long) && strpos($short, $long) !== false) {
            return $now;
        }
        if ($short === $long) {
            return $now;
        }

        return $next;
    }

    public function prefer($primary, $alternate)
    {
        $chosen = $this->fuller($primary, $alternate);

        return $chosen !== '' ? $chosen : trim((string) $primary);
    }

    protected function fuller($saved, $other)
    {
        $saved = trim((string) $saved);
        $other = trim((string) $other);
        if ($saved === '' || $saved === 'WhatsApp') {
            return ($other === '' || $other === 'WhatsApp') ? '' : $other;
        }
        if ($other === '' || $other === 'WhatsApp') {
            return $saved;
        }
        $short = $this->plain($saved);
        $long = $this->plain($other);
        if ($short !== '' && $long !== '' && strlen($long) > strlen($short) && stripos($long, $short) !== false) {
            return $other;
        }

        return $saved;
    }

    protected function plain($value)
    {
        $value = trim((string) preg_replace('/[^\p{L}\p{N}\s.]+/u', ' ', (string) $value));

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    public function searchAliases($digits)
    {
        $digits = preg_replace('/\D/', '', (string) $digits);
        if ($digits === '' || strpos($digits, '237') !== 0) {
            return $digits === '' ? [] : [$digits];
        }
        $national = substr($digits, 3);
        $other = '';
        if (strlen($national) === 8 && strpos('5679', $national[0]) !== false) {
            $other = '6'.$national;
        } elseif (strlen($national) === 9 && $national[0] === '6' && strpos('5679', $national[1]) !== false) {
            $other = substr($national, 1);
        }
        $aliases = [$digits];
        if ($other !== '') {
            $aliases[] = '237'.$other;
        }

        return array_values(array_unique($aliases));
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
