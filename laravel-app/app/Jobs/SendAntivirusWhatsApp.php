<?php

namespace App\Jobs;

use App\Antivirus\AntivirusNotice;
use App\Cloud\CloudTenant;
use App\Services\Cloud\CloudTenantContext;
use App\Services\Messaging\NotificationRouter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAntivirusWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $timeout = 60;

    /** @var int */
    public $tenantId;

    /** @var int */
    public $noticeId;

    public function __construct($tenantId, $noticeId)
    {
        $this->tenantId = (int) $tenantId;
        $this->noticeId = (int) $noticeId;
        $this->onConnection('database')->onQueue('whatsapp');
    }

    public function handle(NotificationRouter $router)
    {
        $tenant = CloudTenant::find($this->tenantId);
        if (! $tenant) {
            return;
        }
        $context = app(CloudTenantContext::class);
        $context->set($tenant);
        try {
            $this->deliver($router);
        } finally {
            $context->clear();
        }
    }

    protected function deliver(NotificationRouter $router)
    {
        $claimed = AntivirusNotice::where('id', $this->noticeId)
            ->where('status', 'queued')
            ->update(['status' => 'sending']);
        $notice = AntivirusNotice::find($this->noticeId);
        if (! $notice || $notice->status === 'sent') {
            return;
        }
        if (! $claimed && ! ($notice->status === 'sending' && $this->attempts() > 1)) {
            return;
        }

        try {
            if (! $notice->text_sent_at) {
                $result = $router->sendWhatsAppText($notice->phone, $notice->body, [], false);
                if (empty($result['success'])) {
                    throw new \RuntimeException(isset($result['error']) ? $result['error'] : 'WhatsApp was not sent.');
                }
                $notice->text_sent_at = now();
                $notice->save();
            }
            if ($notice->document_path && ! $notice->document_sent_at) {
                $full = storage_path('app/'.$notice->document_path);
                if (! is_file($full)) {
                    throw new \RuntimeException('The approval PDF is not ready.');
                }
                $name = $notice->document_name ?: 'Antivirus-approval.pdf';
                $result = $router->sendWhatsAppDocument($notice->phone, $full, $name, $name);
                if (empty($result['success'])) {
                    throw new \RuntimeException(isset($result['error']) ? $result['error'] : 'The PDF was not sent.');
                }
                $notice->document_sent_at = now();
                $notice->save();
            }
            $notice->status = 'sent';
            $notice->save();
        } catch (\Throwable $e) {
            $notice->status = 'queued';
            $notice->save();
            throw $e;
        }
    }
}
