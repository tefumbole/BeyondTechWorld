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
            $router = app(NotificationRouter::class);
            if (trim((string) ($statusVars['body'] ?? '')) !== '' && $router->sharedNoticeIsApproved()) {
                $template = $router->sendSharedNotice($phone, $statusVars);
                if (! empty($template['success'])) {
                    return true;
                }
            }
            $result = $router->sendWhatsAppAnnouncement($phone, $message, $statusVars);

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

        $vars = AnnouncementPersonalization::recipientVars($person, $reference, $header !== '' ? $header : $subject);
        $body = trim(AnnouncementPersonalization::personalize($announcement->body ?: '', $vars));
        $body = preg_replace('/^\s*Dear\s+[^,\n]*,\s*/iu', '', $body);
        $body = trim(preg_replace('/\*+|_+/', '', (string) $body));
        if ($isCc) {
            $body = "You have been copied on this notice.\n\n".$body;
        }
        $footer = trim(AnnouncementPersonalization::personalize($announcement->footer ?: '', $vars));
        $footer = trim(preg_replace('/\*+|_+/', '', $footer));
        if ($footer === '') {
            $footer = $header !== '' ? $header : \App\Support\WhatsAppMessage::companyName();
        }
        if ($subject === '') {
            $subject = $header !== '' ? $header : 'Notice';
        }
        if ($header === '') {
            $header = $subject;
        }

        return [
            'title' => $subject,
            'subject' => $subject,
            'header' => $header,
            'name' => $name !== '' ? $name : 'Friend',
            'body' => $body !== '' ? $body : '-',
            'footer' => $footer,
            'message' => $body !== '' ? $body : '-',
            'reference' => $reference !== '' ? $reference : '-',
            'details' => $header,
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
        $export = app(GroupContactExportService::class);
        $results = [];
        $sent = 0;
        $private = [];
        $broadcast = [];
        foreach (array_values($groups) as $group) {
            $jid = isset($group['group_jid']) ? (string) $group['group_jid'] : '';
            if ($jid !== '' && $export->skipsGroupBroadcast($jid)) {
                foreach ($export->notifiableMembers($jid) as $member) {
                    $private[] = [
                        'id' => 'wa:'.$member['phone'],
                        'kind' => 'contact',
                        'name' => $member['name'],
                        'wa_name' => $member['name'],
                        'phone' => $member['phone'],
                        'email' => '',
                        'group_jid' => $jid,
                        'group' => isset($group['name']) ? $group['name'] : '',
                    ];
                }
                continue;
            }
            $broadcast[] = $group;
        }
        foreach (array_values($broadcast) as $index => $group) {
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
        if ($private) {
            $announcement->sent_count = $sent;
            $announcement->cc_sent_count = 0;
            $announcement->send_results_json = json_encode($results);
            if (count($private) > 5) {
                $announcement->recipients_json = json_encode($private);
                $announcement->status = 'sending';
                $announcement->whatsapp_status = 'sending';
                $announcement->is_scheduled = false;
                $announcement->save();
                \App\Jobs\SendWaAnnouncementBatchJob::dispatch($announcement->id)
                    ->onConnection('database')
                    ->onQueue('whatsapp');

                return ['sent' => $sent, 'cc' => 0, 'whatsapp_status' => 'sending'];
            }

            return $this->deliverPeople($announcement, $private, [], true, $results);
        }
        $total = count($broadcast);
        $announcement->sent_count = $sent;
        $announcement->cc_sent_count = 0;
        $announcement->send_results_json = json_encode($results);
        $announcement->status = 'sent';
        $announcement->whatsapp_status = $broadcast === [] ? 'sent' : ($sent === 0 ? 'pending' : ($sent < $total ? 'partial' : 'sent'));
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
            $groupJid = isset($person['group_jid']) ? (string) $person['group_jid'] : '';
            if ($groupJid !== '' && ! app(GroupContactExportService::class)->shouldNotify($groupJid, $phone)) {
                continue;
            }
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
            $total = 0;
            $export = app(GroupContactExportService::class);
            foreach (array_merge($announcement->recipients(), $announcement->ccRecipients()) as $person) {
                if (! is_array($person)) {
                    continue;
                }
                $groupJid = isset($person['group_jid']) ? (string) $person['group_jid'] : '';
                $personPhone = isset($person['phone']) ? (string) $person['phone'] : '';
                if ($groupJid !== '' && ! $export->shouldNotify($groupJid, $personPhone)) {
                    continue;
                }
                $total++;
            }
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
        $display = $groups->savedDisplayName($phone);
        if ($display !== '') {
            return $display;
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
        if (! $this->acquireReminderLock($announcement->id)) {
            return 0;
        }

        try {
            return $this->deliverReminder($announcement);
        } finally {
            $this->releaseReminderLock($announcement->id);
        }
    }

    protected function deliverReminder(WaAnnouncement $announcement)
    {

        $groups = [];
        $phones = [];
        foreach (array_merge($announcement->recipients(), $announcement->ccRecipients()) as $person) {
            if (! is_array($person)) {
                continue;
            }
            $jid = isset($person['group_jid']) ? trim((string) $person['group_jid']) : '';
            $kind = isset($person['kind']) ? (string) $person['kind'] : '';
            $phone = isset($person['phone']) ? trim((string) $person['phone']) : '';
            if ($kind === 'group' || ($phone === '' && substr($jid, -5) === '@g.us') || strpos($phone, '@g.us') !== false) {
                if (substr($jid, -5) === '@g.us') {
                    $groups[$jid] = $person;
                } elseif (strpos($phone, '@g.us') !== false) {
                    $groups[$phone] = $person;
                }
                continue;
            }
            $digits = preg_replace('/\D+/', '', $phone);
            if (strlen($digits) < 8 || isset($phones[$digits])) {
                continue;
            }
            if ($jid !== '' && ! app(GroupContactExportService::class)->shouldNotify($jid, $digits)) {
                continue;
            }
            $phones[$digits] = $person;
        }

        $sent = 0;
        if (! $announcement->personalized && $groups !== []) {
            foreach ($groups as $jid => $person) {
                if ($this->postGroupReminder($announcement, $jid, $person)) {
                    $sent++;
                }
                usleep(5000000);
            }

            return $sent;
        }

        foreach ($phones as $person) {
            $phone = $person['phone'] ?? '';
            $person['name'] = $this->personalName($person);
            $vars = $this->twilioVars($announcement, $person, false);
            $vars['kind'] = 'a reminder';
            $msg = $this->reminderText($announcement, $person);
            if ($this->sendPhone($phone, $msg, $vars)) {
                $sent++;
            }
            usleep(5000000);
        }

        return $sent;
    }

    protected function acquireReminderLock($announcementId)
    {
        $row = \Illuminate\Support\Facades\DB::select('SELECT GET_LOCK(?, 0) AS locked', [$this->reminderLockName($announcementId)]);

        return isset($row[0]) && (int) $row[0]->locked === 1;
    }

    protected function releaseReminderLock($announcementId)
    {
        \Illuminate\Support\Facades\DB::select('SELECT RELEASE_LOCK(?)', [$this->reminderLockName($announcementId)]);
    }

    protected function reminderLockName($announcementId)
    {
        return 'wa_rem_'.substr(md5((string) $announcementId), 0, 24);
    }

    protected function postGroupReminder(WaAnnouncement $announcement, $jid, array $person = [])
    {
        $person['name'] = '';
        $person['phone'] = '';
        $msg = $this->reminderText($announcement, $person);
        $export = app(GroupContactExportService::class);
        if ($export->skipsGroupBroadcast($jid)) {
            $delivered = 0;
            $members = $export->notifiableMembers($jid);
            foreach ($members as $index => $member) {
                if ($index > 0) {
                    usleep(5000000);
                }
                $memberPerson = $person;
                $memberPerson['name'] = $member['name'] ?? '';
                $memberPerson['phone'] = $member['phone'] ?? '';
                $vars = $this->twilioVars($announcement, $memberPerson, false);
                $vars['kind'] = 'a reminder';
                if ($this->sendPhone($member['phone'], $msg, $vars)) {
                    $delivered++;
                }
            }

            return $members === [] || $delivered > 0;
        }
        $posted = app(BeyondWasenderService::class)->sendGroupText($jid, $msg);

        return ! empty($posted['success']);
    }

    protected function reminderText(WaAnnouncement $announcement, array $person)
    {
        $body = ltrim(AnnouncementPersonalization::buildMessage($announcement, $person, false));

        return "⏰ *Reminder*\n\n".$body;
    }
}
