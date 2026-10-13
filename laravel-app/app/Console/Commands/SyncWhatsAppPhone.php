<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsAppWebhookProcessor;
use App\WhatsApp\WhatsAppWebhookEvent;
use Illuminate\Console\Command;

class SyncWhatsAppPhone extends Command
{
    protected $signature = 'whatsapp:sync-phone';

    protected $description = 'Apply stored phone chat updates so the inbox matches the linked WhatsApp';

    public function handle(WhatsAppWebhookProcessor $processor)
    {
        @set_time_limit(0);
        $types = ['chats.update', 'chats.upsert', 'message.sent', 'messages.upsert'];
        $count = 0;
        WhatsAppWebhookEvent::whereIn('event_type', $types)
            ->where('status', WhatsAppWebhookEvent::IGNORED)
            ->orderBy('id')
            ->chunkById(200, function ($events) use ($processor, &$count) {
                foreach ($events as $event) {
                    $processor->process($event);
                    $count++;
                }
                $this->line('Applied '.$count);
            });

        $this->info('Phone chat sync finished. '.$count.' events applied.');

        return 0;
    }
}
