<?php

namespace App\Services\WhatsApp;

use App\WhatsApp\WhatsAppCall;
use App\WhatsApp\WhatsAppWebhookEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookProcessor
{
    protected $parser;
    protected $conversations;
    protected $identity;

    public function __construct(
        WaSenderEventParser $parser,
        WhatsAppConversationService $conversations,
        WhatsAppIdentityService $identity
    ) {
        $this->parser = $parser;
        $this->conversations = $conversations;
        $this->identity = $identity;
    }

    public function process(WhatsAppWebhookEvent $event)
    {
        $event->status = WhatsAppWebhookEvent::PROCESSING;
        $event->attempts = (int) $event->attempts + 1;
        $event->processing_started_at = now();
        $event->save();

        try {
            $parsed = $this->parser->parse($event->payloadArray());
            $type = $parsed['type'];

            if (! empty($parsed['is_group']) || in_array($type, ['messages-group.received', 'messages.group.received', 'message-group.received'], true)) {
                if (! empty($parsed['from_me'])) {
                    $event->status = WhatsAppWebhookEvent::IGNORED;
                    $event->processed_at = now();
                    $event->save();

                    return $event;
                }
                app(GroupIngestService::class)->ingest($parsed);
                $event->status = WhatsAppWebhookEvent::PROCESSED;
            } elseif ($type === 'poll.results') {
                if (! empty($parsed['phone']) && ! empty($parsed['body'])) {
                    $this->conversations->recordIncoming($parsed);
                }
                $event->status = WhatsAppWebhookEvent::PROCESSED;
            } elseif (in_array($type, ['messages.received', 'message.received', 'messages.upsert', 'message.upsert'], true)) {
                if (! empty($parsed['from_me'])) {
                    $event->status = WhatsAppWebhookEvent::IGNORED;
                    $event->processed_at = now();
                    $event->save();

                    return $event;
                }
                $this->conversations->recordIncoming($parsed);
                $event->status = WhatsAppWebhookEvent::PROCESSED;
            } elseif (in_array($type, ['messages.update', 'message.update', 'message-receipt.update', 'messages.receipt', 'message.receipt'], true)) {
                $this->conversations->applyStatus($parsed['message_id'], $parsed['status']);
                $event->status = WhatsAppWebhookEvent::PROCESSED;
            } elseif (in_array($type, ['call', 'calls.received'], true)) {
                $this->recordCall($parsed);
                $event->status = WhatsAppWebhookEvent::PROCESSED;
            } else {
                $event->status = WhatsAppWebhookEvent::IGNORED;
            }

            $event->processed_at = now();
            $event->processing_error = null;
            $event->save();
        } catch (\Throwable $e) {
            Log::warning('[whatsapp-hub] webhook processing failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
            $event->status = WhatsAppWebhookEvent::FAILED;
            $event->processing_error = $e->getMessage();
            $event->save();
        }

        return $event;
    }

    protected function recordCall(array $parsed)
    {
        $call = $parsed['call'] ?: [];
        $providerId = isset($call['id']) ? (string) $call['id'] : null;
        if ($providerId) {
            $existing = WhatsAppCall::where('provider_call_id', $providerId)->first();
            if ($existing) {
                return $existing;
            }
        }

        $contact = null;
        if (! empty($parsed['phone'])) {
            $contact = $this->conversations->findOrCreateContact($parsed['phone']);
        }

        $offerStatus = strtolower((string) ($call['status'] ?? 'offer'));
        $status = WhatsAppCall::RECEIVED;
        if (in_array($offerStatus, ['timeout', 'reject', 'rejected', 'missed'], true)) {
            $status = WhatsAppCall::MISSED;
        }

        $calledAt = now();
        if (! empty($call['date'])) {
            try {
                $calledAt = Carbon::parse($call['date']);
            } catch (\Throwable $e) {
            }
        }

        return WhatsAppCall::create([
            'provider_call_id' => $providerId,
            'contact_id' => $contact ? $contact->id : null,
            'caller_phone' => $contact ? $contact->display_phone : ($parsed['phone'] ?? null),
            'call_type' => ! empty($call['is_video']) ? 'video' : 'audio',
            'status' => $status,
            'called_at' => $calledAt,
        ]);
    }
}
