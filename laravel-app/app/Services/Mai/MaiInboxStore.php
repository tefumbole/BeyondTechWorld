<?php

namespace App\Services\Mai;

use App\Mai\MaiInboundEvent;
use App\Mai\MaiOutbound;
use App\Mai\MaiSession;
use App\Services\Cloud\CloudTenantContext;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;

class MaiInboxStore
{
    public function accept(array $event)
    {
        $workerId = isset($event['session_id']) ? (string) $event['session_id'] : '';
        $eventId = isset($event['event_id']) ? substr((string) $event['event_id'], 0, 80) : '';
        if ($workerId === '' || $eventId === '') {
            return null;
        }
        $session = MaiSession::where('worker_session_id', $workerId)->first();
        if (! $session || $session->provider !== MaiProviderRouter::MAI) {
            return null;
        }
        if (MaiBusinessLine::is($session->phone_number) || MaiBusinessLine::is($session->expected_phone)) {
            return null;
        }
        $known = MaiInboundEvent::where('mai_session_id', $session->id)->where('event_id', $eventId)->first();
        if ($known) {
            return $known;
        }
        $row = new MaiInboundEvent();
        $row->mai_session_id = $session->id;
        $row->event_id = $eventId;
        $row->kind = isset($event['kind']) && $event['kind'] === 'receipt' ? 'receipt' : (! empty($event['from_me']) ? 'echo' : 'message');
        $row->save();
        if ($row->kind === 'receipt') {
            $this->applyReceipt($session, $event);

            return $row;
        }
        if (! empty($event['from_me'])) {
            $this->echo($session, $event);

            return $row;
        }
        $message = $this->incoming($session, $event);
        $jid = isset($event['jid']) ? (string) $event['jid'] : '';
        if ($this->isLive($event) && substr($jid, -5) !== '@g.us') {
            $this->maybeReply($session, $message);
            app(MaiWebhookService::class)->dispatch($session, $eventId, [
                'id' => $eventId,
                'type' => 'message.received',
                'session_id' => $session->public_id,
                'from' => isset($event['remote']) ? (string) $event['remote'] : '',
                'text' => isset($event['text']) ? (string) $event['text'] : '',
            ]);
        }

        return $row;
    }

    protected function incoming(MaiSession $session, array $event)
    {
        $jid = isset($event['jid']) ? (string) $event['jid'] : '';
        $group = substr($jid, -5) === '@g.us';
        app(MaiIdentity::class)->remember($session, $event);
        if (! empty($event['participant'])) {
            app(MaiIdentity::class)->remember($session, [
                'jid' => (string) $event['participant'],
                'saved_name' => isset($event['participant_saved']) ? (string) $event['participant_saved'] : '',
                'profile_name' => isset($event['participant_profile']) ? (string) $event['participant_profile'] : '',
            ]);
        }
        $remote = preg_replace('/\D+/', '', isset($event['remote']) ? (string) $event['remote'] : '');
        if (substr($jid, -15) === '@s.whatsapp.net' || substr($jid, -4) === '@lid' || $group) {
            $remote = preg_replace('/\D+/', '', strtok($jid, '@'));
        }
        if ($remote === '' || strlen($remote) < 8 || strlen($remote) > ($group ? 32 : 20)) {
            return null;
        }
        $text = isset($event['text']) ? trim((string) $event['text']) : '';
        $conversation = app(MaiOutboundService::class)->conversation($session, $remote);

        return app(CloudTenantContext::class)->withoutIsolation(function () use ($session, $conversation, $event, $text) {
            $providerId = 'mai:'.$session->id.':'.$event['event_id'];
            $existing = WhatsAppMessage::where('provider_message_id', $providerId)->first();
            if ($existing) {
                $stored = $this->keepFile($session, isset($event['file']) ? (string) $event['file'] : '');
                if ($stored !== '') {
                    $media = $existing->media();
                    if (empty($media['stored'])) {
                        $media['stored'] = $stored;
                        unset($media['unavailable']);
                        unset($media['reason']);
                        $existing->media_json = json_encode($media);
                        if (empty($existing->type) || strtoupper((string) $existing->type) === 'TEXT') {
                            $kind = isset($event['type']) ? strtoupper(substr((string) $event['type'], 0, 24)) : '';
                            if (in_array($kind, ['IMAGE', 'VIDEO', 'AUDIO', 'DOCUMENT', 'STICKER'], true)) {
                                $existing->type = $kind;
                            }
                        }
                        $existing->save();
                    }
                } elseif (! empty($event['unavailable'])) {
                    $media = $existing->media();
                    if (empty($media['stored'])) {
                        $media['unavailable'] = true;
                        if (! empty($event['reason'])) {
                            $media['reason'] = substr((string) $event['reason'], 0, 180);
                        }
                        if (! empty($event['file_name'])) {
                            $media['file_name'] = substr((string) $event['file_name'], 0, 180);
                        }
                        $existing->media_json = json_encode($media);
                        $existing->save();
                    }
                }

                return $existing;
            }
            $type = isset($event['type']) ? strtoupper(substr((string) $event['type'], 0, 24)) : 'TEXT';
            if (! in_array($type, ['TEXT', 'IMAGE', 'VIDEO', 'AUDIO', 'DOCUMENT', 'STICKER'], true)) {
                $type = 'TEXT';
            }
            $media = [];
            if (! empty($event['quote'])) {
                $media['quote'] = ['text' => substr((string) $event['quote'], 0, 500)];
            }
            if (! empty($event['participant'])) {
                $media['participant_jid'] = substr((string) $event['participant'], 0, 191);
                $person = \App\Mai\MaiPeer::where('mai_session_id', $session->id)
                    ->where('remote_jid', (string) $event['participant'])
                    ->first();
                $label = $person ? trim((string) ($person->saved_name ?: $person->profile_name)) : '';
                if ($label !== '') {
                    $media['participant_label'] = $label;
                }
            }
            $stored = $this->keepFile($session, isset($event['file']) ? (string) $event['file'] : '');
            if ($stored !== '') {
                $media['stored'] = $stored;
            } elseif (! empty($event['unavailable']) || ($type !== 'TEXT' && empty($event['file']))) {
                $media['unavailable'] = true;
                if (! empty($event['reason'])) {
                    $media['reason'] = substr((string) $event['reason'], 0, 180);
                }
            }
            if (! empty($event['file_name'])) {
                $media['file_name'] = substr((string) $event['file_name'], 0, 180);
            }
            if (! empty($event['id'])) {
                $media['key'] = [
                    'id' => substr((string) $event['id'], 0, 128),
                    'from_me' => ! empty($event['from_me']),
                ];
            }
            $message = new WhatsAppMessage();
            $message->conversation_id = $conversation->id;
            $message->contact_id = $conversation->contact_id;
            $message->direction = WhatsAppMessage::DIR_IN;
            $message->type = $type;
            $message->provider_message_id = $providerId;
            $message->body = $text;
            if ($media) {
                $message->media_json = json_encode($media);
            }
            $message->status = WhatsAppMessage::STATUS_DELIVERED;
            $message->sender_type = 'CONTACT';
            $message->delivered_at = now();
            $stamp = $this->stampFrom($event);
            if ($stamp) {
                $message->timestamps = false;
                $message->created_at = $stamp;
                $message->updated_at = $stamp;
            }
            $message->save();
            $conversation->last_message = $text;
            $conversation->last_activity_at = now();
            $conversation->last_incoming_at = now();
            if ($this->isLive($event)) {
                $conversation->unread_count = (int) $conversation->unread_count + 1;
            }
            $conversation->save();
            if ($this->isLive($event) && $type === 'TEXT' && $text !== '') {
                try {
                    $translator = app(\App\Services\WhatsApp\WhatsAppTranslator::class);
                    if (strtoupper((string) $conversation->mode) === WhatsAppConversation::MODE_AI && ! $translator->activeFor($conversation->id)) {
                        $translator->setChat($conversation->id, true);
                    }
                    if ($translator->activeFor($conversation->id) || strtoupper((string) $conversation->mode) === WhatsAppConversation::MODE_AI) {
                        if (! $translator->activeFor($conversation->id)) {
                            $translator->setChat($conversation->id, true);
                        }
                        $message = $translator->decorate($message->fresh() ?: $message, true);
                    }
                } catch (\Throwable $e) {
                }
            }

            return $message;
        });
    }

    protected function echo(MaiSession $session, array $event)
    {
        $eventId = (string) $event['event_id'];
        $held = MaiOutbound::where('mai_session_id', $session->id)->where('provider_message_id', $eventId)->first();
        if ($held && $held->status !== 'sent') {
            $held->status = 'sent';
            $held->save();
        }
        $remote = preg_replace('/\D+/', '', isset($event['remote']) ? (string) $event['remote'] : '');
        if ($remote === '') {
            return;
        }
        $conversation = app(MaiOutboundService::class)->conversation($session, $remote);
        app(CloudTenantContext::class)->withoutIsolation(function () use ($session, $conversation, $event) {
            $providerId = 'mai-echo:'.$session->id.':'.$event['event_id'];
            if (WhatsAppMessage::where('provider_message_id', $providerId)->exists()) {
                return;
            }
            $message = new WhatsAppMessage();
            $message->conversation_id = $conversation->id;
            $message->contact_id = $conversation->contact_id;
            $message->direction = WhatsAppMessage::DIR_OUT;
            $message->type = 'TEXT';
            $message->provider_message_id = $providerId;
            $message->body = isset($event['text']) ? (string) $event['text'] : '';
            $message->status = WhatsAppMessage::STATUS_SENT;
            $message->sender_type = 'ECHO';
            $message->sent_at = now();
            $message->save();
        });
    }

    public function applyReceipt(MaiSession $session, array $event)
    {
        $id = isset($event['id']) ? substr((string) $event['id'], 0, 128) : '';
        $receipt = isset($event['receipt']) ? (int) $event['receipt'] : 0;
        if ($id === '' || $receipt < 2) {
            return;
        }
        if ($receipt >= 5) {
            $status = WhatsAppMessage::STATUS_PLAYED;
        } elseif ($receipt >= 4) {
            $status = WhatsAppMessage::STATUS_READ;
        } elseif ($receipt >= 3) {
            $status = WhatsAppMessage::STATUS_DELIVERED;
        } else {
            $status = WhatsAppMessage::STATUS_SENT;
        }
        $ids = WhatsAppConversation::where('mai_session_id', $session->id)->pluck('id')->all();
        if (! $ids) {
            return;
        }
        $message = WhatsAppMessage::whereIn('conversation_id', $ids)->where(function ($query) use ($id, $session) {
            $query->where('provider_message_id', $id)
                ->orWhere('provider_message_id', 'mai:'.$session->id.':'.$id)
                ->orWhere('provider_message_id', 'mai-echo:'.$session->id.':'.$id);
        })->first();
        if (! $message) {
            return;
        }
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
            $message->delivered_at = now();
        }
        if (in_array($status, [WhatsAppMessage::STATUS_READ, WhatsAppMessage::STATUS_PLAYED], true) && ! $message->read_at) {
            $message->read_at = now();
        }
        $message->save();
    }

    public function storeMedia(MaiSession $session, $file)
    {
        return $this->keepFile($session, $file);
    }

    protected function keepFile(MaiSession $session, $file)
    {
        $file = str_replace('\\', '/', trim((string) $file));
        $prefix = 'sessions/'.$session->worker_session_id.'/media/';
        if ($file === '' || strpos($file, '..') !== false || strpos($file, $prefix) !== 0) {
            return '';
        }
        $source = storage_path('app/whatsapp-link/'.$file);
        if (! is_file($source)) {
            return '';
        }
        $name = basename($file);
        if (! preg_match('/^[A-Za-z0-9_.-]+$/', $name)) {
            return '';
        }
        $relative = 'whatsapp-media/mai/'.$session->id.'/'.$name;
        $dest = storage_path('app/'.$relative);
        if (! is_dir(dirname($dest)) && ! @mkdir(dirname($dest), 0755, true)) {
            return '';
        }
        if (! @copy($source, $dest)) {
            return '';
        }

        return $relative;
    }

    protected function stampFrom(array $event)
    {
        if (! isset($event['at']) || ! is_numeric($event['at'])) {
            return null;
        }
        $value = (int) $event['at'];
        if ($value > 1000000000000) {
            $value = (int) floor($value / 1000);
        }
        if ($value < 1) {
            return null;
        }

        return \Carbon\Carbon::createFromTimestamp($value);
    }

    protected function isLive(array $event)
    {
        if (! array_key_exists('live', $event)) {
            return true;
        }

        return ! empty($event['live']);
    }

    protected function maybeReply(MaiSession $session, $message)
    {
        if (! $message) {
            return;
        }
        $conversation = WhatsAppConversation::find($message->conversation_id);
        if (! $this->stillAi($conversation, $session)) {
            return;
        }
        $generator = config('cloud.mai_customer.ai_reply');
        if (is_callable($generator)) {
            $text = call_user_func($generator, $conversation, $message);
        } else {
            $text = app(MaiAiReply::class)->answer($conversation, $message);
        }
        $conversation = $conversation->fresh();
        $session = $session->fresh();
        if (! $this->stillAi($conversation, $session) || trim((string) $text) === '') {
            return;
        }
        app(MaiOutboundService::class)->deliver($conversation, $text, 'ai-'.$message->id, null);
    }

    protected function stillAi($conversation, $session)
    {
        if (! $conversation || ! $session) {
            return false;
        }

        return strtoupper((string) $conversation->mode) === WhatsAppConversation::MODE_AI;
    }
}
