<?php

namespace App\Services\Mai;

use App\Mai\MaiSession;
use App\Services\Cloud\MaiWhatsAppEngine;
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
        $store = app(MaiInboxStore::class);
        $identity = app(MaiIdentity::class);
        $last = (int) $session->sync_seq;
        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }
            if ((int) (isset($event['generation']) ? $event['generation'] : 0) !== $generation) {
                continue;
            }
            $seq = (int) (isset($event['seq']) ? $event['seq'] : 0);
            if ($seq <= $last) {
                continue;
            }
            $last = $seq;
            $kind = isset($event['kind']) ? (string) $event['kind'] : '';
            if ($kind === 'contact' || $kind === 'chat') {
                $identity->remember($session, $event);
                continue;
            }
            if ($kind === 'receipt') {
                $store->applyReceipt($session, $event);
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
        if (! empty($payload['history_ready'])) {
            $session->history_ready = 1;
        }
        $session->save();
    }
}
