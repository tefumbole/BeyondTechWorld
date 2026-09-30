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
        $remaining = $notify->deliverContactBatch($announcement->fresh(), 5);
        if ($remaining > 0) {
            static::dispatch($this->announcementId)->onConnection('database')->onQueue('whatsapp');

            return;
        }
        $announcement = $announcement->fresh();
        $results = $announcement->send_results_json ? json_decode($announcement->send_results_json, true) : [];
        $okCount = 0;
        $total = is_array($results) ? count($results) : 0;
        if (is_array($results)) {
            foreach ($results as $row) {
                if (! empty($row['ok'])) {
                    $okCount++;
                }
            }
        }
        $announcement->status = 'sent';
        $announcement->whatsapp_status = $okCount === 0 ? 'pending' : ($okCount < $total ? 'partial' : 'sent');
        $announcement->is_scheduled = false;
        $announcement->save();
    }
}
