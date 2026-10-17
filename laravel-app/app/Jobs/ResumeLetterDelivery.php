<?php

namespace App\Jobs;

use App\Http\Controllers\LetterController;
use App\Letter;
use App\MessageDeliveryBatch;
use App\MessageDeliveryItem;
use App\Services\MessageDeliveryTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Sends the next unsent letter recipient, then queues the following one
 * at least 5 seconds later so WhatsApp is not flooded.
 */
class ResumeLetterDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 150;

    public $tries = 1;

    protected $batchId;

    public function __construct($batchId)
    {
        $this->batchId = (int) $batchId;
        $this->onQueue('whatsapp');
    }

    public static function lockKey($batchId)
    {
        return 'letter-delivery-chain-'.(int) $batchId;
    }

    /**
     * @return bool false when a chain for this batch is already running
     */
    public static function start($batchId)
    {
        $batchId = (int) $batchId;
        if (! Cache::add(self::lockKey($batchId), 1, now()->addMinutes(10))) {
            return false;
        }
        self::dispatch($batchId)->onQueue('whatsapp');

        return true;
    }

    public function handle()
    {
        $batch = MessageDeliveryBatch::find($this->batchId);
        if (! $batch || ($batch->type ?? '') !== 'letter') {
            Cache::forget(self::lockKey($this->batchId));

            return;
        }
        Cache::put(self::lockKey($this->batchId), 1, now()->addMinutes(10));

        $tracker = app(MessageDeliveryTracker::class);
        $item = MessageDeliveryItem::where('batch_id', $batch->id)
            ->whereIn('status', ['queued', 'sending'])
            ->orderBy('id')
            ->first();
        if (! $item) {
            $tracker->finalizeBatch($batch->id);
            Cache::forget(self::lockKey($this->batchId));

            return;
        }

        $letter = Letter::find($batch->letter_id);
        if (! $letter) {
            $item->status = 'failed';
            $item->error = 'Letter was not found.';
            $item->save();
            $this->queueNext($batch->id);

            return;
        }

        $tracker->markBatchSending($batch->id);
        $item->status = 'sending';
        $item->save();

        $name = trim((string) ($item->recipient_name ?: ''));
        $phone = trim((string) ($item->phone ?: ''));
        if ($name === '' || preg_match('/^\+?\d[\d\s\-().]+$/', $name)) {
            $name = $phone !== '' ? preg_replace('/\D+/', '', $phone) : 'Customer';
        }
        $recipient = (object) [
            'name' => $name,
            'phone_number' => $phone,
            'phone' => $phone,
            'email' => (string) ($item->email ?: ''),
            'address' => '',
            'directory_id' => (string) ($item->recipient_ref ?: ''),
            'group_jid' => '',
        ];

        $controller = new LetterController();
        try {
            if (($item->role ?? '') === 'cc') {
                $msg = $controller->sendPDFToCC($letter, $recipient, null);
            } else {
                $msg = $controller->sendPDF($letter, $recipient, $item->recipient_ref);
            }
            $failed = is_string($msg) && stripos($msg, 'not sent') !== false;
            if ($failed) {
                $item->status = 'failed';
                $item->error = mb_substr($msg, 0, 2000);
            } else {
                $item->status = 'sent';
                $item->error = null;
                $item->sent_at = now();
                try {
                    $controller->sendMail($letter, $recipient, $item->recipient_ref);
                } catch (\Throwable $e) {
                    Log::info('Letter resume mail skipped: '.$e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            $item->status = 'failed';
            $item->error = mb_substr($e->getMessage(), 0, 2000);
            Log::warning('Letter resume send failed: '.$e->getMessage(), [
                'batch_id' => $batch->id,
                'item_id' => $item->id,
            ]);
        }
        $item->save();
        $tracker->refreshBatchCounts($batch->id);
        $this->queueNext($batch->id);
    }

    protected function queueNext($batchId)
    {
        $more = MessageDeliveryItem::where('batch_id', $batchId)
            ->whereIn('status', ['queued', 'sending'])
            ->exists();
        if ($more) {
            self::dispatch($batchId)->delay(now()->addSeconds(5))->onQueue('whatsapp');

            return;
        }
        app(MessageDeliveryTracker::class)->finalizeBatch($batchId);
        Cache::forget(self::lockKey($batchId));
    }
}
