<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\ReminderCron::class,
        Commands\SendScheduledAnnouncements::class,
        Commands\RentalReturnReminderCron::class,
        Commands\SendBookingReminders::class,
        Commands\SendContractSignatureReminders::class,
        Commands\ProcessContractReminders::class,
        Commands\ProcessContractExpiryAlerts::class,
        Commands\InternshipReconcileReleases::class,
        Commands\InternshipImportCurriculum::class,
        Commands\InternshipTimesheetReminders::class,
        Commands\InternshipReviewSla::class,
        Commands\InternshipNotifyIntern::class,
        Commands\SendOnlineInvitationReminders::class,
        Commands\FixFuneralEulogies::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('reminder:cron')->everyMinute();
        $schedule->command('announcements:send-scheduled')->everyMinute();
        $schedule->command('letters:send-scheduled')->everyMinute();
        $schedule->command('rental:return-reminders')->everyFiveMinutes();
        $schedule->command('bookings:send-reminders')->everyMinute();
        $schedule->command('events:publish-scheduled')->everyMinute();
        $schedule->command('events:process-reminders')->everyMinute();
        $schedule->command('tasks:process')->everyMinute()->withoutOverlapping(10);
        $schedule->command('announcements:process')->everyMinute();
        $schedule->command('contracts:process-reminders')->everyMinute();
        $schedule->command('contracts:expiry-alerts')->dailyAt('08:00');
        // Stagger internship WhatsApp so the three hourly jobs do not start together.
        $schedule->command('internship:reconcile-releases')->hourlyAt(5);
        $schedule->command('internship:review-sla')->hourlyAt(20)->withoutOverlapping();
        $schedule->command('internship:timesheet-reminders')->dailyAt('21:35')->withoutOverlapping();
        $schedule->command('online-invitations:send-reminders')->everyMinute();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
