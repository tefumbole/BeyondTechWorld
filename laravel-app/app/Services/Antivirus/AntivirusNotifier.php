<?php

namespace App\Services\Antivirus;

use App\Antivirus\AntivirusNotice;
use App\Jobs\SendAntivirusWhatsApp;
use App\Services\Cloud\CloudTenantContext;

class AntivirusNotifier
{
    public function queue($noticeId)
    {
        $notice = AntivirusNotice::find($noticeId);
        if (! $notice) {
            return;
        }
        $tenantId = $notice->cloud_tenant_id ?: app(CloudTenantContext::class)->id();
        SendAntivirusWhatsApp::dispatch($tenantId, $notice->id);
    }
}
