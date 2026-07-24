<?php

namespace App\Services;

use App\Http\Controllers\Controller;
use App\Services\Messaging\NotificationRouter;
use App\Support\AnnouncementPersonalization;
use App\WaAnnouncement;
use Illuminate\Support\Facades\Log;

class AnnouncementNotificationService extends Controller
{
    protected function sendPhone($phone, $message)
    {
        if (empty(trim((string) $phone))) {
            return false;
        }
        try {
            // Uses NotificationRouter: Twilio beyond_notice when WHATSAPP_SERVICE=TWILIO.
            $result = app(NotificationRouter::class)->sendWhatsAppAnnouncement($phone, $message);

            return ! empty($result['success']);
        } catch (\Exception $e) {
            Log::warning('Announcement WhatsApp failed: ' . $e->getMessage());

            return false;
        }
    }

    protected function sendAttachment($phone, WaAnnouncement $announcement)
    {
        if (empty($announcement->attachment_path) || empty($phone)) {
            return;
        }
        $full = public_path($announcement->attachment_path);
        if (! is_file($full)) {
            return;
        }
        try {
            $customer = (object) ['phone_number' => $phone, 'phone' => $phone];
            $this->wpPDFAnnouncement(
                $announcement->attachment_path,
                $customer,
                $announcement->attachment_name ?: basename($announcement->attachment_path)
            );
        } catch (\Exception $e) {
            Log::warning('Announcement attachment WhatsApp failed: ' . $e->getMessage());
        }
    }

    /**
     * Send to all recipients + CC. No action required from recipient.
     */
    public function dispatchAnnouncement(WaAnnouncement $announcement)
    {
        $results = [];
        $sent = 0;
        $ccSent = 0;

        foreach ($announcement->recipients() as $person) {
            $phone = $person['phone'] ?? '';
            $ok = false;
            if ($announcement->send_whatsapp) {
                $msg = AnnouncementPersonalization::buildMessage($announcement, $person, false);
                $ok = $this->sendPhone($phone, $msg);
                if ($ok) {
                    $this->sendAttachment($phone, $announcement);
                    $sent++;
                }
            }
            $results[] = [
                'type' => 'to',
                'id' => $person['id'] ?? null,
                'name' => $person['name'] ?? '',
                'phone' => $phone,
                'ok' => $ok,
            ];
            usleep(6000000); // 6s between recipients
        }

        foreach ($announcement->ccRecipients() as $person) {
            $phone = $person['phone'] ?? '';
            $ok = false;
            if ($announcement->send_whatsapp) {
                $msg = AnnouncementPersonalization::buildMessage($announcement, $person, true);
                $ok = $this->sendPhone($phone, $msg);
                if ($ok) {
                    $this->sendAttachment($phone, $announcement);
                    $ccSent++;
                }
            }
            $results[] = [
                'type' => 'cc',
                'id' => $person['id'] ?? null,
                'name' => $person['name'] ?? '',
                'phone' => $phone,
                'ok' => $ok,
            ];
            usleep(6000000);
        }

        $total = count($announcement->recipients()) + count($announcement->ccRecipients());
        $okCount = $sent + $ccSent;
        $whatsappStatus = 'sent';
        if ($okCount === 0 && $total > 0) {
            $whatsappStatus = 'pending';
        } elseif ($okCount < $total) {
            $whatsappStatus = 'partial';
        }

        $announcement->sent_count = $sent;
        $announcement->cc_sent_count = $ccSent;
        $announcement->send_results_json = json_encode($results);
        $announcement->status = 'sent';
        $announcement->whatsapp_status = $whatsappStatus;
        $announcement->is_scheduled = false;
        $announcement->save();

        return ['sent' => $sent, 'cc' => $ccSent, 'whatsapp_status' => $whatsappStatus];
    }

    public function sendReminder(WaAnnouncement $announcement)
    {
        $sent = 0;
        foreach (array_merge($announcement->recipients(), $announcement->ccRecipients()) as $person) {
            $phone = $person['phone'] ?? '';
            if (! $phone) {
                continue;
            }
            $when = $announcement->scheduled_for
                ? $announcement->scheduled_for->format('d M Y H:i')
                : 'soon';
            $msg = "⏰ *ANNOUNCEMENT REMINDER*\n━━━━━━━━━━━━━━━\n\n";
            $msg .= "Hello *" . ($person['name'] ?: 'Team') . "*,\n\n";
            $msg .= "Reminder for announcement";
            if ($announcement->reference) {
                $msg .= " (*{$announcement->reference}*)";
            }
            $msg .= ":\n\n";
            $msg .= "▪️ *" . ($announcement->subject ?: 'Announcement') . "*\n";
            $msg .= "▪️ Scheduled: {$when}\n\n";
            $msg .= "_Beyond Enterprise_";
            if ($this->sendPhone($phone, $msg)) {
                $sent++;
            }
            usleep(3000000);
        }

        return $sent;
    }
}
