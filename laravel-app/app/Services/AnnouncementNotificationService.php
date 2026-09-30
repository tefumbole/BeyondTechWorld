<?php

namespace App\Services;

use App\Http\Controllers\Controller;
use App\Services\Messaging\NotificationRouter;
use App\Services\WhatsApp\GroupContactExportService;
use App\Support\AnnouncementPersonalization;
use App\WaAnnouncement;
use Illuminate\Support\Facades\Log;

class AnnouncementNotificationService extends Controller
{
    protected $whatsappNames = null;

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
        $name = AnnouncementPersonalization::usableName(isset($person['name']) ? $person['name'] : '', isset($person['phone']) ? $person['phone'] : '');
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
        $recipients = $announcement->recipients();
        $onlyGroups = count($recipients) > 0;
        foreach ($recipients as $person) {
            if (! isset($person['kind']) || $person['kind'] !== 'group') {
                $onlyGroups = false;
                break;
            }
        }
        if ($onlyGroups) {
            return $this->dispatchGroups($announcement, $recipients);
        }

        return $this->deliverPeople($announcement, $recipients, $announcement->ccRecipients(), true);
    }

    /**
     * Send the next slice of a large contact list. Returns how many people are still waiting.
     */
    public function deliverContactBatch(WaAnnouncement $announcement, $limit = 5)
    {
        $done = [];
        $existing = $announcement->send_results_json ? json_decode($announcement->send_results_json, true) : [];
        if (! is_array($existing)) {
            $existing = [];
        }
        foreach ($existing as $row) {
            if (! empty($row['phone'])) {
                $done[(string) $row['phone']] = true;
            }
        }
        $pending = [];
        foreach ($announcement->recipients() as $person) {
            $phone = isset($person['phone']) ? (string) $person['phone'] : '';
            if ($phone === '' || isset($done[$phone])) {
                continue;
            }
            $pending[] = $person;
        }
        $batch = array_slice($pending, 0, max(1, (int) $limit));
        $sentNow = $this->deliverPeople($announcement, $batch, [], false, $existing);

        return count($pending) - count($batch);
    }

    protected function dispatchGroups(WaAnnouncement $announcement, array $groups)
    {
        $wasender = app(BeyondWasenderService::class);
        $results = [];
        $sent = 0;
        foreach (array_values($groups) as $index => $group) {
            if ($index > 0) {
                usleep(2000000);
            }
            $person = [
                'name' => isset($group['name']) ? $group['name'] : 'everyone',
                'phone' => '',
                'email' => '',
            ];
            $msg = AnnouncementPersonalization::buildMessage($announcement, $person, false);
            $jid = isset($group['group_jid']) ? (string) $group['group_jid'] : '';
            $posted = $jid !== '' ? $wasender->sendGroupText($jid, $msg) : ['success' => false, 'error' => 'Missing group'];
            $ok = ! empty($posted['success']);
            if ($ok && ! empty($announcement->attachment_path)) {
                $full = public_path($announcement->attachment_path);
                if (is_file($full)) {
                    $wasender->sendGroupDocument(
                        $jid,
                        $full,
                        $announcement->attachment_name ?: basename($full),
                        $announcement->subject ?: 'Announcement'
                    );
                }
            }
            if ($ok) {
                $sent++;
            }
            $results[] = [
                'type' => 'group',
                'id' => $jid,
                'name' => $person['name'],
                'phone' => '',
                'ok' => $ok,
                'error' => $ok ? '' : (isset($posted['error']) ? $posted['error'] : 'Could not post in the group'),
            ];
        }
        $total = count($groups);
        $announcement->sent_count = $sent;
        $announcement->cc_sent_count = 0;
        $announcement->send_results_json = json_encode($results);
        $announcement->status = 'sent';
        $announcement->whatsapp_status = $sent === 0 ? 'pending' : ($sent < $total ? 'partial' : 'sent');
        $announcement->is_scheduled = false;
        $announcement->save();

        return ['sent' => $sent, 'cc' => 0, 'whatsapp_status' => $announcement->whatsapp_status];
    }

    protected function dispatchGroup(WaAnnouncement $announcement, array $group)
    {
        return $this->dispatchGroups($announcement, [$group]);
    }

    protected function deliverPeople(WaAnnouncement $announcement, array $recipients, array $ccs, $finalize, array $existingResults = [])
    {
        $results = $existingResults;
        $sent = (int) $announcement->sent_count;
        $ccSent = (int) $announcement->cc_sent_count;

        foreach ($recipients as $person) {
            $phone = $person['phone'] ?? '';
            if (! empty($announcement->personalized)) {
                $person['name'] = $this->personalName($person);
            }
            $ok = false;
            if ($announcement->send_whatsapp) {
                // Wasender sends the OTP-style formatted text; Twilio still uses template vars.
                $vars = $this->twilioVars($announcement, $person, false);
                $msg = AnnouncementPersonalization::buildMessage($announcement, $person, false);
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
            usleep(5000000);
        }

        foreach ($ccs as $person) {
            $phone = $person['phone'] ?? '';
            if (! empty($announcement->personalized)) {
                $person['name'] = $this->personalName($person);
            }
            $ok = false;
            if ($announcement->send_whatsapp) {
                $vars = $this->twilioVars($announcement, $person, true);
                $msg = AnnouncementPersonalization::buildMessage($announcement, $person, true);
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
            usleep(5000000);
        }

        $announcement->sent_count = $sent;
        $announcement->cc_sent_count = $ccSent;
        $announcement->send_results_json = json_encode($results);
        if ($finalize) {
            $total = count($announcement->recipients()) + count($announcement->ccRecipients());
            $okCount = $sent + $ccSent;
            $whatsappStatus = 'sent';
            if ($okCount === 0 && $total > 0) {
                $whatsappStatus = 'pending';
            } elseif ($okCount < $total) {
                $whatsappStatus = 'partial';
            }
            $announcement->status = 'sent';
            $announcement->whatsapp_status = $whatsappStatus;
            $announcement->is_scheduled = false;
        }
        $announcement->save();

        return ['sent' => $sent, 'cc' => $ccSent, 'whatsapp_status' => $announcement->whatsapp_status];
    }

    protected function personalName(array $person)
    {
        $phone = isset($person['phone']) ? (string) $person['phone'] : '';
        $groups = app(GroupContactExportService::class);
        if (! empty($person['name_edited'])) {
            $edited = AnnouncementPersonalization::usableName(isset($person['name']) ? $person['name'] : '', $phone);
            if ($edited !== '') {
                return $edited;
            }
        }
        $resolved = $groups->nameForPhone($phone, true);
        if ($resolved !== '') {
            return $resolved;
        }
        $self = AnnouncementPersonalization::usableName(isset($person['wa_name']) ? $person['wa_name'] : '', $phone);
        if ($self === '' && $phone !== '') {
            $self = AnnouncementPersonalization::usableName($this->whatsappProfileName($phone), $phone);
        }
        if ($self !== '') {
            return $self;
        }

        return AnnouncementPersonalization::usableName(isset($person['name']) ? $person['name'] : '', $phone);
    }

    protected function whatsappProfileName($phone)
    {
        if ($this->whatsappNames === null) {
            $this->whatsappNames = [];
            $listed = app(BeyondWasenderService::class)->listContacts();
            foreach ((isset($listed['contacts']) ? $listed['contacts'] : []) as $contact) {
                $digits = preg_replace('/\D+/', '', (string) (isset($contact['phone']) ? $contact['phone'] : ''));
                if (strlen($digits) < 8) {
                    continue;
                }
                $this->whatsappNames[substr($digits, -9)] = trim((string) (isset($contact['wa_name']) ? $contact['wa_name'] : ''));
            }
        }
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) < 8) {
            return '';
        }
        $tail = substr($digits, -9);

        return isset($this->whatsappNames[$tail]) ? $this->whatsappNames[$tail] : '';
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
            $person['name'] = $this->personalName($person);
            $name = AnnouncementPersonalization::usableName($person['name'], $phone);
            $msg = "⏰ *Reminder*\n\n";
            if ($name !== '') {
                $msg .= 'Dear *'.$name."*,\n\n";
            } else {
                $msg .= "Hello,\n\n";
            }
            $msg .= "This is a reminder for the following announcement.\n\n";
            if ($announcement->reference) {
                $msg .= \App\Support\WhatsAppMessage::bullet('Reference', $announcement->reference);
            }
            $msg .= \App\Support\WhatsAppMessage::bullet('Subject', $announcement->subject ?: 'Announcement');
            $msg .= \App\Support\WhatsAppMessage::bullet('Scheduled', $when);
            $msg .= \App\Support\WhatsAppMessage::footer();
            if ($this->sendPhone($phone, $msg)) {
                $sent++;
            }
            usleep(5000000);
        }

        return $sent;
    }
}
