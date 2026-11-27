<?php

namespace App\Console\Commands;

use App\Services\Mai\MaiIdentity;
use App\WhatsApp\WhatsAppContact;
use App\WhatsApp\WhatsAppConversation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
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
            $trusted = Schema::hasTable('mai_peers') ? $identity->trusted($conversation, $conversation->contact) : '';
            $current = trim((string) $conversation->peer_label);
            $phone = preg_replace('/\D/', '', (string) optional($conversation->contact)->normalized_phone);
            $leaked = $current !== '' && $current !== $trusted && $this->leaked($conversation, $current, $phone);
            if ($trusted !== '' && $current !== $trusted) {
                $rebuilt++;
                if (! $dry) {
                    $conversation->peer_label = $trusted;
                    $conversation->save();
                    if ($leaked) {
                        $this->forgetPhone($phone);
                    }
                }
                continue;
            }
            if ($leaked) {
                $cleared++;
                if (! $dry) {
                    $conversation->peer_label = null;
                    $conversation->save();
                    $this->forgetPhone($phone);
                }
                continue;
            }
            $kept++;
        }
        $this->line('rebuilt='.$rebuilt.' cleared='.$cleared.' kept='.$kept.' dry='.($dry ? 'yes' : 'no'));

        return 0;
    }

    protected function leaked(WhatsAppConversation $conversation, $label, $phone)
    {
        $cached = $phone !== '' ? Cache::get('wa.book.'.$phone) : null;
        if (is_string($cached) && trim($cached) === $label) {
            return true;
        }
        $contact = $conversation->contact;
        if ($contact && trim((string) $contact->wa_name) === $label) {
            return true;
        }

        return WhatsAppContact::where('wa_name', $label)
            ->where('id', '!=', (int) $conversation->contact_id)
            ->exists();
    }

    protected function forgetPhone($phone)
    {
        if ($phone === '') {
            return;
        }
        Cache::forget('wa.book.'.$phone);
    }
}
