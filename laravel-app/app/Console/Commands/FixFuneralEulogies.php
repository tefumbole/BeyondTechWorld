<?php

namespace App\Console\Commands;

use App\Services\FuneralPledgeService;
use Illuminate\Console\Command;

class FixFuneralEulogies extends Command
{
    protected $signature = 'funeral:fix-eulogies';

    protected $description = 'Rename a submitted eulogy author and refresh signature PNGs';

    public function handle(FuneralPledgeService $service)
    {
        $renamed = $service->renameEulogyAuthor('Ankly Cymilliene', 'Ngwayu Claude');
        $this->info('Renamed eulogy authors: '.$renamed);

        $updated = $service->refreshEulogySignatures();
        $this->info('Refreshed signature PNGs: '.$updated);

        return 0;
    }
}
