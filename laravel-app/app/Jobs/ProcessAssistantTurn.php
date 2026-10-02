<?php

namespace App\Jobs;

use App\Services\Assistant\BeyondAssistantService;
use App\WhatsApp\WhatsAppMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAssistantTurn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public $timeout = 45;

    protected $messageId;

    public function __construct($messageId)
    {
        $this->messageId = (int) $messageId;
    }

    public function handle(BeyondAssistantService $assistant)
    {
        $message = WhatsAppMessage::with('conversation.contact')->find($this->messageId);
        if (! $message || $message->direction !== WhatsAppMessage::DIR_IN) {
            return;
        }
        $tenantId = null;
        if (\Illuminate\Support\Facades\Schema::hasColumn('whatsapp_conversations', 'cloud_tenant_id')) {
            $tenantId = app(\App\Services\Cloud\CloudTenantContext::class)->withoutIsolation(function () use ($message) {
                $conversation = \App\WhatsApp\WhatsAppConversation::find($message->conversation_id);

                return $conversation ? $conversation->cloud_tenant_id : null;
            });
            if (! $tenantId) {
                \Illuminate\Support\Facades\Log::warning('[beyond-assistant] turn has no company', [
                    'message_id' => $this->messageId,
                ]);

                return;
            }
        }
        try {
            $run = function () use ($assistant, $message) {
                $assistant->handleIncoming($message);
            };
            if ($tenantId) {
                app(\App\Services\Cloud\CloudTenantContextRunner::class)->run($tenantId, $run);
            } else {
                $run();
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[beyond-assistant] turn failed', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
