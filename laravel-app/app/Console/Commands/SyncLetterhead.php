<?php

namespace App\Console\Commands;

use App\Support\Letterhead;
use Illuminate\Console\Command;

class SyncLetterhead extends Command
{
    protected $signature = 'app:sync-letterhead {--default : Use the Beyond letterhead for letters with no organization}';

    protected $description = 'Install Beyond letterhead header/footer into public/logo and General Settings';

    public function handle()
    {
        $assets = $this->option('default')
            ? Letterhead::restoreDefault()
            : Letterhead::ensureSynced();
        $this->info('Letterhead header: '.($assets['header_file'] ?: 'missing'));
        $this->info('Letterhead footer: '.($assets['footer_file'] ?: 'missing'));
        $this->info('Watermark: '.($assets['watermark_file'] ?: 'missing'));

        if (! empty($assets['header_file']) && strpos($assets['header_file'], 'beyond-letterhead') === false) {
            $this->warn('Header is not the Beyond branding file — check General Settings uploads.');
        }

        return ($assets['has_header'] && $assets['has_footer']) ? 0 : 1;
    }
}
