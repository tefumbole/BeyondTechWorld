<?php

namespace App\Console\Commands;

use App\Services\Cloud\CloudSubscriptionService;
use Illuminate\Console\Command;

class ProcessCloudSubscriptions extends Command
{
    protected $signature = 'cloud:process-subscriptions';

    protected $description = 'Expire trials, close paid periods, and record subscription reminders. Safe to run twice.';

    public function handle(CloudSubscriptionService $subscriptions)
    {
        $changed = $subscriptions->processDue(now());
        $this->line('changed='.$changed);

        return 0;
    }
}
