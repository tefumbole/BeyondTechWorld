<?php

namespace App\Console\Commands;

use App\Http\Controllers\Controller;
use App\OnlineInvitation;
use App\OnlineInvitationReminder;
use Illuminate\Console\Command;

class SendOnlineInvitationReminders extends Command
{
    protected $signature = 'online-invitations:send-reminders';

    protected $description = 'Send scheduled WhatsApp reminders for digital invitation events';

    public function handle()
    {
        $due = OnlineInvitationReminder::with('event')
            ->where('status', 'scheduled')
            ->where('remind_at', '<=', now())
            ->orderBy('id')
            ->limit(20)
            ->get();

        $controller = new Controller();
        foreach ($due as $reminder) {
            $q = OnlineInvitation::where('is_active', 1)
                ->where('event_id', $reminder->event_id);
            if ($reminder->audience === 'accepted') {
                $q->where('rsvp_status', 'accepted');
            } elseif ($reminder->audience === 'sent') {
                $q->where('status', 'sent');
            }
            $invitations = $q->get();
            $eventName = optional($reminder->event)->name ?: 'the event';
            $custom = trim((string) $reminder->message);
            foreach ($invitations as $inv) {
                $phone = $inv->recipient_phone;
                if (! $phone) {
                    continue;
                }
                $name = $inv->recipient_name ?: 'Guest';
                $url = $inv->token
                    ? rtrim((string) env('APP_URL'), '/').'/online-invitation/invite/'.$inv->token
                    : '';
                $msg = $custom !== ''
                    ? $custom
                    : "Reminder: *{$eventName}*\n\nHi {$name}, this is a reminder about your invitation."
                        .($url ? "\n\nView invitation:\n{$url}" : '');
                try {
                    $controller->wpMessage($phone, $msg);
                    usleep(5500000);
                } catch (\Throwable $e) {
                    \Log::warning('Invitation reminder WhatsApp failed', [
                        'reminder_id' => $reminder->id,
                        'invitation_id' => $inv->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            $reminder->status = 'sent';
            $reminder->sent_at = now();
            $reminder->save();
        }

        $this->info('Processed '.$due->count().' reminder(s).');

        return 0;
    }
}
