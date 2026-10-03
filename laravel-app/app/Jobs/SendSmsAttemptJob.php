<?php

namespace App\Jobs;

use App\Cloud\CloudTenant;
use App\Services\Cloud\CloudTenantContext;
use App\Services\Messaging\MessagingHub;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsAttemptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $cloudTenantId;

    public $attemptId;

    public function __construct($cloudTenantId, $attemptId)
    {
        $this->cloudTenantId = (int) $cloudTenantId;
        $this->attemptId = (int) $attemptId;
    }

    public function handle(MessagingHub $hub, CloudTenantContext $context)
    {
        try {
            $tenant = CloudTenant::find($this->cloudTenantId);
            if ($tenant) {
                $context->set($tenant);
            }
            $hub->executeSmsAttempt($this->attemptId);
        } finally {
            $context->clear();
        }
    }
}
