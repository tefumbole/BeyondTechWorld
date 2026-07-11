<?php

namespace App\Console\Commands;

use App\Announcement;
use App\Http\Controllers\AnnouncementController;
use App\Customer;
use App\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendScheduledAnnouncements extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'announcements:send-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send announcements scheduled for date_time that are not yet sent';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Use app timezone; date_time stored as app-local time
        $now = now();
        $dueAnnouncements = Announcement::where('is_active', true)
            ->where('is_sent', false)
            ->whereNotNull('date_time')
            ->where('date_time', '<=', $now)
            ->get();

        if ($dueAnnouncements->isEmpty()) {
            return 0;
        }

        $controller = new AnnouncementController();

        foreach ($dueAnnouncements as $announcement) {
            try {
                if ($announcement->people_type === 'csv') {
                    $csv_path = public_path('announcement/csv/');
//                    $csv_path = public_path('public/announcement/csv/');
                    $csvFilePath = $csv_path . $announcement->to;
                    $file = fopen($csvFilePath, 'r');

                    if ($file !== false) {
                        $firstRow = true;
                        while (($data = fgetcsv($file)) !== false) {
                            $lims_customer_data = '';
                            if ($firstRow) {
                                $firstRow = false; // Set the flag to false after skipping the first row
                                continue; // Skip processing the first row
                            }
                            $lims_customer_data = (object) $data;
                            $lims_customer_data->name = $data[0] ?? '';
                            $lims_customer_data->phone_number = $data[1] ?? '';
                            $lims_customer_data->email = $data[2] ?? '';
                            // Map optional custom columns Column1..Column10 from CSV indexes 3..12
                            $lims_customer_data->column1 = $data[3] ?? '';
                            $lims_customer_data->column2 = $data[4] ?? '';
                            $lims_customer_data->column3 = $data[5] ?? '';
                            $lims_customer_data->column4 = $data[6] ?? '';
                            $lims_customer_data->column5 = $data[7] ?? '';
                            $lims_customer_data->column6 = $data[8] ?? '';
                            $lims_customer_data->column7 = $data[9] ?? '';
                            $lims_customer_data->column8 = $data[10] ?? '';
                            $lims_customer_data->column9 = $data[11] ?? '';
                            $lims_customer_data->column10 = $data[12] ?? '';

                            $controller->sendAnnouncementMsg($announcement, $lims_customer_data);
                        }
                    }
                } else {
                    // Determine the correct model based on people_type
                    $modelClass = $announcement->people_type === 'customer' ? Customer::class : Employee::class;

                    // Handle `to` list
                    foreach (explode(',', (string) $announcement->to) as $to) {
                        $to = trim($to);
                        if ($to === '') { continue; }
                        $recipient = $modelClass::find($to);
                        if ($recipient) {
                            $controller->sendAnnouncementMsg($announcement, $recipient);
                        }
                    }
                    // Handle `cc` list
                    if (!empty($announcement->cc)) {
                        foreach (explode(',', (string) $announcement->cc) as $cc) {
                            $cc = trim($cc);
                            if ($cc === '') { continue; }
                            $recipient = $modelClass::find($cc);
                            if ($recipient) {
                                $controller->sendAnnouncementMsg($announcement, $recipient);
                            }
                        }
                    }
                }

                $announcement->update(['is_sent' => true]);
            } catch (\Throwable $e) {
                Log::error('Failed sending scheduled announcement', [
                    'announcement_id' => $announcement->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return 0;
    }
}


