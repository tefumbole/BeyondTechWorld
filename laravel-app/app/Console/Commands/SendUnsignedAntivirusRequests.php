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
        $result = $requests->sendUnsignedForSignature();
        $requesters = $requests->notifyRequestersSentForApproval();
        $this->info('Approver signature requests sent: '.$result['approvers']);
        $this->info('Chief signature requests sent: '.$result['chiefs']);
        $this->info('Requester notices sent: '.$requesters['sent']);
        $this->info('Skipped: '.($result['skipped'] + $requesters['skipped']));

        return 0;
    }
}
