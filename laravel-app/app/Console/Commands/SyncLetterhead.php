<?php

namespace App\Console\Commands;

use App\Support\Letterhead;
use Illuminate\Console\Command;

class SyncLetterhead extends Command
{
    protected $signature = 'app:sync-letterhead';

    protected $description = 'Ensure quotation/letter header & footer images exist under public/logo';

    public function handle()
    {
        $assets = Letterhead::ensureSynced();
        $this->info('Letterhead header: '.($assets['header_file'] ?: 'missing'));
        $this->info('Letterhead footer: '.($assets['footer_file'] ?: 'missing'));
        $this->info('Watermark: '.($assets['watermark_file'] ?: 'missing'));

        return ($assets['has_header'] && $assets['has_footer']) ? 0 : 1;
    }
}
