<?php

namespace App\Jobs;

use App\Services\WhatsApp\WhatsAppWebhookProcessor;
use App\WhatsApp\WhatsAppWebhookEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWasenderWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 60;

    protected $eventId;

    public function __construct($eventId)
    {
        $this->eventId = (int) $eventId;
    }

    public function handle(WhatsAppWebhookProcessor $processor)
    {
        $event = WhatsAppWebhookEvent::find($this->eventId);
        if (! $event) {
            return;
        }
        if (in_array($event->status, [WhatsAppWebhookEvent::PROCESSED, WhatsAppWebhookEvent::IGNORED], true)) {
            return;
        }

        $processor->process($event);
    }
}
