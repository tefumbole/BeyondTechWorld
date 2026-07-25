<?php

namespace App\Services;

use App\Http\Controllers\Controller;
use App\Services\Messaging\NotificationRouter;
use App\Support\AnnouncementPersonalization;
use App\WaAnnouncement;
use Illuminate\Support\Facades\Log;

class AnnouncementNotificationService extends Controller
{
    /**
     * @param  array{title?:string,name?:string,message?:string,reference?:string,details?:string}  $statusVars
     */
    protected function sendPhone($phone, $message, array $statusVars = [])
    {
        if (empty(trim((string) $phone))) {
            return false;
        }
        try {
            // NotificationRouter: Wasender by default; Twilio beyond_notice only if WHATSAPP_SERVICE=TWILIO.
            $result = app(NotificationRouter::class)->sendWhatsAppAnnouncement($phone, $message, $statusVars);

            return ! empty($result['success']);
        } catch (\Exception $e) {
            Log::warning('Announcement WhatsApp failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Map announcement fields into beyond_notice variables:
     * {{1}} headline, {{2}} name, {{3}} message, {{4}} reference, {{5}} extra.
     */
    protected function twilioVars(WaAnnouncement $announcement, array $person, $isCc = false)
    {
        $name = trim((string) ($person['name'] ?? ''));
        $subject = trim((string) ($announcement->subject ?? ''));
        $reference = trim((string) ($announcement->reference ?? ''));
        $header = trim((string) ($announcement->header ?? ''));

        $plain = AnnouncementPersonalization::buildTwilioBody($announcement, $person, $isCc);
        if (mb_strlen($plain) > 800) {
            $plain = rtrim(mb_substr($plain, 0, 799)).'…';
        }

        $details = $header !== '' ? $header : 'Beyond announcement';
        if (! empty($announcement->scheduled_for)) {
            $details = 'Scheduled '.$announcement->scheduled_for->format('d M Y H:i');
        }

        return [
            'title' => $subject !== '' ? $subject : 'Announcement',
            'name' => $name !== '' ? $name : 'Client',
            'message' => $plain !== '' ? $plain : '-',
            'reference' => $reference !== '' ? $reference : 'Announcement',
            'details' => $details,
        ];
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
                $vars = $this->twilioVars($announcement, $person, false);
                $msg = $vars['message'];
                $ok = $this->sendPhone($phone, $msg, $vars);
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
                $vars = $this->twilioVars($announcement, $person, true);
                $msg = $vars['message'];
                $ok = $this->sendPhone($phone, $msg, $vars);
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
