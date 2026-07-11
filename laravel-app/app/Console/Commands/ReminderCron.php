<?php

namespace App\Console\Commands;

use App\BookingProduct;
use App\Http\Controllers\BookingController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReminderCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminder:cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $bookings = DB::select("SELECT booking_id, product_id, DATE_FORMAT(end, '%Y-%m-%d') as end FROM booking_products WHERE DATE_FORMAT(end, '%Y-%m-%d') = ?", [Date('Y-m-d')]);
        $bookinController = new BookingController();
        foreach ($bookings as $booking) {
            $bookinController->sendMailFromCommand($booking->booking_id, $booking->product_id);
        }
        die('command end!');
//        php artisan schedule:run
//        php artisan reminder:cron
    }
}
