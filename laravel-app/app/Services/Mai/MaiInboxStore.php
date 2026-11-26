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
        $row->kind = ! empty($event['from_me']) ? 'echo' : 'message';
        $row->save();
        if (! empty($event['from_me'])) {
            $this->echo($session, $event);

            return $row;
        }
        $message = $this->incoming($session, $event);
        $this->maybeReply($session, $message);
        app(MaiWebhookService::class)->dispatch($session, $eventId, [
            'id' => $eventId,
            'type' => 'message.received',
            'session_id' => $session->public_id,
            'from' => isset($event['remote']) ? (string) $event['remote'] : '',
            'text' => isset($event['text']) ? (string) $event['text'] : '',
        ]);

        return $row;
    }

    protected function incoming(MaiSession $session, array $event)
    {
        $remote = preg_replace('/\D+/', '', isset($event['remote']) ? (string) $event['remote'] : '');
        if ($remote === '') {
            return null;
        }
        $text = isset($event['text']) ? trim((string) $event['text']) : '';
        $conversation = app(MaiOutboundService::class)->conversation($session, $remote);

        return app(CloudTenantContext::class)->withoutIsolation(function () use ($session, $conversation, $event, $text) {
            $providerId = 'mai:'.$session->id.':'.$event['event_id'];
            $existing = WhatsAppMessage::where('provider_message_id', $providerId)->first();
            if ($existing) {
                return $existing;
            }
            $message = new WhatsAppMessage();
            $message->conversation_id = $conversation->id;
            $message->contact_id = $conversation->contact_id;
            $message->direction = WhatsAppMessage::DIR_IN;
            $message->type = 'TEXT';
            $message->provider_message_id = $providerId;
            $message->body = $text;
            $message->status = WhatsAppMessage::STATUS_DELIVERED;
            $message->sender_type = 'CONTACT';
            $message->delivered_at = now();
            $message->save();
            $conversation->last_message = $text;
            $conversation->last_activity_at = now();
            $conversation->last_incoming_at = now();
            $conversation->unread_count = (int) $conversation->unread_count + 1;
            $conversation->save();

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

    protected function maybeReply(MaiSession $session, $message)
    {
        if (! $message || ! $session->ai_enabled) {
            return;
        }
        $generator = config('cloud.mai_customer.ai_reply');
        if (! is_callable($generator)) {
            return;
        }
        $conversation = WhatsAppConversation::find($message->conversation_id);
        if (! $this->stillAi($conversation, $session)) {
            return;
        }
        $text = call_user_func($generator, $conversation, $message);
        $conversation = $conversation->fresh();
        $session = $session->fresh();
        if (! $this->stillAi($conversation, $session) || trim((string) $text) === '') {
            return;
        }
        app(MaiOutboundService::class)->deliver($conversation, $text, 'ai-'.$message->id, null);
    }

    protected function stillAi($conversation, $session)
    {
        if (! $conversation || ! $session || ! $session->ai_enabled) {
            return false;
        }

        return strtoupper((string) $conversation->mode) === WhatsAppConversation::MODE_AI;
    }
}
