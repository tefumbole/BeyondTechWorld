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
        try {
            $assistant->handleIncoming($message);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[beyond-assistant] turn failed', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
