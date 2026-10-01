<?php

namespace App\Jobs;

use App\Services\WhatsApp\GroupContactExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ResolveWhatsAppContactNamesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 80;

    public $jid;

    public function __construct($jid = null)
    {
        $this->jid = $jid ? (string) $jid : null;
    }

    public function handle(GroupContactExportService $groups)
    {
        if ($this->jid === null) {
            Cache::put('wa_contact_names_global', 1, 120);
        }
        $remaining = $groups->resolveContactNames($this->jid, 4);
        if ($remaining > 0) {
            static::dispatch($this->jid)->delay(2)->onConnection('database')->onQueue('whatsapp');

            return;
        }
        if ($this->jid && Cache::add('wa_contact_names_global', 1, 120)) {
            static::dispatch(null)->delay(2)->onConnection('database')->onQueue('whatsapp');
        }
    }
}
