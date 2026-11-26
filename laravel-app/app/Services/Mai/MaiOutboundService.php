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

    public function __construct(MaiLinkClient $link)
    {
        $this->link = $link;
    }

    public function sendTo(MaiSession $session, $to, $text, $idempotencyKey)
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
        $digits = preg_replace('/\D+/', '', (string) $to);
        if (! preg_match('/^[1-9][0-9]{7,14}$/', $digits) || MaiBusinessLine::is($digits)) {
            return ['success' => false, 'error' => 'Enter the other phone number.'];
        }
        $text = trim((string) $text);
        if ($text === '' || ! MaiCapabilities::textReady()) {
            return ['success' => false, 'error' => 'Write a text message.'];
        }
        $conversation = $this->conversation($session, $digits);

        return $this->deliver($conversation, $text, $idempotencyKey, null);
    }

    public function deliver(WhatsAppConversation $conversation, $text, $idempotencyKey, WhatsAppMessage $existing = null)
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
        $payload = $this->link->send($session->worker_session_id, $to, $text);
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
