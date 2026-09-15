<?php

namespace App\Console\Commands;

use App\Services\Wealth\WealthIncomeSync;
use Illuminate\Console\Command;

class WealthSyncIncome extends Command
{
    protected $signature = 'wealth:sync-income';

    protected $description = 'Idempotently sync paid completed sales into the Wealth Manager income ledger';

    public function handle(WealthIncomeSync $sync)
    {
        $n = $sync->syncAllPaidSales();
        $this->info('Synced '.$n.' paid sales.');

        return 0;
    }
}
