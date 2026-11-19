<?php

namespace App\Console\Commands;

use App\Services\Antivirus\AntivirusRequestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SendUnsignedAntivirusRequests extends Command
{
    protected $signature = 'antivirus:send-unsigned';

    protected $description = 'Send each unsigned antivirus signature request once';

    public function handle(AntivirusRequestService $requests)
    {
        $flag = 'antivirus-unsigned-signature-sent.txt';
        if (Storage::disk('local')->exists($flag)) {
            $this->info('Unsigned antivirus requests were already sent once.');

            return 0;
        }
        $result = $requests->sendUnsignedForSignature();
        Storage::disk('local')->put($flag, now()->toDateTimeString());
        $this->info('Approver signature requests sent: '.$result['approvers']);
        $this->info('Chief signature requests sent: '.$result['chiefs']);
        $this->info('Skipped: '.$result['skipped']);

        return 0;
    }
}
