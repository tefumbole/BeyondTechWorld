<?php

namespace App\Services\Mai;

use App\Mai\MaiSession;
use App\Services\Cloud\MaiWhatsAppEngine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Pulls the linked phone's event log into this session only.
 * History and reconnect replays are stored and never answered.
 */
class MaiSync
{
    public function pull(MaiSession $session)
    {
        if (! Schema::hasColumn('mai_sessions', 'sync_seq')) {
            return;
        }
        $phone = $session->phone_number ?: $session->expected_phone;
        $engine = app(MaiWhatsAppEngine::class);
        $payload = $engine->events($phone, (int) $session->sync_seq);
        if (! is_array($payload) || ! isset($payload['generation'])) {
            return;
        }
        $generation = (int) $payload['generation'];
        if ($generation < 1) {
            return;
        }
        if ((int) $session->sync_generation !== $generation) {
            $session->sync_generation = $generation;
            $session->sync_seq = 0;
            $payload = $engine->events($phone, 0);
            if (! is_array($payload)) {
                return;
            }
            $events = isset($payload['events']) && is_array($payload['events']) ? $payload['events'] : [];
        }
        $events = isset($payload['events']) && is_array($payload['events']) ? $payload['events'] : [];
        $this->ingest($session, $events, $generation);
    }

    /**
     * Stores one already-downloaded batch. Progress is recorded only after the
     * messages in that batch have been written.
     */
    public function ingest(MaiSession $session, array $events, $generation = null)
    {
        $generation = $generation === null ? (int) $session->sync_generation : (int) $generation;
        $store = app(MaiInboxStore::class);
        $identity = app(MaiIdentity::class);
        $last = (int) $session->sync_seq;
        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }
            if ($generation > 0 && (int) (isset($event['generation']) ? $event['generation'] : 0) !== $generation) {
                continue;
            }
            $seq = (int) (isset($event['seq']) ? $event['seq'] : 0);
            if ($seq > 0 && $seq <= $last) {
                continue;
            }
            if ($seq > $last) {
                $last = $seq;
            }
            $kind = isset($event['kind']) ? (string) $event['kind'] : '';
            if ($kind === 'contact' || $kind === 'chat') {
                $identity->remember($session, $event);
                continue;
            }
            if ($kind === 'presence') {
                $typing = app(\App\Services\WhatsApp\WhatsAppTyping::class);
                $who = isset($event['phone']) ? (string) $event['phone'] : '';
                $presence = isset($event['presence']) ? (string) $event['presence'] : '';
                if ($presence === 'composing' || $presence === 'recording') {
                    $typing->remember($who, $presence);
                } else {
                    $typing->forget($who);
                }
                continue;
            }
            if ($kind === 'receipt') {
                $store->applyReceipt($session, $event);
                continue;
            }
            if ($kind === 'history-page') {
                $this->rememberPage($session, $event);
                continue;
            }
            if ($kind === 'history') {
                $this->rememberProgress($session, $event);
                continue;
            }
            if ($kind === 'message') {
                $event['session_id'] = $session->worker_session_id;
                if (empty($event['event_id']) && ! empty($event['id'])) {
                    $event['event_id'] = (string) $event['id'];
                }
                $store->accept($event);
            }
        }
        $session->sync_seq = $last;
        $session->save();
    }

    protected function rememberPage(MaiSession $session, array $event)
    {
        $jid = isset($event['jid']) ? (string) $event['jid'] : '';
        $digits = preg_replace('/\D/', '', strtok($jid, '@'));
        $state = isset($event['state']) ? (string) $event['state'] : 'received';
        if (! in_array($state, ['pending', 'received', 'failed', 'unavailable'], true)) {
            $state = 'received';
        }
        if ($digits !== '') {
            Cache::put('mai.older.'.$session->id.'.'.$digits, $state, now()->addMinutes(10));
        }
    }

    protected function rememberProgress(MaiSession $session, array $event)
    {
        $progress = isset($event['progress']) ? (int) $event['progress'] : null;
        if ($progress !== null) {
            Cache::put('mai.sync.'.$session->id, max(0, min(100, $progress)), now()->addDay());
        }
        if (! empty($event['ready']) && $progress !== null && $progress >= 100 && Schema::hasColumn('mai_sessions', 'history_ready')) {
            $session->history_ready = 1;
        }
    }
}
