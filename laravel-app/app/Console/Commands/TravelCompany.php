<?php

namespace App\Console\Commands;

use App\Travel\TravelContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TravelCompany extends Command
{
    protected $signature = 'travel:company {tenantId} {--disable}';

    protected $description = 'Allow or hide Epic Travels for one company. Does not turn on the site-wide switch.';

    public function handle()
    {
        $tenantId = (int) $this->argument('tenantId');
        if ($tenantId < 1) {
            $this->error('Give the company id.');

            return 1;
        }
        if ($this->option('disable')) {
            DB::table('travel_settings')->updateOrInsert(
                ['cloud_tenant_id' => $tenantId],
                ['enabled' => 0, 'updated_at' => now(), 'created_at' => now()]
            );
            $this->info('Epic Travels is hidden for company '.$tenantId.'.');

            return 0;
        }
        TravelContext::allow($tenantId);
        $this->info('Epic Travels is allowed for company '.$tenantId.'.');
        $this->line('The pages stay hidden until TRAVEL_ENABLED=true.');
        $this->line('Payments stay in the sandbox until TRAVEL_SANDBOX=false.');

        return 0;
    }
}
