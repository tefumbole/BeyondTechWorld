<?php

namespace App\Console\Commands;

use App\Services\Property\RentBillingService;
use Illuminate\Console\Command;

class GenerateRentObligations extends Command
{
    protected $signature = 'property:generate-rent {--date=}';

    protected $description = 'Create missing rent obligations for active tenancies';

    public function handle(RentBillingService $billing)
    {
        $created = $billing->generate($this->option('date'));
        $this->info('Rent obligations created: '.$created);

        return 0;
    }
}
