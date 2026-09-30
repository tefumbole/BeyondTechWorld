<?php

namespace App\Jobs;

use App\Services\WhatsApp\GroupContactExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ResolveWhatsAppGroupsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 80;

    public function handle(GroupContactExportService $groups)
    {
        Cache::put('wa_group_resolve', 1, 80);
        $remaining = $groups->resolveNext(5);
        if ($remaining > 0) {
            static::dispatch()->delay(70)->onConnection('database')->onQueue('whatsapp');
        }
    }
}
