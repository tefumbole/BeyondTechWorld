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
                    if ($firstRow) { $firstRow = false; continue;}
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
        } else {
            LetterRecipients::eachRecipient($letter->people_type, $letter->to, function ($recipient, $model, $to) use ($letterController, $letter) {
                $letterController->sendPDF($letter, $recipient, $to);
                $letterController->sendMail($letter, $recipient, $to);
            });

            if ($letter->cc != null) {
                $model = $this->customer;
                foreach (array_filter(explode(',', $letter->cc)) as $cc) {
                    $lims_customer_data = $model::find($cc);
                    if ($lims_customer_data) {
                        $letterController->sendPDFToCC($letter, $lims_customer_data, $letter->to);
                    }
                }
            }
        }
    }
}
