<?php

namespace App\Console\Commands;

use App\Services\Mai\MaiIdentity;
use App\WhatsApp\WhatsAppConversation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RepairMaiLabels extends Command
{
    protected $signature = 'mai:repair-labels {--dry-run : Report changes without writing} {--session= : One mai_sessions id}';

    protected $description = 'Rebuild Whats MAI labels from that connection only';

    public function handle()
    {
        if (! Schema::hasTable('whatsapp_conversations') || ! Schema::hasColumn('whatsapp_conversations', 'mai_session_id')) {
            $this->line('rebuilt=0 cleared=0 kept=0 dry=yes');

            return 0;
        }
        $dry = (bool) $this->option('dry-run');
        $sessionId = (int) $this->option('session');
        $query = WhatsAppConversation::with('contact')->whereNotNull('mai_session_id');
        if ($sessionId > 0) {
            $query->where('mai_session_id', $sessionId);
        }
        $rebuilt = 0;
        $cleared = 0;
        $kept = 0;
        $identity = app(MaiIdentity::class);
        foreach ($query->get() as $conversation) {
            $next = $identity->display($conversation, $conversation->contact);
            $current = trim((string) $conversation->peer_label);
            if ($current === $next) {
                $kept++;
                continue;
            }
            if ($identity->trusted($conversation, $conversation->contact) !== '') {
                $rebuilt++;
            } else {
                $cleared++;
            }
            if (! $dry) {
                $conversation->peer_label = $next;
                $conversation->save();
            }
        }
        $this->line('rebuilt='.$rebuilt.' cleared='.$cleared.' kept='.$kept.' dry='.($dry ? 'yes' : 'no'));

        return 0;
    }
}
