<?php

namespace App\Services;

use App\Letter;
use App\MessageDeliveryBatch;
use App\MessageDeliveryItem;
use App\Support\LetterRecipients;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MessageDeliveryTracker
{
    public function enabled(): bool
    {
        return Schema::hasTable('message_delivery_batches')
            && Schema::hasTable('message_delivery_items');
    }

    /**
     * Create a queued batch + one WhatsApp-PDF item per To recipient (and CC).
     */
    public function queueLetter(Letter $letter, $customerClass = null): ?MessageDeliveryBatch
    {
        if (! $this->enabled()) {
            return null;
        }

        $batch = MessageDeliveryBatch::create([
            'uuid' => (string) Str::uuid(),
            'type' => 'letter',
            'letter_id' => $letter->id,
            'title' => $letter->name ?: ($letter->subject ?: ('Letter #'.$letter->id)),
            'status' => 'queued',
            'total' => 0,
            'sent_count' => 0,
            'failed_count' => 0,
            'queued_by' => Auth::id(),
        ]);

        $rows = $this->expandRecipients($letter, $customerClass);
        foreach ($rows as $row) {
            MessageDeliveryItem::create(array_merge($row, [
                'batch_id' => $batch->id,
                'status' => 'queued',
            ]));
        }

        $batch->total = count($rows);
        if ($batch->total === 0) {
            $batch->status = 'failed';
            $batch->finished_at = now();
        }
        $batch->save();

        return $batch->fresh('items');
    }

    public function markBatchSending(int $batchId): void
    {
        if (! $this->enabled()) {
            return;
        }
        $batch = MessageDeliveryBatch::find($batchId);
        if (! $batch) {
            return;
        }
        $batch->status = 'sending';
        $batch->started_at = $batch->started_at ?: now();
        $batch->save();
    }

    public function markItemSending(int $batchId, $recipientRef, $phone = null): ?MessageDeliveryItem
    {
        $item = $this->findItem($batchId, $recipientRef, $phone);
        if (! $item) {
            return null;
        }
        $item->status = 'sending';
        $item->save();

        return $item;
    }

    public function markItemSent(int $batchId, $recipientRef, $phone = null, $providerId = null): void
    {
        $item = $this->findItem($batchId, $recipientRef, $phone);
        if (! $item) {
            return;
        }
        $item->status = 'sent';
        $item->provider_message_id = $providerId;
        $item->error = null;
        $item->sent_at = now();
        $item->save();
        $this->refreshBatchCounts($batchId);
    }

    public function markItemFailed(int $batchId, $recipientRef, $phone = null, $error = null): void
    {
        $item = $this->findItem($batchId, $recipientRef, $phone);
        if (! $item) {
            return;
        }
        $item->status = 'failed';
        $item->error = $error ? mb_substr((string) $error, 0, 2000) : 'Send failed';
        $item->save();
        $this->refreshBatchCounts($batchId);
    }

    public function finalizeBatch(int $batchId): void
    {
        if (! $this->enabled()) {
            return;
        }
        $this->refreshBatchCounts($batchId, true);
    }

    public function refreshBatchCounts(int $batchId, bool $finalize = false): void
    {
        $batch = MessageDeliveryBatch::find($batchId);
        if (! $batch) {
            return;
        }

        $sent = MessageDeliveryItem::where('batch_id', $batchId)->where('status', 'sent')->count();
        $failed = MessageDeliveryItem::where('batch_id', $batchId)->where('status', 'failed')->count();
        $pending = MessageDeliveryItem::where('batch_id', $batchId)
            ->whereIn('status', ['queued', 'sending'])
            ->count();

        $batch->sent_count = $sent;
        $batch->failed_count = $failed;
        $batch->total = max((int) $batch->total, $sent + $failed + $pending);

        if ($pending === 0 || $finalize) {
            if ($failed === 0 && $sent > 0) {
                $batch->status = 'completed';
            } elseif ($sent === 0 && $failed > 0) {
                $batch->status = 'failed';
            } elseif ($sent > 0 && $failed > 0) {
                $batch->status = 'partial';
            } elseif ($batch->total === 0) {
                $batch->status = 'failed';
            } else {
                $batch->status = 'sending';
            }
            if ($pending === 0) {
                $batch->finished_at = now();
            }
        } else {
            $batch->status = 'sending';
        }

        $batch->save();
    }

    protected function findItem(int $batchId, $recipientRef, $phone = null): ?MessageDeliveryItem
    {
        if (! $this->enabled()) {
            return null;
        }

        $q = MessageDeliveryItem::where('batch_id', $batchId)
            ->whereIn('status', ['queued', 'sending']);

        $ref = $recipientRef !== null && $recipientRef !== '' ? (string) $recipientRef : null;
        $phone = $phone !== null && $phone !== '' ? (string) $phone : null;

        if ($ref !== null) {
            $item = (clone $q)->where('recipient_ref', $ref)->orderBy('id')->first();
            if ($item) {
                return $item;
            }
        }
        if ($phone !== null) {
            $item = (clone $q)->where('phone', $phone)->orderBy('id')->first();
            if ($item) {
                return $item;
            }
        }

        return (clone $q)->orderBy('id')->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function expandRecipients(Letter $letter, $customerClass = null): array
    {
        $rows = [];
        $seen = [];

        $push = function ($recipient, $ref, $role = 'to') use (&$rows, &$seen) {
            $name = trim((string) ($recipient->name ?? ''));
            $phone = trim((string) ($recipient->phone_number ?? $recipient->phone ?? ''));
            $email = trim((string) ($recipient->email ?? ''));
            $key = strtolower(($ref ?: '').'|'.$phone.'|'.$email.'|'.$role);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $rows[] = [
                'recipient_ref' => $ref !== null ? (string) $ref : null,
                'recipient_name' => $name !== '' ? $name : null,
                'phone' => $phone !== '' ? $phone : null,
                'email' => $email !== '' ? $email : null,
                'channel' => 'whatsapp_pdf',
                'role' => $role,
            ];
        };

        try {
            if ($letter->people_type === 'csv') {
                $csv_path = public_path(env('LETTER_CSV_PATH'));
                $csvFilePath = $csv_path.$letter->to;
                if (is_file($csvFilePath) && ($file = fopen($csvFilePath, 'r'))) {
                    $firstRow = true;
                    $i = 0;
                    while (($row = fgetcsv($file)) !== false) {
                        if ($firstRow) {
                            $firstRow = false;
                            continue;
                        }
                        $i++;
                        $r = (object) [
                            'name' => $row[0] ?? '',
                            'phone_number' => $row[1] ?? '',
                            'email' => $row[2] ?? '',
                        ];
                        $push($r, 'csv:'.$i, 'to');
                    }
                    fclose($file);
                }
            } elseif ($letter->people_type === 'directory') {
                LetterRecipients::eachDirectoryRecipient($letter->recipients_json, function ($recipient, $ref) use ($push) {
                    $push($recipient, $ref, 'to');
                });
                LetterRecipients::eachDirectoryRecipient($letter->cc_json, function ($recipient, $ref) use ($push) {
                    $push($recipient, $ref, 'cc');
                });
            } else {
                LetterRecipients::eachRecipient($letter->people_type, $letter->to, function ($recipient, $model, $to) use ($push) {
                    $push($recipient, $to, 'to');
                });
                if ($letter->cc && $customerClass) {
                    foreach (array_filter(explode(',', $letter->cc)) as $cc) {
                        try {
                            $ccRecipient = $customerClass::find($cc);
                            if ($ccRecipient) {
                                $push($ccRecipient, $cc, 'cc');
                            }
                        } catch (\Throwable $e) {
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('MessageDeliveryTracker expand failed: '.$e->getMessage(), [
                'letter_id' => $letter->id ?? null,
            ]);
        }

        return $rows;
    }
}
