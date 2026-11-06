<?php

namespace App\Console\Commands;

use App\Services\EventAutoPay;
use Illuminate\Console\Command;

class PayScheduledEventCrew extends Command
{
    protected $signature = 'events:pay-scheduled';

    protected $description = 'Pay event engineers when an approved payment time is reached';

    public function handle(EventAutoPay $pay)
    {
        $pay->runDue();

        return 0;
    }
}
