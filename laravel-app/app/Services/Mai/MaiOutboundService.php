<?php

namespace App\Services\Mai;

use App\Mai\MaiOutbound;
use App\Mai\MaiSession;
use App\Services\Cloud\CloudTenantContext;
use App\WhatsApp\WhatsAppContact;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;

class MaiOutboundService
{
    protected $link;

    protected $apiKey;

    protected $apiGate = false;

    public function __construct(MaiLinkClient $link)
    {
        $this->link = $link;
    }

    public function sendTo(MaiSession $session, $to, $text, $idempotencyKey, $apiKey = null)
    {
        if ($session->provider !== MaiProviderRouter::MAI || MaiBusinessLine::is($session->phone_number) || MaiBusinessLine::is($session->expected_phone)) {
            return ['success' => false, 'error' => 'This chat is not a Whats MAI session.'];
        }
        if ($session->status === 'UNLINKED') {
            return ['success' => false, 'error' => 'Link the phone again to send.'];
        }
        if ($session->status !== 'CONNECTED') {
            return ['success' => false, 'error' => 'Approve WhatsApp on the phone before sending.'];
        }
        $this->apiKey = $apiKey;
        $queued = $this->queued($session, $idempotencyKey);
        if (! $queued) {
            $blocked = $this->blocked($session, $this->apiKey);
            if ($blocked) {
                return $blocked;
            }
            if ($this->overRate($session, $idempotencyKey)) {
                return ['success' => false, 'status' => 'failed', 'error' => 'This session has reached its send limit for this minute.', 'rate_limited' => true];
            }
        }
        $digits = preg_replace('/\D+/', '', (string) $to);
        if (! preg_match('/^[1-9][0-9]{7,14}$/', $digits) || MaiBusinessLine::is($digits)) {
            return ['success' => false, 'error' => 'Enter the other phone number.'];
        }
        $text = trim((string) $text);
        if ($text === '' || ! MaiCapabilities::textReady()) {
            return ['success' => false, 'error' => 'Write a text message.'];
        }
        $conversation = $this->conversation($session, $digits);
        $this->apiGate = true;
        try {
            return $this->deliver($conversation, $text, $idempotencyKey, null);
        } finally {
            $this->apiGate = false;
            $this->apiKey = null;
        }
    }

    public function deliver(WhatsAppConversation $conversation, $text, $idempotencyKey, WhatsAppMessage $existing = null, array $quote = null)
    {
        $session = MaiSession::find((int) $conversation->mai_session_id);
        if (! $session || ! app(MaiProviderRouter::class)->usesMai($conversation)) {
            return ['success' => false, 'error' => 'This chat is not a Whats MAI session.'];
        }
        $key = substr(preg_replace('/[^A-Za-z0-9_.:-]/', '', (string) $idempotencyKey), 0, 80);
        if ($key === '') {
            return ['success' => false, 'error' => 'A send key is required.'];
        }
        $held = MaiOutbound::where('mai_session_id', $session->id)->where('idempotency_key', $key)->first();
        if ($held) {
            if ($held->status === 'pending' && $this->apiGate) {
                $blocked = $this->blocked($session, $this->apiKey);
                if ($blocked) {
                    $held->status = 'failed';
                    $held->last_error = substr((string) $blocked['error'], 0, 191);
                    $held->save();
                }
            }

            return $this->shape($held);
        }
        $contact = $conversation->contact;
        $to = $contact ? preg_replace('/\D+/', '', (string) $contact->normalized_phone) : '';
        $row = new MaiOutbound();
        $row->mai_session_id = $session->id;
        $row->idempotency_key = $key;
        $row->to_phone = $to;
        $row->body = $text;
        $row->status = 'pending';
        $row->save();
        $blocked = $this->apiGate ? $this->blocked($session, $this->apiKey) : null;
        if ($blocked) {
            $row->status = 'failed';
            $row->last_error = substr((string) $blocked['error'], 0, 191);
            $row->save();

            return $this->shape($row);
        }
        $payload = $this->link->send($session->worker_session_id, $to, $text, $quote);
        if (! empty($payload['timeout'])) {
            $row->status = 'uncertain';
            $row->last_error = 'Delivery was not confirmed. It was not sent again.';
            $row->save();

            return $this->shape($row);
        }
        if (empty($payload['success'])) {
            $row->status = 'failed';
            $row->last_error = isset($payload['error']) ? substr((string) $payload['error'], 0, 191) : 'Send failed.';
            $row->save();

            return $this->shape($row);
        }
        $row->status = 'sent';
        $row->provider_message_id = isset($payload['id']) ? substr((string) $payload['id'], 0, 128) : null;
        $row->save();
        if ($existing) {
            $existing->provider_message_id = $row->provider_message_id;
        }
        $this->copyToLinkedRecipient($session, $to, $text, (string) $row->provider_message_id);

        return $this->shape($row);
    }

    public function conversation(MaiSession $session, $digits)
    {
        return app(CloudTenantContext::class)->withoutIsolation(function () use ($session, $digits) {
            $contact = WhatsAppContact::where('normalized_phone', $digits)->first();
            if (! $contact) {
                $contact = new WhatsAppContact();
                $contact->normalized_phone = $digits;
                $contact->display_phone = '+'.$digits;
                $contact->save();
            }
            $conversation = WhatsAppConversation::where('contact_id', $contact->id)
                ->where('mai_session_id', $session->id)
                ->first();
            if (! $conversation) {
                $conversation = new WhatsAppConversation();
                $conversation->contact_id = $contact->id;
                $conversation->mai_session_id = $session->id;
                $conversation->mode = WhatsAppConversation::MODE_HUMAN;
                $conversation->status = WhatsAppConversation::STATUS_OPEN;
                $conversation->channel = WhatsAppConversation::CHANNEL_WHATSAPP;
                $conversation->unread_count = 0;
                $conversation->save();
            }

            return $conversation;
        });
    }

    protected function copyToLinkedRecipient(MaiSession $from, $to, $text, $providerId)
    {
        try {
            app(CloudTenantContext::class)->withoutIsolation(function () use ($from, $to, $text, $providerId) {
                $digits = preg_replace('/\D+/', '', (string) $to);
                if ($digits === '') {
                    return;
                }
                $plain = "REPLACE(REPLACE(REPLACE(IFNULL(%s, ''), '+', ''), ' ', ''), '-', '')";
                $other = MaiSession::where('id', '!=', $from->id)
                    ->where('status', '!=', 'UNLINKED')
                    ->where(function ($query) use ($digits, $plain) {
                        $query->whereRaw(sprintf($plain, 'phone_number').' = ?', [$digits])
                            ->orWhereRaw(sprintf($plain, 'expected_phone').' = ?', [$digits]);
                    })
                    ->first();
                if (! $other) {
                    return;
                }
                $fromDigits = preg_replace('/\D+/', '', (string) ($from->phone_number ?: $from->expected_phone));
                if ($fromDigits === '') {
                    return;
                }
                $conversation = $this->conversation($other, $fromDigits);
                if ($other->cloud_tenant_id && \Illuminate\Support\Facades\Schema::hasColumn('whatsapp_conversations', 'cloud_tenant_id')
                    && (int) $conversation->cloud_tenant_id !== (int) $other->cloud_tenant_id) {
                    $conversation->cloud_tenant_id = $other->cloud_tenant_id;
                    $conversation->save();
                }
                if ($providerId !== '' && WhatsAppMessage::where('conversation_id', $conversation->id)->where('provider_message_id', $providerId)->exists()) {
                    return;
                }
                $message = new WhatsAppMessage();
                $message->conversation_id = $conversation->id;
                $message->contact_id = $conversation->contact_id;
                $message->direction = WhatsAppMessage::DIR_IN;
                $message->type = 'TEXT';
                $message->provider_message_id = $providerId !== '' ? $providerId : null;
                $message->body = $text;
                $message->status = WhatsAppMessage::STATUS_DELIVERED;
                $message->sender_type = 'CONTACT';
                $message->save();
                $conversation->last_message = function_exists('mb_substr') ? mb_substr($text, 0, 191) : substr($text, 0, 191);
                $conversation->last_activity_at = \Carbon\Carbon::now();
                $conversation->last_incoming_at = \Carbon\Carbon::now();
                $conversation->unread_count = (int) $conversation->unread_count + 1;
                $conversation->save();
            });
        } catch (\Throwable $e) {
        }
    }

    public function sendFile(MaiSession $session, $to, $kind, $name, $mime, $data, $idempotencyKey, $apiKey = null)
    {
        if (! in_array($kind, ['image', 'document'], true)) {
            return ['success' => false, 'error' => 'Send text, an image, or a document.'];
        }
        $raw = base64_decode((string) $data, true);
        if ($raw === false || MaiMediaLimit::tooLarge(strlen($raw))) {
            return ['success' => false, 'error' => 'Send a file up to '.MaiMediaLimit::label().'.'];
        }
        if ($session->status !== 'CONNECTED') {
            return ['success' => false, 'error' => 'Approve WhatsApp on the phone before sending.'];
        }
        $this->apiKey = $apiKey;
        $blocked = $this->blocked($session, $this->apiKey);
        if ($blocked) {
            return $blocked;
        }
        $key = substr(preg_replace('/[^A-Za-z0-9_.:-]/', '', (string) $idempotencyKey), 0, 80);
        if ($key === '') {
            return ['success' => false, 'error' => 'A send key is required.'];
        }
        $held = MaiOutbound::where('mai_session_id', $session->id)->where('idempotency_key', $key)->first();
        if ($held) {
            return $this->shape($held);
        }
        if ($this->overRate($session, $key)) {
            return ['success' => false, 'status' => 'failed', 'error' => 'This session has reached its send limit for this minute.', 'rate_limited' => true];
        }
        $digits = preg_replace('/\D+/', '', (string) $to);
        $row = new MaiOutbound();
        $row->mai_session_id = $session->id;
        $row->idempotency_key = $key;
        $row->to_phone = $digits;
        $row->body = $kind.': '.substr((string) $name, 0, 160);
        $row->status = 'pending';
        $row->save();
        $blocked = $this->blocked($session, $apiKey);
        if ($blocked) {
            $row->status = 'failed';
            $row->last_error = substr((string) $blocked['error'], 0, 191);
            $row->save();

            return $this->shape($row);
        }
        $payload = $this->link->sendFile($session->worker_session_id, $digits, $kind, $name, $mime, $raw);
        if (empty($payload['success'])) {
            $row->status = 'failed';
            $row->last_error = isset($payload['error']) ? substr((string) $payload['error'], 0, 191) : 'Send failed.';
            $row->save();

            return $this->shape($row);
        }
        $row->status = 'sent';
        $row->provider_message_id = isset($payload['id']) ? substr((string) $payload['id'], 0, 128) : null;
        $row->save();

        return $this->shape($row);
    }

    protected function queued(MaiSession $session, $idempotencyKey)
    {
        $key = substr(preg_replace('/[^A-Za-z0-9_.:-]/', '', (string) $idempotencyKey), 0, 80);
        if ($key === '') {
            return null;
        }

        return MaiOutbound::where('mai_session_id', $session->id)->where('idempotency_key', $key)->where('status', 'pending')->first();
    }

    protected function blocked(MaiSession $session, $apiKey)
    {
        if ($apiKey && ! empty($apiKey->revoked_at)) {
            return ['success' => false, 'status' => 'failed', 'error' => 'The API token was revoked before sending.'];
        }
        if (! MaiEntitlement::canSend($session)) {
            return ['success' => false, 'status' => 'failed', 'error' => 'This subscription cannot send through the API.'];
        }

        return null;
    }

    protected function overRate(MaiSession $session, $idempotencyKey)
    {
        $key = substr(preg_replace('/[^A-Za-z0-9_.:-]/', '', (string) $idempotencyKey), 0, 80);
        if ($key !== '' && MaiOutbound::where('mai_session_id', $session->id)->where('idempotency_key', $key)->exists()) {
            return false;
        }
        $limit = (int) config('cloud.mai_customer.sends_per_minute', 60);
        if ($limit < 1) {
            return false;
        }
        $bucket = 'mai.send.'.(int) $session->id.'.'.date('YmdHi');
        $count = (int) \Illuminate\Support\Facades\Cache::get($bucket, 0);
        if ($count >= $limit) {
            return true;
        }
        \Illuminate\Support\Facades\Cache::put($bucket, $count + 1, now()->addMinutes(2));

        return false;
    }

    protected function shape(MaiOutbound $row)
    {
        if ($row->status === 'uncertain') {
            return [
                'success' => false,
                'uncertain' => true,
                'status' => 'uncertain',
                'error' => $row->last_error,
            ];
        }
        if ($row->status === 'sent') {
            return [
                'success' => true,
                'status' => 'sent',
                'msg_id' => $row->provider_message_id,
            ];
        }

        return [
            'success' => false,
            'status' => $row->status,
            'error' => $row->last_error ?: 'Send failed.',
        ];
    }
}
