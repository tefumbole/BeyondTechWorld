<?php

namespace App\Jobs;

use App\Http\Controllers\LetterController;
use App\Services\MessageDeliveryTracker;
use App\Support\LetterRecipients;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessQueue implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60000;

    protected $letter;
    protected $id;
    protected $customer;
    protected $batchId;

    public function __construct($letter, $id, $customer, $batchId = null)
    {
        $this->letter = $letter;
        $this->id = $id;
        $this->customer = $customer;
        $this->batchId = $batchId ? (int) $batchId : null;
    }

    public function handle()
    {
        $letter = $this->letter;
        $letterController = new LetterController();
        $tracker = app(MessageDeliveryTracker::class);
        $batchId = $this->batchId;

        if ($batchId) {
            $tracker->markBatchSending($batchId);
        }

        $sendTo = function ($recipient, $ref) use ($letterController, $letter, $tracker, $batchId) {
            $phone = $recipient->phone_number ?? $recipient->phone ?? null;
            if ($batchId) {
                $tracker->markItemSending($batchId, $ref, $phone);
            }
            try {
                $msg = $letterController->sendPDF($letter, $recipient, $ref);
                $letterController->sendMail($letter, $recipient, $ref);
                $failed = is_string($msg) && stripos($msg, 'not sent') !== false;
                if ($batchId) {
                    if ($failed) {
                        $tracker->markItemFailed($batchId, $ref, $phone, $msg);
                    } else {
                        $tracker->markItemSent($batchId, $ref, $phone);
                    }
                }
            } catch (\Throwable $e) {
                if ($batchId) {
                    $tracker->markItemFailed($batchId, $ref, $phone, $e->getMessage());
                }
                throw $e;
            }
        };

        $sendCc = function ($ccRecipient, $ref, $original) use ($letterController, $letter, $tracker, $batchId) {
            $phone = $ccRecipient->phone_number ?? $ccRecipient->phone ?? null;
            if ($batchId) {
                $tracker->markItemSending($batchId, $ref, $phone);
            }
            try {
                $msg = $letterController->sendPDFToCC($letter, $ccRecipient, $original);
                $failed = is_string($msg) && stripos($msg, 'not sent') !== false;
                if ($batchId) {
                    if ($failed) {
                        $tracker->markItemFailed($batchId, $ref, $phone, $msg);
                    } else {
                        $tracker->markItemSent($batchId, $ref, $phone);
                    }
                }
            } catch (\Throwable $e) {
                if ($batchId) {
                    $tracker->markItemFailed($batchId, $ref, $phone, $e->getMessage());
                }
                throw $e;
            }
        };

        try {
            if ($letter->people_type === 'csv') {
                $csv_path = public_path(env('LETTER_CSV_PATH'));
                $csvFilePath = $csv_path.$letter->to;
                $file = fopen($csvFilePath, 'r');

                if ($file !== false) {
                    $firstRow = true;
                    $i = 0;
                    while (($row = fgetcsv($file)) !== false) {
                        if ($firstRow) {
                            $firstRow = false;
                            continue;
                        }
                        $i++;
                        $r = (object) [];
                        $r->name = $row[0] ?? '';
                        $r->phone_number = $row[1] ?? '';
                        $r->email = $row[2] ?? '';
                        $r->address = $row[3] ?? '';
                        $r->column1 = $row[4] ?? '';
                        $r->column2 = $row[5] ?? '';
                        $r->column3 = $row[6] ?? '';
                        $r->column4 = $row[7] ?? '';
                        $r->column5 = $row[8] ?? '';
                        $r->column6 = $row[9] ?? '';
                        $r->column7 = $row[10] ?? '';
                        $r->column8 = $row[11] ?? '';
                        $r->column9 = $row[12] ?? '';
                        $r->column10 = $row[13] ?? '';
                        try {
                            $sendTo($r, 'csv:'.$i);
                        } catch (\Throwable $e) {
                            \Log::warning('Letter CSV send failed: '.$e->getMessage(), [
                                'letter_id' => $letter->id ?? null,
                                'row' => $i,
                            ]);
                        }
                    }
                    fclose($file);
                }
            } elseif ($letter->people_type === 'directory') {
                $toRecipients = [];
                LetterRecipients::eachDirectoryRecipient($letter->recipients_json, function ($recipient, $ref) use ($sendTo, $letter, &$toRecipients) {
                    try {
                        $sendTo($recipient, $ref ?: ($recipient->email ?: $recipient->phone_number));
                        $toRecipients[] = $recipient;
                    } catch (\Throwable $e) {
                        \Log::warning('Letter directory To send failed: '.$e->getMessage(), [
                            'letter_id' => $letter->id ?? null,
                            'ref' => $ref,
                        ]);
                    }
                });

                LetterRecipients::eachDirectoryRecipient($letter->cc_json, function ($ccRecipient, $ref) use ($sendCc, $letter, $toRecipients) {
                    $originals = ! empty($toRecipients) ? $toRecipients : [null];
                    foreach ($originals as $original) {
                        try {
                            $sendCc($ccRecipient, $ref, $original);
                        } catch (\Throwable $e) {
                            \Log::warning('Letter directory CC send failed: '.$e->getMessage(), [
                                'letter_id' => $letter->id ?? null,
                                'ref' => $ref,
                            ]);
                        }
                    }
                });
            } else {
                $toRecipients = [];
                LetterRecipients::eachRecipient($letter->people_type, $letter->to, function ($recipient, $model, $to) use ($sendTo, $letter, &$toRecipients) {
                    try {
                        $sendTo($recipient, $to);
                        $toRecipients[] = $recipient;
                    } catch (\Throwable $e) {
                        \Log::warning('Letter recipient send failed: '.$e->getMessage(), [
                            'letter_id' => $letter->id ?? null,
                            'to' => $to,
                        ]);
                    }
                });

                if ($letter->cc != null && $this->customer) {
                    $model = $this->customer;
                    $originals = ! empty($toRecipients) ? $toRecipients : [null];
                    foreach (array_filter(explode(',', $letter->cc)) as $cc) {
                        try {
                            $ccRecipient = $model::find($cc);
                            if (! $ccRecipient) {
                                continue;
                            }
                            foreach ($originals as $original) {
                                $sendCc($ccRecipient, $cc, $original);
                            }
                        } catch (\Throwable $e) {
                            \Log::warning('Letter CC send failed: '.$e->getMessage(), [
                                'letter_id' => $letter->id ?? null,
                                'cc' => $cc,
                            ]);
                        }
                    }
                }
            }
        } finally {
            if ($batchId) {
                $tracker->finalizeBatch($batchId);
            }
        }
    }
}
