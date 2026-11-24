<?php

namespace App\Console\Commands;

use App\Services\Antivirus\AntivirusRequestService;
use Illuminate\Console\Command;

class SendUnsignedAntivirusRequests extends Command
{
    protected $signature = 'antivirus:send-unsigned';

    protected $description = 'Send each unsigned antivirus signature request once';

    public function handle(AntivirusRequestService $requests)
    {
        $requests->sendUnsignedForSignature();
        $requests->notifyRequestersSentForApproval();
        $this->info('Antivirus reminders are off. Nothing was sent to approvers, signers, or requesters.');

        return 0;
    }
}
