<?php

namespace App\Jobs;

use App\Services\AnnouncementNotificationService;
use App\WaAnnouncement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWaAnnouncementBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 80;

    protected $announcementId;

    public function __construct($announcementId)
    {
        $this->announcementId = (string) $announcementId;
    }

    public function handle(AnnouncementNotificationService $notify)
    {
        $announcement = WaAnnouncement::find($this->announcementId);
        if (! $announcement || $announcement->status === 'deleted') {
            return;
        }
        $send = function () use ($notify, $announcement) {
            $tenant = app(\App\Services\Cloud\CloudTenantContext::class)->tenant();
            if ($tenant && $tenant->type === \App\Cloud\CloudTenantType::CUSTOMER
                && \Illuminate\Support\Facades\Schema::hasTable('cloud_subscriptions')
                && ! app(\App\Services\Cloud\CloudModuleAccessService::class)->canWriteCapability($tenant, 'messaging')) {
                return;
            }
            $remaining = $notify->deliverContactBatch($announcement->fresh(), 5);
            if ($remaining > 0) {
                static::dispatch($this->announcementId)->onConnection('database')->onQueue('whatsapp');

                return;
            }
            $fresh = $announcement->fresh();
            $results = $fresh->send_results_json ? json_decode($fresh->send_results_json, true) : [];
            $okCount = 0;
            $total = is_array($results) ? count($results) : 0;
            if (is_array($results)) {
                foreach ($results as $row) {
                    if (! empty($row['ok'])) {
                        $okCount++;
                    }
                }
            }
            $fresh->status = 'sent';
            $fresh->whatsapp_status = $okCount === 0 ? 'pending' : ($okCount < $total ? 'partial' : 'sent');
            $fresh->is_scheduled = false;
            $fresh->save();
        };
        $tenantId = app(\App\Services\Cloud\CloudWhatsAppConnectionResolver::class)->soleTenantId();
        if ($tenantId) {
            app(\App\Services\Cloud\CloudTenantContextRunner::class)->run($tenantId, $send);

            return;
        }
        $send();
    }
}
