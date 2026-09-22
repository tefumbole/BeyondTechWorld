<?php

namespace App\Console\Commands;

use App\WhatsApp\WhatsAppWebhookEvent;
use Carbon\Carbon;
use Illuminate\Console\Command;

class WhatsAppPruneWebhookEvents extends Command
{
    protected $signature = 'whatsapp:prune-webhooks {--days=}';

    protected $description = 'Remove old raw WhatsApp webhook payloads (does not delete conversations or messages)';

    public function handle()
    {
        $days = (int) ($this->option('days') ?: config('services.whatsapp.webhook_retention_days', 30));
        if ($days < 1) {
            $this->warn('Retention days must be at least 1.');

            return 1;
        }

        $cutoff = Carbon::now()->subDays($days);
        $deleted = WhatsAppWebhookEvent::where(function ($q) use ($cutoff) {
            $q->where('received_at', '<', $cutoff)
                ->orWhere(function ($q2) use ($cutoff) {
                    $q2->whereNull('received_at')->where('created_at', '<', $cutoff);
                });
        })->delete();

        $this->info('Deleted '.$deleted.' webhook event(s) older than '.$days.' day(s). Conversations and messages were kept.');

        return 0;
    }
}