<?php

namespace App\Console\Commands;

use App\Mai\MaiSession;
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
            $this->error('Whats MAI conversations are not available.');

            return 1;
        }
        $sessionId = (int) $this->option('session');
        if ($sessionId <= 0) {
            $this->error('Pass --session=ID so the repair stays on one connection.');

            return 1;
        }
        $session = MaiSession::query()->find($sessionId);
        if (! $session) {
            $this->error('No MAI session '.$sessionId.'.');

            return 1;
        }
        if (! $session->user_id && ! $session->cloud_tenant_id) {
            $this->error('Session '.$sessionId.' has no account or company, so the repair stopped.');

            return 1;
        }
        $dry = (bool) $this->option('dry-run');
        $owner = $session->cloud_tenant_id ? 'company '.$session->cloud_tenant_id : 'account '.$session->user_id;
        $this->line('session='.$session->id.' owner='.$owner.' dry='.($dry ? 'yes' : 'no'));
        $query = WhatsAppConversation::withoutGlobalScopes()->where('mai_session_id', $session->id);
        $examined = (clone $query)->count();
        $this->line('examined='.$examined);
        $rebuilt = 0;
        $cleared = 0;
        $kept = 0;
        $identity = app(MaiIdentity::class);
        foreach ($query->with('contact')->get() as $conversation) {
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
            $this->line('propose id='.$conversation->id.' from='.$current.' to='.$next);
            if (! $dry) {
                $conversation->peer_label = $next;
                $conversation->save();
            }
        }
        $this->line('rebuilt='.$rebuilt.' cleared='.$cleared.' kept='.$kept.' dry='.($dry ? 'yes' : 'no'));

        return 0;
    }
}
