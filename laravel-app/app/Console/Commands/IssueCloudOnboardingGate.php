<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class IssueCloudOnboardingGate extends Command
{
    protected $signature = 'cloud:onboarding-gate {--minutes=20} {--file=}';

    protected $description = 'Retired. Public company signup is not opened by this command.';

    public function handle()
    {
        $this->error('The temporary onboarding gate has been removed.');

        return 1;
    }
}
