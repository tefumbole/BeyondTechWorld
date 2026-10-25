<?php

namespace App\Services;

use App\Support\SchoolMerge;
use App\WaAnnouncement;
use App\WaAnnouncementCategory;
use App\WaAnnouncementReminder;
use App\WaAnnouncementSetting;
use App\WaAnnouncementTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnnouncementService
{
    protected $people;
    protected $notify;

    public function __construct(PeopleDirectoryService $people, AnnouncementNotificationService $notify)
    {
        $this->people = $people;
        $this->notify = $notify;
    }

    public function settings()
    {
        $row = WaAnnouncementSetting::query()->first();
        if (! $row) {
            $row = WaAnnouncementSetting::create([
                'company_name' => 'Beyond Enterprise',
                'default_header' => 'Beyond Enterprise',
                'serial_prefix' => 'BEY/ANN/',
                'next_serial' => 1,
                'serial_padding' => 4,
                'timezone' => 'Africa/Kigali',
                'timezone_offset' => '+02:00',
            ]);
        }

        return $row;
    }

    public function updateSettings(array $data)
    {
        $row = $this->settings();
        $row->fill([
            'company_name' => $data['company_name'] ?? $row->company_name,
            'default_header' => $data['default_header'] ?? $row->default_header,
            'serial_prefix' => $data['serial_prefix'] ?? $row->serial_prefix,
            'next_serial' => (int) ($data['next_serial'] ?? $row->next_serial),
            'serial_padding' => (int) ($data['serial_padding'] ?? $row->serial_padding),
            'timezone' => $data['timezone'] ?? $row->timezone,
            'timezone_offset' => $data['timezone_offset'] ?? $row->timezone_offset,
        ]);
        $row->save();

        return $row;
    }

    /**
     * Same reference scheme as Letters: {letter_serial_no}/{yy}/{NNNNNNN}
     * e.g. BCL/L-/26/0000005 — shared sequence with the letters table.
     */
    public function allocateSerial()
    {
        return \App\Support\LetterReference::next();
    }

    public function eligibleUsers($filter = 'all', $search = '')
    {
        return $this->people->eligibleForTasks($filter, $search);
    }

    public function resolvePeople(array $ids)
    {
        return $this->people->resolveDirectoryPeople($ids);
    }

    /**
     * Fill parent, student, and class for a school letter, then apply preview edits.
     */
    public function withSchoolRoster(array $people, array $overrides = [])
    {
        $byPhone = $this->schoolOverrideMap($overrides);
        foreach ($people as $i => $person) {
            if (! is_array($person)) {
                continue;
            }
            $phone = isset($person['phone']) ? (string) $person['phone'] : '';
            $digits = preg_replace('/\D+/', '', $phone);
            if ($digits !== '') {
                try {
                    $customer = $this->people->findCustomerByLoosePhone($digits);
                } catch (\Throwable $e) {
                    $customer = null;
                }
                if ($customer) {
                    if (empty($person['customer_name'])) {
                        $person['customer_name'] = $customer->name;
                    }
                    if (SchoolMerge::clean(isset($person['student']) ? $person['student'] : '') === '') {
                        $person['company_name'] = $customer->company_name;
                    }
                    if (SchoolMerge::clean(isset($person['class']) ? $person['class'] : '') === '') {
                        $person['city'] = $customer->city;
                    }
                }
            }
            $person = SchoolMerge::decorate($person);
            $tail = $digits !== '' ? substr($digits, -9) : '';
            $override = [];
            if ($digits !== '' && isset($byPhone[$digits])) {
                $override = $byPhone[$digits];
            } elseif ($tail !== '' && isset($byPhone[$tail])) {
                $override = $byPhone[$tail];
            }
            foreach (['parent', 'student', 'class'] as $key) {
                if (! isset($override[$key])) {
                    continue;
                }
                $value = SchoolMerge::clean($override[$key]);
                if ($value === '') {
                    continue;
                }
                $person[$key] = $value;
            }
            if (! empty($override['parent']) && empty($person['name_edited'])) {
                $person['name'] = $person['parent'];
            }
            if ($override !== [] && $digits !== '') {
                $this->rememberSchoolRoster($digits, $person);
            }
            $people[$i] = $person;
        }

        return $people;
    }

    public function categories()
    {
        return WaAnnouncementCategory::orderBy('name')->get();
    }

    public function templates()
    {
        return WaAnnouncementTemplate::orderByDesc('id')->get();
    }

    public function list($status = null, $q = null)
    {
        $query = WaAnnouncement::query()
            ->where('status', '!=', 'deleted')
            ->orderByDesc('created_at');

        if ($status === 'scheduled') {
            $query->where(function ($w) {
                $w->where('status', 'scheduled')->orWhere(function ($x) {
                    $x->where('is_scheduled', true)->where('status', '!=', 'sent');
                });
            });
        } elseif ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('subject', 'like', '%' . $q . '%')
                    ->orWhere('reference', 'like', '%' . $q . '%')
                    ->orWhere('header', 'like', '%' . $q . '%');
            });
        }

        return $query->paginate(30);
    }

    public function create(array $data, $adminId = null)
    {
        $settings = $this->settings();
        $audience = isset($data['audience']) ? (string) $data['audience'] : 'people';
        $school = SchoolMerge::isSchool(
            isset($data['template_name']) ? $data['template_name'] : '',
            isset($data['subject']) ? $data['subject'] : '',
            isset($data['body']) ? $data['body'] : '',
            trim((isset($data['header']) ? $data['header'] : '').' '.(isset($data['footer']) ? $data['footer'] : ''))
        );
        if ($school && $audience === 'group') {
            $data['personalized'] = true;
        }
        if ($audience === 'group') {
            $jids = isset($data['group_jids']) ? (array) $data['group_jids'] : [];
            if (! empty($data['group_jid'])) {
                $jids[] = $data['group_jid'];
            }
            $recipients = ! empty($data['personalized'])
                ? $this->personalizedGroupMembers($jids, isset($data['name_overrides']) ? (array) $data['name_overrides'] : [])
                : $this->groupRecipients($jids);
            $ccs = [];
        } elseif ($audience === 'contacts') {
            $recipients = $this->whatsappContacts();
            $ccs = [];
        } else {
            $recipientIds = array_values(array_unique(array_filter($data['recipient_ids'] ?? [])));
            $ccIds = array_values(array_unique(array_filter($data['cc_ids'] ?? [])));
            $recipients = $this->resolvePeople($recipientIds);
            $ccs = $this->resolvePeople($ccIds);
        }
        if (! empty($data['personalized'])) {
            $overrides = isset($data['name_overrides']) ? (array) $data['name_overrides'] : [];
            $recipients = $this->applyNameOverrides($recipients, $overrides);
            $ccs = $this->applyNameOverrides($ccs, $overrides);
            $this->persistEditedGroupNames($recipients);
            $this->persistEditedGroupNames($ccs);
        }
        if ($school) {
            $overrides = isset($data['school_overrides']) ? (array) $data['school_overrides'] : [];
            $recipients = $this->withSchoolRoster($recipients, $overrides);
            $ccs = $this->withSchoolRoster($ccs, $overrides);
        }

        $sendMode = $data['send_mode'] ?? 'now';
        $scheduleAt = ! empty($data['schedule_at']) ? Carbon::parse($data['schedule_at']) : null;
        $isScheduled = $sendMode === 'schedule' && $scheduleAt;

        $announcement = WaAnnouncement::create([
            'reference' => $this->allocateSerial(),
            'subject' => $data['subject'] ?? 'Announcement',
            'header' => $data['header'] ?? $settings->default_header,
            'body' => $data['body'] ?? '',
            'footer' => $data['footer'] ?? '',
            'category_id' => $data['category_id'] ?? null,
            'status' => $isScheduled ? 'scheduled' : 'draft',
            'whatsapp_status' => $isScheduled ? 'scheduled' : 'pending',
            'send_whatsapp' => ! empty($data['send_whatsapp']),
            'send_sms' => ! empty($data['send_sms']),
            'personalized' => ! empty($data['personalized']),
            'is_scheduled' => (bool) $isScheduled,
            'scheduled_for' => $isScheduled ? $scheduleAt : null,
            'schedules_json' => $isScheduled ? json_encode([
                ['scheduled_at' => $scheduleAt->toDateTimeString(), 'status' => 'pending'],
            ]) : null,
            'recipients_json' => json_encode($recipients),
            'cc_json' => json_encode($ccs),
            'attachment_path' => $data['attachment_path'] ?? null,
            'attachment_name' => $data['attachment_name'] ?? null,
            'created_by' => $adminId,
            'cloned_from_id' => $data['cloned_from_id'] ?? null,
        ]);

        foreach (($data['reminders'] ?? []) as $time) {
            if (! $time) {
                continue;
            }
            WaAnnouncementReminder::create([
                'announcement_id' => $announcement->id,
                'reminder_time' => Carbon::parse($time),
                'is_sent' => false,
            ]);
        }

        if (! $isScheduled && (! empty($data['send_whatsapp']) || ! empty($data['send_sms']))) {
            $this->startDelivery($announcement->fresh());
        }

        return $announcement->fresh();
    }

    public function startDelivery(WaAnnouncement $announcement)
    {
        $recipients = $announcement->recipients();
        $contactSend = false;
        foreach ($recipients as $person) {
            if (isset($person['kind']) && $person['kind'] === 'contact') {
                $contactSend = true;
                break;
            }
        }
        $this->markGroupsSent($recipients);
        if ($contactSend && count($recipients) > 5) {
            $announcement->status = 'sending';
            $announcement->whatsapp_status = 'sending';
            $announcement->is_scheduled = false;
            $announcement->save();
            \App\Jobs\SendWaAnnouncementBatchJob::dispatch($announcement->id)
                ->onConnection('database')
                ->onQueue('whatsapp');

            return;
        }
        $this->notify->dispatchAnnouncement($announcement);
    }

    protected function markGroupsSent(array $recipients)
    {
        $groups = app(\App\Services\WhatsApp\GroupContactExportService::class);
        $seen = [];
        foreach ($recipients as $person) {
            $jid = isset($person['group_jid']) ? trim((string) $person['group_jid']) : '';
            if (substr($jid, -5) !== '@g.us' || isset($seen[$jid])) {
                continue;
            }
            $seen[$jid] = true;
            $groups->rememberSent($jid);
        }
    }

    protected function groupRecipients(array $jids)
    {
        $wanted = [];
        foreach ($jids as $jid) {
            $jid = trim((string) $jid);
            if ($jid !== '' && substr($jid, -5) === '@g.us') {
                $wanted[$jid] = true;
            }
        }
        $catalog = [];
        foreach (app(\App\Services\WhatsApp\GroupContactExportService::class)->announcementGroups() as $group) {
            $catalog[$group['jid']] = $group;
        }
        $out = [];
        foreach (array_keys($wanted) as $jid) {
            if (! isset($catalog[$jid])) {
                continue;
            }
            $name = trim((string) $catalog[$jid]['name']);
            if ($name === '') {
                $profile = app(BeyondWasenderService::class)->groupProfile($jid);
                $name = trim((string) (isset($profile['name']) ? $profile['name'] : ''));
            }
            if ($name === '') {
                $name = 'WhatsApp group';
            }
            $out[] = [
                'id' => 'group',
                'kind' => 'group',
                'group_jid' => $jid,
                'name' => $name,
                'phone' => '',
                'email' => '',
            ];
            app(\App\Services\WhatsApp\GroupContactExportService::class)->rememberUse($jid);
        }
        if (! $out) {
            throw new \InvalidArgumentException('Choose at least one WhatsApp group.');
        }

        return $out;
    }

    public function previewGroupPeople(array $jids)
    {
        return $this->personalizedGroupMembers($jids, []);
    }

    protected function personalizedGroupMembers(array $jids, array $overrides = [])
    {
        $export = app(\App\Services\WhatsApp\GroupContactExportService::class);
        $byPhone = [];
        $names = [];
        foreach ($overrides as $phone => $name) {
            $digits = preg_replace('/\D+/', '', (string) $phone);
            $name = trim((string) $name);
            if ($digits === '' || $name === '') {
                continue;
            }
            $names[$digits] = $name;
            if (strlen($digits) > 9) {
                $names[substr($digits, -9)] = $name;
            }
        }
        foreach ($this->groupRecipients($jids) as $group) {
            $fetched = $export->rowsForGroup($group['group_jid']);
            foreach ((isset($fetched['rows']) ? $fetched['rows'] : []) as $row) {
                $phone = trim((string) (isset($row['phone']) ? $row['phone'] : ''));
                $digits = preg_replace('/\D+/', '', $phone);
                if (strlen($digits) < 8 || isset($byPhone[$digits]) || ! $export->shouldNotify($group['group_jid'], $digits)) {
                    continue;
                }
                $tail = substr($digits, -9);
                $original = trim((string) (isset($row['name']) ? $row['name'] : ''));
                $waName = $original;
                $edited = false;
                if (isset($names[$digits])) {
                    $waName = $names[$digits];
                    $edited = $waName !== $original;
                } elseif ($tail !== '' && isset($names[$tail])) {
                    $waName = $names[$tail];
                    $edited = $waName !== $original;
                }
                if ($waName === '' || preg_match('/^\d+$/', $waName)) {
                    $waName = $digits;
                    $edited = false;
                }
                $byPhone[$digits] = [
                    'id' => 'wa:'.$digits,
                    'kind' => 'contact',
                    'name' => $waName,
                    'wa_name' => $waName,
                    'phone' => $digits,
                    'email' => '',
                    'group' => $group['name'],
                    'group_jid' => $group['group_jid'],
                    'name_edited' => $edited,
                ];
            }
        }
        if (! $byPhone) {
            throw new \InvalidArgumentException('No phone numbers were returned for the selected groups, so a personalized message cannot be sent to each member.');
        }

        return array_values($byPhone);
    }

    protected function applyNameOverrides(array $people, array $overrides)
    {
        $names = [];
        foreach ($overrides as $phone => $name) {
            $digits = preg_replace('/\D+/', '', (string) $phone);
            $name = trim((string) $name);
            if ($digits === '' || $name === '') {
                continue;
            }
            $names[$digits] = $name;
            if (strlen($digits) > 9) {
                $names[substr($digits, -9)] = $name;
            }
        }
        if (! $names) {
            return $people;
        }
        foreach ($people as $i => $person) {
            $digits = preg_replace('/\D+/', '', (string) (isset($person['phone']) ? $person['phone'] : ''));
            $tail = $digits !== '' ? substr($digits, -9) : '';
            $override = '';
            if ($digits !== '' && isset($names[$digits])) {
                $override = $names[$digits];
            } elseif ($tail !== '' && isset($names[$tail])) {
                $override = $names[$tail];
            }
            if ($override === '') {
                continue;
            }
            $current = trim((string) (isset($person['name']) ? $person['name'] : ''));
            if ($override === $current) {
                continue;
            }
            $people[$i]['name'] = $override;
            $people[$i]['wa_name'] = $override;
            $people[$i]['name_edited'] = true;
        }

        return $people;
    }

    protected function schoolOverrideMap(array $overrides)
    {
        $byPhone = [];
        foreach ($overrides as $phone => $fields) {
            if (! is_array($fields)) {
                continue;
            }
            $digits = preg_replace('/\D+/', '', (string) $phone);
            if ($digits === '') {
                continue;
            }
            $byPhone[$digits] = $fields;
            if (strlen($digits) > 9) {
                $byPhone[substr($digits, -9)] = $fields;
            }
        }

        return $byPhone;
    }

    protected function rememberSchoolRoster($phone, array $person)
    {
        try {
            $customer = $this->people->findCustomerByLoosePhone($phone);
        } catch (\Throwable $e) {
            return;
        }
        if (! $customer) {
            return;
        }
        $dirty = false;
        $student = SchoolMerge::clean(isset($person['student']) ? $person['student'] : '');
        $className = SchoolMerge::clean(isset($person['class']) ? $person['class'] : '');
        if ($student !== '' && $student !== (string) $customer->company_name) {
            $customer->company_name = $student;
            $dirty = true;
        }
        if ($className !== '' && $className !== (string) $customer->city) {
            $customer->city = $className;
            $dirty = true;
        }
        if ($dirty) {
            $customer->save();
        }
    }

    protected function persistEditedGroupNames(array $people)
    {
        $groups = app(\App\Services\WhatsApp\GroupContactExportService::class);
        foreach ($people as $person) {
            if (empty($person['name_edited'])) {
                continue;
            }
            $jid = isset($person['group_jid']) ? trim((string) $person['group_jid']) : '';
            $phone = isset($person['phone']) ? (string) $person['phone'] : '';
            $name = isset($person['name']) ? trim((string) $person['name']) : '';
            if ($jid === '' || $phone === '' || $name === '') {
                continue;
            }
            try {
                $groups->saveDisplayName($jid, $phone, $name);
            } catch (\InvalidArgumentException $e) {
                // The announcement still uses the preview name.
            }
        }
    }

    protected function whatsappContacts()
    {
        $listed = app(BeyondWasenderService::class)->listContacts();
        if (empty($listed['success'])) {
            throw new \InvalidArgumentException(isset($listed['error']) ? $listed['error'] : 'Could not load WhatsApp contacts.');
        }
        $contacts = isset($listed['contacts']) ? $listed['contacts'] : [];
        if (! $contacts) {
            throw new \InvalidArgumentException('No WhatsApp contacts were returned for this account.');
        }

        return $contacts;
    }

    public function cloneAnnouncement(WaAnnouncement $source)
    {
        return [
            'subject' => $source->subject,
            'header' => $source->header,
            'body' => $source->body,
            'footer' => $source->footer,
            'category_id' => $source->category_id,
            'cloned_from_id' => $source->id,
            'recipient_ids' => array_column($source->recipients(), 'id'),
            'cc_ids' => array_column($source->ccRecipients(), 'id'),
        ];
    }

    public function softDelete($id)
    {
        $row = WaAnnouncement::findOrFail($id);
        $row->status = 'deleted';
        $row->save();

        return true;
    }

    public function processScheduledSends()
    {
        $now = Carbon::now();
        $due = WaAnnouncement::query()
            ->where('status', 'scheduled')
            ->where('is_scheduled', true)
            ->where('scheduled_for', '<=', $now)
            ->get();

        $count = 0;
        foreach ($due as $a) {
            $claimed = WaAnnouncement::where('id', $a->id)
                ->where('status', 'scheduled')
                ->update([
                    'status' => 'sending',
                    'whatsapp_status' => 'sending',
                    'is_scheduled' => false,
                ]);
            if (! $claimed) {
                continue;
            }
            $a = $a->fresh();
            if ($a->send_whatsapp || $a->send_sms) {
                $this->startDelivery($a);
                $count++;
            } else {
                $a->status = 'sent';
                $a->whatsapp_status = 'sent';
                $a->save();
                $count++;
            }
        }

        return $count;
    }

    public function processReminders()
    {
        $now = Carbon::now();
        $due = WaAnnouncementReminder::query()
            ->where('is_sent', false)
            ->where('reminder_time', '<=', $now)
            ->with('announcement')
            ->get();

        $count = 0;
        $sentFor = [];
        foreach ($due as $reminder) {
            $claimed = WaAnnouncementReminder::where('id', $reminder->id)
                ->where('is_sent', false)
                ->update(['is_sent' => true]);
            if (! $claimed) {
                continue;
            }
            $a = $reminder->announcement;
            if (! $a || $a->status === 'deleted') {
                continue;
            }
            if (isset($sentFor[$a->id])) {
                continue;
            }
            $sentFor[$a->id] = true;
            app(\App\Services\WhatsApp\GroupContactExportService::class)->fillAnnouncementRecipients($a, false);
            $a = $a->fresh();
            $this->notify->sendReminder($a);
            $count++;
        }

        return $count;
    }

    public function storeCategory(array $data)
    {
        $name = trim($data['name'] ?? '');
        $slug = Str::slug($name) ?: Str::random(6);

        return WaAnnouncementCategory::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);
    }

    public function deleteCategory($id)
    {
        return WaAnnouncementCategory::where('id', $id)->delete();
    }

    public function storeTemplate(array $data)
    {
        return WaAnnouncementTemplate::create([
            'name' => $data['name'] ?? 'Template',
            'category_id' => $data['category_id'] ?? null,
            'subject' => $data['subject'] ?? null,
            'header' => $data['header'] ?? null,
            'body' => $data['body'] ?? null,
        ]);
    }

    public function deleteTemplate($id)
    {
        return WaAnnouncementTemplate::where('id', $id)->delete();
    }

    public function reminders()
    {
        return WaAnnouncementReminder::with('announcement')
            ->orderByDesc('reminder_time')
            ->paginate(40);
    }

    public function deleteReminder($id)
    {
        return WaAnnouncementReminder::where('id', $id)->delete();
    }
}
