<?php

namespace App\Console\Commands;

use App\Services\Cloud\CloudOnboardingGate;
use Illuminate\Console\Command;

class IssueCloudOnboardingGate extends Command
{
    protected $signature = 'cloud:onboarding-gate {--minutes=20} {--file=}';

    protected $description = 'Write a one-time onboarding validation token to a file. Does not print it.';

    public function handle(CloudOnboardingGate $gate)
    {
        $file = (string) $this->option('file');
        if ($file === '') {
            $this->error('Pass --file so the token is not printed.');

            return 1;
        }
        $token = $gate->issue((int) $this->option('minutes'));
        if (file_put_contents($file, $token) === false) {
            return 1;
        }
        chmod($file, 0600);
        $this->line('issued');

        return 0;
    }
}
