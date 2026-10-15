<?php

namespace App\Console\Commands;

use App\Hotspot\HotspotEnforcer;
use Illuminate\Console\Command;

class HotspotEnforceCommand extends Command
{
    protected $signature = 'hotspot:enforce';

    protected $description = 'Expire hotspot access, disconnect sessions, and send one reminder';

    public function handle(HotspotEnforcer $enforcer)
    {
        $enforcer->refreshUsage();
        $enforcer->retryOffline();
        $enforcer->run();
        $this->info('Hotspot enforcement finished.');
    }
}
