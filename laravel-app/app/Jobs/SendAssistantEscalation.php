<?php

namespace App\Jobs;

use App\Services\Assistant\AssistantHandoverService;
use App\WhatsApp\WhatsAppConversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAssistantEscalation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $retryAfter = 45;

    public $timeout = 30;

    protected $conversationId;

    protected $details;

    public function __construct($conversationId, array $details)
    {
        $this->conversationId = (int) $conversationId;
        $this->details = $details;
        $this->onQueue('whatsapp');
    }

    public function handle(AssistantHandoverService $handover)
    {
        $conversation = WhatsAppConversation::find($this->conversationId);
        if (! $conversation) {
            return;
        }
        $tenantId = null;
        if (\Illuminate\Support\Facades\Schema::hasColumn('whatsapp_conversations', 'cloud_tenant_id')) {
            $tenantId = $conversation->cloud_tenant_id;
        }
        $send = function () use ($handover) {
            return $handover->retrySend($this->conversationId, $this->details);
        };
        if ($tenantId) {
            $result = app(\App\Services\Cloud\CloudTenantContextRunner::class)->run($tenantId, $send);
        } else {
            $result = $send();
        }
        if (! empty($result['retrying'])) {
            throw new \RuntimeException('escalation_not_submitted');
        }
    }
}
