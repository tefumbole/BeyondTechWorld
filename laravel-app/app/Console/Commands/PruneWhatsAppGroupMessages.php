<?php

namespace App\Console\Commands;

use App\WhatsApp\WhatsAppGroup;
use App\WhatsApp\WhatsAppGroupMessage;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PruneWhatsAppGroupMessages extends Command
{
    protected $signature = 'whatsapp:prune-group-messages';

    protected $description = 'Delete expired raw WhatsApp group messages. Confirmed actions keep their source id.';

    public function handle()
    {
        if (! Schema::hasTable('whatsapp_group_messages')) {
            return 0;
        }
        $removed = 0;
        foreach (WhatsAppGroup::all() as $group) {
            $days = (int) $group->raw_retention_days;
            if ($days < 1) {
                continue;
            }
            $cut = Carbon::now()->subDays($days);
            $removed += WhatsAppGroupMessage::where('group_id', $group->id)->where('message_at', '<', $cut)->delete();
        }
        $this->info('Removed '.$removed.' expired group message(s).');

        return 0;
    }
}
