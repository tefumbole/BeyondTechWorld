<?php

namespace App\Jobs;

use App\Http\Controllers\LetterController;
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

    public function __construct($letter, $id, $customer)
    {
        $this->letter = $letter;
        $this->id = $id;
        $this->customer = $customer;
    }

    public function handle()
    {
        $letter = $this->letter;
        $id = $this->id;
        $letterController = new LetterController();

        if ($letter->people_type === 'csv') {
            $csv_path = public_path(env('LETTER_CSV_PATH'));
            $csvFilePath = $csv_path.$letter->to;
            $file = fopen($csvFilePath, 'r');

            if ($file !== false) {
                $firstRow = true;
                while (($row = fgetcsv($file)) !== false) {
                    if ($firstRow) {
                        $firstRow = false;
                        continue;
                    }
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
                    $lims_customer_data = $r;

                    $letterController->sendPDF($letter, $lims_customer_data, $lims_customer_data->email ?: $lims_customer_data->phone_number);
                    $letterController->sendMail($letter, $lims_customer_data, $lims_customer_data->email ?: '');
                }
            }
            fclose($file);
        } elseif ($letter->people_type === 'directory') {
            $toRecipients = [];
            LetterRecipients::eachDirectoryRecipient($letter->recipients_json, function ($recipient, $ref) use ($letterController, $letter, &$toRecipients) {
                try {
                    $key = $recipient->email ?: ($recipient->phone_number ?: $ref);
                    $letterController->sendPDF($letter, $recipient, $key);
                    $letterController->sendMail($letter, $recipient, $key);
                    $toRecipients[] = $recipient;
                } catch (\Throwable $e) {
                    \Log::warning('Letter directory To send failed: '.$e->getMessage(), [
                        'letter_id' => $letter->id ?? null,
                        'ref' => $ref,
                    ]);
                }
            });

            LetterRecipients::eachDirectoryRecipient($letter->cc_json, function ($ccRecipient, $ref) use ($letterController, $letter, $toRecipients) {
                $originals = ! empty($toRecipients) ? $toRecipients : [null];
                foreach ($originals as $original) {
                    try {
                        $letterController->sendPDFToCC($letter, $ccRecipient, $original);
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
            LetterRecipients::eachRecipient($letter->people_type, $letter->to, function ($recipient, $model, $to) use ($letterController, $letter, &$toRecipients) {
                try {
                    $letterController->sendPDF($letter, $recipient, $to);
                    $letterController->sendMail($letter, $recipient, $to);
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
                            $letterController->sendPDFToCC($letter, $ccRecipient, $original);
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
    }
}
