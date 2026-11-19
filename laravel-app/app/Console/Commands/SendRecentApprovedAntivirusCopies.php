<?php

namespace App\Console\Commands;

use App\Services\Antivirus\AntivirusRequestService;
use Illuminate\Console\Command;

class SendRecentApprovedAntivirusCopies extends Command
{
    protected $signature = 'antivirus:send-approved-copies';

    protected $description = 'Send unsent antivirus approval copies from the last 72 hours';

    public function handle(AntivirusRequestService $requests)
    {
        $result = $requests->sendRecentApprovedCopies(72);
        $this->info('Approval copies sent: '.$result['copies']);
        $this->info('Skipped: '.$result['skipped']);

        return 0;
    }
}
