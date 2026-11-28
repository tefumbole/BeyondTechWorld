<?php

namespace App\Services\Mai;

use App\Mai\MaiSession;
use App\Services\Cloud\MaiWhatsAppEngine;
use Carbon\Carbon;
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
        $payload = $engine->events($phone, (int) $session->sync_seq, 3);
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
            $payload = $engine->events($phone, 0, 3);
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

    /**
     * Connection and the first import are separate. Completion is the adapter
     * signal after the batches in that pull are stored, never a timer.
     */
    public function view(MaiSession $session)
    {
        if (! Schema::hasColumn('mai_sessions', 'history_ready')) {
            return null;
        }
        $ready = (bool) $session->history_ready;
        $done = Cache::get('mai.sync.done.'.$session->id);
        if ($ready) {
            return [
                'state' => 'complete',
                'text' => 'Initial sync complete',
                'detail' => $this->receivedLine($done),
                'percent' => null,
                'retry' => false,
            ];
        }
        $touch = Cache::get('mai.sync.touch.'.$session->id);
        $connectedAt = $session->connected_at ? Carbon::parse($session->connected_at) : null;
        $freshLink = $connectedAt && $connectedAt->gt(now()->subMinutes(30));
        if (! $touch && ! $freshLink) {
            return null;
        }
        if (Cache::get('mai.sync.fail.'.$session->id)) {
            return [
                'state' => 'failed',
                'text' => 'Could not finish syncing',
                'detail' => '',
                'percent' => null,
                'retry' => true,
            ];
        }
        $stale = $touch && (time() - (int) $touch) > 90;
        $quiet = ! $touch && $connectedAt && $connectedAt->lt(now()->subSeconds(90));
        if ($stale || $quiet) {
            return [
                'state' => 'incomplete',
                'text' => 'Sync incomplete',
                'detail' => '',
                'percent' => null,
                'retry' => true,
            ];
        }
        $progress = Cache::get('mai.sync.'.$session->id);
        $percent = is_numeric($progress) ? (int) $progress : null;
        if ($percent === null || $percent < 1 || $percent > 99) {
            $percent = null;
        }

        return [
            'state' => 'syncing',
            'text' => 'Connected · Syncing contacts and chats',
            'detail' => '',
            'percent' => $percent,
            'retry' => false,
        ];
    }

    protected function rememberProgress(MaiSession $session, array $event)
    {
        $phase = isset($event['phase']) ? (string) $event['phase'] : '';
        Cache::put('mai.sync.touch.'.$session->id, time(), now()->addDay());
        if ($phase === 'failed') {
            Cache::put('mai.sync.fail.'.$session->id, 1, now()->addDay());

            return;
        }
        if ($phase === 'started' || $phase === 'retry') {
            Cache::forget('mai.sync.fail.'.$session->id);

            return;
        }
        $progress = isset($event['progress']) ? (int) $event['progress'] : null;
        if ($progress !== null && $progress >= 0 && $progress <= 100) {
            Cache::put('mai.sync.'.$session->id, $progress, now()->addDay());
        }
        if (empty($event['ready']) || $progress === null || $progress < 100 || ! Schema::hasColumn('mai_sessions', 'history_ready')) {
            return;
        }
        $session->history_ready = 1;
        Cache::forget('mai.sync.fail.'.$session->id);
        if (array_key_exists('contacts', $event) && array_key_exists('chats', $event)) {
            Cache::put('mai.sync.done.'.$session->id, [
                'contacts' => (int) $event['contacts'],
                'chats' => (int) $event['chats'],
            ], now()->addDays(30));
        }
    }

    protected function receivedLine($done)
    {
        if (! is_array($done) || ! array_key_exists('contacts', $done) || ! array_key_exists('chats', $done)) {
            return '';
        }
        $contacts = (int) $done['contacts'];
        $chats = (int) $done['chats'];

        return $contacts.' contact'.($contacts === 1 ? '' : 's').' and '.$chats.' chat'.($chats === 1 ? '' : 's').' received.';
    }
}
