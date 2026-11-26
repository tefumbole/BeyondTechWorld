<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsAppConversationService;
use App\WhatsApp\WhatsAppConversation;
use Illuminate\Console\Command;

class RecountWhatsAppUnread extends Command
{
    protected $signature = 'whatsapp:recount-unread';

    protected $description = 'Set each chat badge to incoming messages the staff has not read';

    public function handle(WhatsAppConversationService $conversations)
    {
        $fixed = app(\App\Services\Cloud\CloudTenantContext::class)->withoutIsolation(function () use ($conversations) {
            $fixed = 0;
            WhatsAppConversation::where('unread_count', '>', 0)
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($conversations, &$fixed) {
                    foreach ($rows as $conversation) {
                        $before = (int) $conversation->unread_count;
                        $left = $conversations->recountUnread($conversation);
                        if ($before !== $left) {
                            $conversation->save();
                            $fixed++;
                        }
                    }
                });

            return $fixed;
        });

        $this->info('Unread badges aligned. '.$fixed.' chats updated.');

        return 0;
    }
}
