<?php

namespace App\Console\Commands;

use App\Letter;
use App\Http\Controllers\LetterController;
use App\Customer;
use App\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendScheduledLetters extends Command
{
    protected $signature = 'letters:send-scheduled';
    protected $description = 'Send letters scheduled for date_time that are not yet sent';

    public function handle()
    {
        $now = now();
        $dueLetters = Letter::where('is_active', 1)
            ->where('is_sent', 0)
            ->where('is_approve', 1)
            ->where('is_sign', 1)
            ->where('is_rejected', 0)
            ->whereNotNull('date_time')
            ->where('date_time', '<=', $now)
            ->get();


        if ($dueLetters->isEmpty()) {
            return 0;
        }

        $controller = new LetterController();

        foreach ($dueLetters as $letter) {
            try {
                if ($letter->people_type === 'customer') {
                    $customerModel = Customer::class;
                } else {
                    $customerModel = Employee::class;
                }

                if ($letter->people_type === 'csv') {
                    $csvPath = public_path(env('LETTER_CSV_PATH'));
                    $csvFilePath = $csvPath . $letter->to;
                    if (is_readable($csvFilePath)) {
                        if (($file = fopen($csvFilePath, 'r')) !== false) {
                            $firstRow = true;
                            while (($data = fgetcsv($file)) !== false) {
                                if ($firstRow) { $firstRow = false; continue; }
                                $recipient = (object) [];
                                $recipient->name = $data[0] ?? '';
                                $recipient->phone_number = $data[1] ?? '';
                                $recipient->email = $data[2] ?? '';
                                $recipient->address = $data[3] ?? '';
                                // Optional custom columns Column1..Column10 mapped from CSV indexes 4..13
                                $recipient->column1 = $data[4] ?? '';
                                $recipient->column2 = $data[5] ?? '';
                                $recipient->column3 = $data[6] ?? '';
                                $recipient->column4 = $data[7] ?? '';
                                $recipient->column5 = $data[8] ?? '';
                                $recipient->column6 = $data[9] ?? '';
                                $recipient->column7 = $data[10] ?? '';
                                $recipient->column8 = $data[11] ?? '';
                                $recipient->column9 = $data[12] ?? '';
                                $recipient->column10 = $data[13] ?? '';
                                $controller->sendPDF($letter, $recipient, $recipient->email ?: $recipient->phone_number);
                                $controller->sendMail($letter, $recipient, $recipient->email ?: '');
                            }
                            fclose($file);
                        }
                    }
                } else {
                    foreach (explode(',', (string) $letter->to) as $to) {
                        $to = trim($to);
                        if ($to === '') { continue; }
                        $recipient = $customerModel::find($to);
                        if ($recipient) {
                            $controller->sendPDF($letter, $recipient, $to);
                            $controller->sendMail($letter, $recipient, $to);
                        }
                    }
                    if (!empty($letter->cc)) {
                        foreach (explode(',', (string) $letter->cc) as $cc) {
                            $cc = trim($cc);
                            if ($cc === '') { continue; }
                            $recipient = $customerModel::find($cc);
                            if ($recipient) {
                                $controller->sendPDFToCC($letter, $recipient, $letter->to);
                            }
                        }
                    }
                }

                $letter->update(['is_sent' => true]);
            } catch (\Throwable $e) {
                Log::error('Failed sending scheduled letter', [
                    'letter_id' => $letter->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return 0;
    }
}


