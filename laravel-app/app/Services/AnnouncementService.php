<?php

namespace App\Services;

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

    public function allocateSerial()
    {
        return DB::transaction(function () {
            $row = WaAnnouncementSetting::query()->lockForUpdate()->first();
            if (! $row) {
                $row = $this->settings();
                $row = WaAnnouncementSetting::query()->lockForUpdate()->find($row->id);
            }
            $n = (int) $row->next_serial;
            $pad = max(1, (int) $row->serial_padding);
            $ref = rtrim((string) $row->serial_prefix, '-') . str_pad((string) $n, $pad, '0', STR_PAD_LEFT);
            $row->next_serial = $n + 1;
            $row->save();

            return $ref;
        });
    }

    public function eligibleUsers($filter = 'all', $search = '')
    {
        return $this->people->eligibleForTasks($filter, $search);
    }

    public function resolvePeople(array $ids)
    {
        $map = [];
        foreach ($this->eligibleUsers('all', '') as $u) {
            $map[$u['id']] = $u;
        }
        $out = [];
        foreach ($ids as $id) {
            if (isset($map[$id])) {
                $out[] = $map[$id];
                continue;
            }
            // Resolve directory ID to BeyondUser snapshot even if not in first page
            try {
                $beyondId = $this->people->resolveToBeyondUserId($id);
                if ($beyondId) {
                    $user = \App\BeyondUser::find($beyondId);
                    if ($user) {
                        $out[] = [
                            'id' => $id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'phone' => $user->phone,
                            'address' => $user->address ?? '',
                            'role' => $user->role ?? '',
                            'source' => 'Resolved',
                        ];
                    }
                }
            } catch (\Exception $e) {
                // skip
            }
        }

        return $out;
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
        $recipientIds = array_values(array_unique(array_filter($data['recipient_ids'] ?? [])));
        $ccIds = array_values(array_unique(array_filter($data['cc_ids'] ?? [])));
        $recipients = $this->resolvePeople($recipientIds);
        $ccs = $this->resolvePeople($ccIds);

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

        if (! $isScheduled && ! empty($data['send_whatsapp'])) {
            $this->notify->dispatchAnnouncement($announcement->fresh());
        }

        return $announcement->fresh();
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
            if ($a->send_whatsapp) {
                $this->notify->dispatchAnnouncement($a);
                $count++;
            } else {
                $a->status = 'sent';
                $a->whatsapp_status = 'sent';
                $a->is_scheduled = false;
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
        foreach ($due as $reminder) {
            $a = $reminder->announcement;
            if (! $a || $a->status === 'deleted') {
                $reminder->is_sent = true;
                $reminder->save();
                continue;
            }
            $this->notify->sendReminder($a);
            $reminder->is_sent = true;
            $reminder->save();
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
