<?php

namespace App\Services\Mai;

use App\Mai\MaiSession;
use App\Services\Cloud\CloudTenantContext;
use App\Services\Cloud\MaiWhatsAppEngine;
use App\User;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use Carbon\Carbon;

/**
 * The phone linked at signup is the same hub as BeyondTechWorld.
 * Chats are stored on that session, never on the business line.
 */
class PersonalHub
{
    public function ensure(User $user)
    {
        $digits = preg_replace('/\D/', '', (string) $user->phone);
        if (! preg_match('/^[1-9][0-9]{7,14}$/', $digits) || MaiBusinessLine::is($digits)) {
            return null;
        }
        $worker = 'p'.$digits;
        $session = MaiSession::where('worker_session_id', $worker)->first();
        if ($session && (int) $session->user_id !== (int) $user->id) {
            return null;
        }
        if (! $session) {
            $session = new MaiSession();
            $session->public_id = 'c'.bin2hex(random_bytes(16));
            $session->user_id = $user->id;
            $session->provider = MaiProviderRouter::MAI;
            $session->worker_session_id = $worker;
            $session->ai_enabled = 0;
        }
        $session->expected_phone = $digits;
        $session->phone_number = $digits;
        if (! $session->exists) {
            $session->status = 'PENDING';
        }
        $this->issueKey($session, false);
        $session->save();

        return $session;
    }

    public function sync(MaiSession $session, $lookupNames = true)
    {
        $phone = $session->phone_number ?: $session->expected_phone;
        $engine = app(MaiWhatsAppEngine::class);
        $outbound = app(MaiOutboundService::class);
        try {
            app(MaiSync::class)->pull($session->fresh() ?: $session);
        } catch (\Throwable $e) {
        }
        $session = $session->fresh() ?: $session;
        app(CloudTenantContext::class)->withoutIsolation(function () use ($session, $phone, $engine, $outbound, $lookupNames) {
            $identity = app(MaiIdentity::class);
            if ($lookupNames) {
            foreach ($engine->chats($phone) as $chat) {
                if (substr($chat['jid'], -5) === '@g.us' || (int) $chat['at'] <= 0) {
                    continue;
                }
                $store = $chat['phone'] !== '' ? $chat['phone'] : (isset($chat['lid']) ? $chat['lid'] : '');
                if ($store === '' || strlen($store) < 8 || strlen($store) > 15) {
                    continue;
                }
                $conversation = $outbound->conversation($session, $store);
                $contact = $conversation->contact;
                $identity->remember($session, $chat);
                $saved = $identity->display($conversation, $contact);
                if ($saved !== '' && $saved !== 'WhatsApp' && \Illuminate\Support\Facades\Schema::hasColumn('whatsapp_conversations', 'peer_label')) {
                    $label = function_exists('mb_substr') ? mb_substr($saved, 0, 191) : substr($saved, 0, 191);
                    if ((string) $conversation->peer_label !== $label) {
                        $conversation->peer_label = $label;
                    }
                }
                if ($chat['preview'] !== '' && (string) $conversation->last_message !== $chat['preview']) {
                    $conversation->last_message = $chat['preview'];
                }
                if ((int) $chat['unread'] > (int) $conversation->unread_count) {
                    $conversation->unread_count = (int) $chat['unread'];
                }
                $at = (int) $chat['at'];
                if ($at > 1000000000000) {
                    $at = (int) floor($at / 1000);
                }
                if ($at > 0) {
                    $when = Carbon::createFromTimestamp($at);
                    if (! $conversation->last_activity_at || $when->gt($conversation->last_activity_at)) {
                        $conversation->last_activity_at = $when;
                    }
                }
                if ($conversation->isDirty()) {
                    $conversation->save();
                }
            }
            }
            $incoming = $engine->messages($phone);
            if (! $lookupNames && count($incoming) > 60) {
                $incoming = array_slice($incoming, -60);
            }
            foreach ($incoming as $row) {
                $from = (string) $row['from'];
                if (substr($from, -5) === '@g.us') {
                    continue;
                }
                $digits = preg_replace('/\D/', '', strtok($from, '@'));
                if ($digits === '' || strlen($digits) < 8 || strlen($digits) > 15) {
                    continue;
                }
                if ($row['at'] === '' || ! is_numeric($row['at'])) {
                    continue;
                }
                $stampValue = (int) $row['at'];
                if ($stampValue > 1000000000000) {
                    $stampValue = (int) floor($stampValue / 1000);
                }
                $providerId = isset($row['id']) ? substr((string) $row['id'], 0, 128) : '';
                $status = $this->receiptStatus($row);
                $conversation = $outbound->conversation($session, $digits);
                if ($providerId !== '') {
                    $existing = WhatsAppMessage::where('conversation_id', $conversation->id)
                        ->where('provider_message_id', $providerId)
                        ->first();
                    if ($existing) {
                        $this->raiseStatus($existing, $status);
                        continue;
                    }
                }
                $stamp = Carbon::createFromTimestamp($stampValue);
                $same = WhatsAppMessage::where('conversation_id', $conversation->id)
                    ->where('body', $row['text'])
                    ->where('direction', ! empty($row['from_me']) ? WhatsAppMessage::DIR_OUT : WhatsAppMessage::DIR_IN)
                    ->where('created_at', '>=', $stamp->copy()->subMinutes(2))
                    ->orderByDesc('id')
                    ->first();
                if ($same) {
                    if ($providerId !== '' && ! $same->provider_message_id) {
                        $same->provider_message_id = $providerId;
                        $same->save();
                    }
                    $this->raiseStatus($same, $status);
                    continue;
                }
                $key = 'mai-live:'.$session->id.':'.substr(sha1($from.'|'.$row['text'].'|'.$row['at']), 0, 40);
                if (WhatsAppMessage::where('provider_message_id', $key)->exists()) {
                    continue;
                }
                $message = new WhatsAppMessage();
                $message->timestamps = false;
                $message->conversation_id = $conversation->id;
                $message->contact_id = $conversation->contact_id;
                $message->direction = ! empty($row['from_me']) ? WhatsAppMessage::DIR_OUT : WhatsAppMessage::DIR_IN;
                $message->type = 'TEXT';
                $message->provider_message_id = $providerId !== '' ? $providerId : $key;
                $message->body = $row['text'];
                $message->status = $status;
                $message->sender_type = ! empty($row['from_me']) ? 'ECHO' : 'CONTACT';
                $message->created_at = $stamp;
                $message->updated_at = $stamp;
                $message->save();
                $preview = function_exists('mb_substr') ? mb_substr($row['text'], 0, 191) : substr($row['text'], 0, 191);
                $conversation->last_message = $preview;
                if (! $conversation->last_activity_at || $stamp->gt($conversation->last_activity_at)) {
                    $conversation->last_activity_at = $stamp;
                }
                if (empty($row['from_me'])) {
                    $conversation->last_incoming_at = $stamp;
                    if (! $lookupNames) {
                        $conversation->unread_count = (int) $conversation->unread_count + 1;
                    }
                }
                $conversation->save();
            }
        });
    }

    public function groups(MaiSession $session)
    {
        $phone = $session->phone_number ?: $session->expected_phone;
        $listed = app(MaiWhatsAppEngine::class)->groups($phone);
        if ($listed) {
            return $listed;
        }
        $groups = [];
        foreach (app(MaiWhatsAppEngine::class)->chats($phone) as $chat) {
            if (substr($chat['jid'], -5) !== '@g.us') {
                continue;
            }
            $groups[] = [
                'jid' => $chat['jid'],
                'name' => $chat['name'] !== '' ? $chat['name'] : 'WhatsApp group',
                'members' => null,
            ];
        }

        return $groups;
    }

    public function issueKey(MaiSession $session, $save = true)
    {
        if (! \Illuminate\Support\Facades\Schema::hasColumn('mai_sessions', 'api_key')) {
            return $session;
        }
        if (preg_match('/^[a-f0-9]{64}$/', (string) $session->api_key)) {
            return $session;
        }
        $session->api_key = bin2hex(random_bytes(32));
        if ($save && $session->exists) {
            $session->save();
        }

        return $session;
    }

    public function label($contact, $conversation = null)
    {
        if ($conversation && $conversation->mai_session_id && \Illuminate\Support\Facades\Schema::hasTable('mai_peers')) {
            return app(MaiIdentity::class)->display($conversation, $contact);
        }
        $phone = preg_replace('/\D/', '', (string) optional($contact)->normalized_phone);
        $own = $conversation ? trim((string) $conversation->peer_label) : '';
        if ($own !== '') {
            return $own;
        }
        if ($conversation && $conversation->mai_session_id) {
            if ($phone !== '' && strlen($phone) <= 13) {
                return '+'.$phone;
            }

            return 'WhatsApp';
        }
        $name = $this->savedName(optional($contact)->wa_name, $phone);
        if ($name !== '') {
            return $name;
        }
        $shown = preg_replace('/\D/', '', (string) optional($contact)->display_phone);
        if ($shown !== '' && strlen($shown) <= 13) {
            return (string) $contact->display_phone;
        }
        if ($phone !== '' && strlen($phone) <= 13) {
            return '+'.$phone;
        }

        return 'WhatsApp';
    }

    public function phoneLabel($contact)
    {
        $phone = preg_replace('/\D/', '', (string) optional($contact)->normalized_phone);
        if ($phone !== '' && strlen($phone) <= 13) {
            return '+'.$phone;
        }

        return '';
    }

    protected function receiptStatus(array $row)
    {
        if (empty($row['from_me'])) {
            return WhatsAppMessage::STATUS_DELIVERED;
        }
        $receipt = isset($row['receipt']) ? (int) $row['receipt'] : 0;
        if ($receipt >= 5) {
            return WhatsAppMessage::STATUS_PLAYED;
        }
        if ($receipt >= 4) {
            return WhatsAppMessage::STATUS_READ;
        }
        if ($receipt >= 3) {
            return WhatsAppMessage::STATUS_DELIVERED;
        }

        return WhatsAppMessage::STATUS_SENT;
    }

    protected function raiseStatus(WhatsAppMessage $message, $status)
    {
        $rank = [
            WhatsAppMessage::STATUS_QUEUED => 1,
            WhatsAppMessage::STATUS_SENT => 2,
            WhatsAppMessage::STATUS_DELIVERED => 3,
            WhatsAppMessage::STATUS_READ => 4,
            WhatsAppMessage::STATUS_PLAYED => 5,
        ];
        $current = isset($rank[$message->status]) ? $rank[$message->status] : 0;
        $next = isset($rank[$status]) ? $rank[$status] : 0;
        if ($next <= $current) {
            return;
        }
        $message->status = $status;
        if ($status === WhatsAppMessage::STATUS_DELIVERED && ! $message->delivered_at) {
            $message->delivered_at = Carbon::now();
        }
        if ($status === WhatsAppMessage::STATUS_READ && ! $message->read_at) {
            $message->read_at = Carbon::now();
        }
        $message->save();
    }

    protected function savedName($name, $phone)
    {
        $name = trim((string) $name);
        $digits = preg_replace('/\D/', '', $name);
        $phone = preg_replace('/\D/', '', (string) $phone);
        if ($name === '' || $digits === $phone || $name === '+'.$phone) {
            return '';
        }

        return $name;
    }
}
