<?php

namespace App\Console\Commands;

use App\Support\AppVersion;
use Illuminate\Console\Command;

class SyncAppVersion extends Command
{
    protected $signature = 'app:sync-version';

    protected $description = 'Sync General Settings application version from laravel-app/VERSION';

    public function handle()
    {
        $version = AppVersion::syncToSettings();
        $this->info('Application version synced: '.$version);

        return 0;
    }
}
