<?php

namespace App\Services\Attendance;

use App\Attendance;
use App\AttendanceCorrection;
use App\Employee;
use App\Event;
use App\EventAssignment;
use App\InternshipEnrolment;
use App\Services\EventTimesheetService;
use App\Services\TimesheetService;
use App\TimesheetEntry;
use App\User;
use App\WhatsApp\AttendanceActivity;
use App\WhatsApp\WhatsAppConversation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class AttendanceWhatsAppService
{
    protected $policy;
    protected $location;

    public function __construct(AttendancePolicyService $policy, AttendanceLocationService $location)
    {
        $this->policy = $policy;
        $this->location = $location;
    }

    public function checkIn(array $context, array $params = [])
    {
        $person = $this->resolve($context, $params);
        if (empty($person['success']) || ! empty($person['needs_choice'])) {
            return $person;
        }
        $params = $this->pullJob($params);
        if (! empty($params['job'])) {
            return $this->checkInAssignment($context, $params);
        }
        if ($this->looksLikeFieldArrival($params) && empty($params['job'])) {
            $jobs = $this->assignmentsToday($person);
            if ($jobs->count() === 1) {
                $params['job'] = $this->jobToken($jobs->first());

                return $this->checkInAssignment($context, $params);
            }
            if ($jobs->count() > 1) {
                return $this->jobChoices($jobs);
            }
        }

        return $this->openSession($person, $params, null);
    }

    public function checkOut(array $context, array $params = [])
    {
        $person = $this->resolve($context, $params);
        if (empty($person['success']) || ! empty($person['needs_choice'])) {
            return $person;
        }
        $params = $this->pullJob($params);
        if (! empty($params['job'])) {
            return $this->checkOutAssignment($context, $params);
        }

        return $this->closeSession($person, $params, null);
    }

    public function status(array $context, array $params = [])
    {
        $person = $this->resolve($context, $params);
        if (empty($person['success']) || ! empty($person['needs_choice'])) {
            return $person;
        }
        $open = $this->openToday($person);
        if ($open) {
            return [
                'success' => true,
                'state' => 'checked_in',
                'started' => $this->clock($open->checkin),
                'duration' => $this->formatDuration($this->minutesBetween($open->checkin, Carbon::now()->format('H:i:s'))),
                'location_status' => $open->location_status,
            ];
        }
        $closed = $this->todayRow($person);
        if ($closed && $this->isClosed($closed)) {
            return [
                'success' => true,
                'state' => 'checked_out',
                'started' => $this->clock($closed->checkin),
                'ended' => $this->clock($closed->checkout),
                'duration' => $this->formatDuration($this->minutesBetween($closed->checkin, $closed->checkout)),
            ];
        }

        return ['success' => true, 'state' => 'not_checked_in'];
    }

    public function hours(array $context, array $params = [])
    {
        $person = $this->resolve($context, $params);
        if (empty($person['success']) || ! empty($person['needs_choice'])) {
            return $person;
        }
        $today = $this->todayRow($person);
        $todayMinutes = 0;
        if ($today && $today->checkin) {
            $end = $this->isClosed($today) ? $today->checkout : Carbon::now()->format('H:i:s');
            $todayMinutes = $this->minutesBetween($today->checkin, $end);
        }
        $weekMinutes = 0;
        foreach ($this->rowsFor($person)->whereBetween('date', [Carbon::now()->startOfWeek()->toDateString(), Carbon::now()->toDateString()])->get() as $row) {
            if (! $row->checkin) {
                continue;
            }
            $end = $this->isClosed($row) ? $row->checkout : ($row->date === Carbon::today()->toDateString() ? Carbon::now()->format('H:i:s') : $row->checkin);
            $weekMinutes += $this->minutesBetween($row->checkin, $end);
        }
        $timesheet = null;
        if ($person['user_id'] && Schema::hasTable('be_timesheet_entries')) {
            $timesheet = round((float) TimesheetEntry::where('user_id', $person['user_id'])->whereDate('entry_date', Carbon::today())->sum('hours'), 2);
        }

        return [
            'success' => true,
            'today' => $this->formatDuration($todayMinutes),
            'week' => $this->formatDuration($weekMinutes),
            'timesheet_hours' => $timesheet,
        ];
    }

    public function assignment(array $context, array $params = [])
    {
        $person = $this->resolve($context, $params);
        if (empty($person['success']) || ! empty($person['needs_choice'])) {
            return $person;
        }
        $rows = [];
        foreach ($this->assignmentsToday($person) as $assignment) {
            $event = $assignment->event;
            $rows[] = [
                'token' => $this->jobToken($assignment),
                'name' => $event ? $event->name : 'Assignment',
                'venue' => $event ? $event->venue : null,
                'role' => $assignment->assignment_role,
                'reporting_time' => $assignment->reporting_time ? $assignment->reporting_time->format('H:i') : null,
                'status' => $assignment->attendance_status,
            ];
        }
        $this->audit($context, $person, null, 'assignment_lookup', (string) count($rows));

        return ['success' => true, 'assignments' => $rows];
    }

    public function checkInAssignment(array $context, array $params = [])
    {
        $person = $this->resolve($context, $params);
        if (empty($person['success']) || ! empty($person['needs_choice'])) {
            return $person;
        }
        $job = $this->resolveJob($person, $params);
        if (empty($job['success'])) {
            return $job;
        }
        if (isset($params['latitude']) || isset($params['longitude'])) {
            $placed = $this->placeLocation($job['event'], $params);
            if (empty($placed['success'])) {
                return $placed;
            }
        } elseif ($this->policy->fieldRequiresLocation()) {
            $this->audit($context, $person, null, 'location_required', $job['token']);

            return [
                'success' => true,
                'location_required' => true,
                'job' => $job['token'],
                'assignment_id' => $job['assignment']->id,
            ];
        } else {
            $placed = null;
        }

        return $this->openSession($person, $params, $job, $placed);
    }

    public function checkOutAssignment(array $context, array $params = [])
    {
        $person = $this->resolve($context, $params);
        if (empty($person['success']) || ! empty($person['needs_choice'])) {
            return $person;
        }
        $job = $this->resolveJob($person, $params);
        if (empty($job['success'])) {
            return $job;
        }

        return $this->closeSession($person, $params, $job['assignment']->id);
    }

    public function requestCorrection(array $context, array $params = [])
    {
        $person = $this->resolve($context, $params);
        if (empty($person['success']) || ! empty($person['needs_choice'])) {
            return $person;
        }
        if (! Schema::hasTable('attendance_correction_requests')) {
            return ['success' => false, 'error' => 'unavailable'];
        }
        $open = $this->rowsFor($person)->where('date', '<', Carbon::today()->toDateString())->where(function ($q) {
            $q->whereNull('checkout')->orWhere('checkout', '');
        })->orderByDesc('date')->first();
        $row = AttendanceCorrection::create([
            'employee_id' => $person['employee'] ? $person['employee']->id : null,
            'user_id' => $person['user_id'],
            'intern_user_id' => $person['intern_user_id'],
            'attendance_id' => $open ? $open->id : null,
            'reason' => isset($params['text']) ? substr((string) $params['text'], 0, 500) : null,
            'status' => AttendanceCorrection::PENDING,
            'conversation_id' => isset($context['conversation_id']) ? $context['conversation_id'] : null,
            'provider_message_id' => isset($params['provider_message_id']) ? $params['provider_message_id'] : null,
        ]);
        $this->audit($context, $person, $open ? $open->id : null, 'correction_requested', (string) $row->id);

        return ['success' => true, 'correction_id' => $row->id, 'status' => AttendanceCorrection::PENDING];
    }

    public function correctionStatus(array $context, array $params = [])
    {
        $person = $this->resolve($context, $params);
        if (empty($person['success']) || ! empty($person['needs_choice'])) {
            return $person;
        }
        if (! Schema::hasTable('attendance_correction_requests')) {
            return ['success' => true, 'corrections' => []];
        }
        $rows = AttendanceCorrection::where('user_id', $person['user_id'])->orderByDesc('id')->limit(5)->get();
        $list = [];
        foreach ($rows as $row) {
            $list[] = ['id' => $row->id, 'status' => $row->status];
        }

        return ['success' => true, 'corrections' => $list];
    }

    public function approveCorrection($id, User $staff, $checkoutTime)
    {
        if (! $this->staffMayApprove($staff)) {
            return ['success' => false, 'error' => 'forbidden'];
        }
        $checkoutTime = trim((string) $checkoutTime);
        if (! preg_match('/^\d{1,2}:\d{2}$/', $checkoutTime)) {
            return ['success' => false, 'error' => 'time_required'];
        }
        $request = AttendanceCorrection::find($id);
        if (! $request || ! $request->attendance_id) {
            return ['success' => false, 'error' => 'not_found'];
        }
        $attendance = Attendance::find($request->attendance_id);
        if (! $attendance) {
            return ['success' => false, 'error' => 'not_found'];
        }

        return DB::transaction(function () use ($request, $attendance, $staff, $checkoutTime) {
            $attendance->checkout = strlen($checkoutTime) === 5 ? $checkoutTime.':00' : $checkoutTime;
            $attendance->note = trim((string) $attendance->note."\nApproved correction");
            $attendance->save();
            $request->status = AttendanceCorrection::APPROVED;
            $request->requested_checkout = substr($checkoutTime, 0, 5);
            $request->approver_user_id = $staff->id;
            $request->reviewed_at = now();
            $request->save();
            $this->audit([], [
                'user_id' => $staff->id,
                'employee' => $attendance->employee_id ? (object) ['id' => $attendance->employee_id] : null,
            ], $attendance->id, 'correction_approved', (string) $request->id);

            return ['success' => true, 'attendance_id' => $attendance->id, 'checkout' => $this->clock($attendance->checkout)];
        });
    }

    public function panel(WhatsAppConversation $conversation)
    {
        $context = app(\App\Services\Assistant\AssistantContextBuilder::class)->build($conversation);
        $person = $this->resolve($context, []);
        if (! empty($person['needs_choice']) || empty($person['success'])) {
            return null;
        }
        $open = $this->openToday($person);
        $pending = Schema::hasTable('attendance_correction_requests')
            ? AttendanceCorrection::where('user_id', $person['user_id'])->where('status', AttendanceCorrection::PENDING)->count()
            : 0;

        return [
            'name' => $person['employee'] ? $person['employee']->name : ($person['intern_user_id'] ? 'Intern' : null),
            'employee_id' => $person['employee'] ? $person['employee']->id : null,
            'state' => $open ? 'Checked in' : 'Not checked in',
            'started' => $open ? $this->clock($open->checkin) : null,
            'duration' => $open ? $this->formatDuration($this->minutesBetween($open->checkin, Carbon::now()->format('H:i:s'))) : null,
            'location_status' => $open ? $open->location_status : null,
            'assignment_id' => $open ? $open->event_assignment_id : null,
            'pending_corrections' => $pending,
            'attendance_id' => $open ? $open->id : null,
        ];
    }

    public function metrics()
    {
        $zero = [
            'checked_in_now' => 0,
            'field_on_site' => 0,
            'missing_checkout' => 0,
            'pending_corrections' => 0,
            'location_review' => 0,
            'failures' => 0,
        ];
        if (! Schema::hasTable('attendances')) {
            return $zero;
        }
        $open = Attendance::whereDate('date', Carbon::today())->where(function ($q) {
            $q->whereNull('checkout')->orWhere('checkout', '');
        });

        return [
            'checked_in_now' => (clone $open)->count(),
            'field_on_site' => (clone $open)->whereNotNull('event_assignment_id')->count(),
            'missing_checkout' => Attendance::where('date', '<', Carbon::today()->toDateString())->where(function ($q) {
                $q->whereNull('checkout')->orWhere('checkout', '');
            })->count(),
            'pending_corrections' => Schema::hasTable('attendance_correction_requests')
                ? AttendanceCorrection::where('status', AttendanceCorrection::PENDING)->count()
                : 0,
            'location_review' => Schema::hasColumn('attendances', 'location_status')
                ? Attendance::where('location_status', 'LOCATION_REVIEW_REQUIRED')->count()
                : 0,
            'failures' => Schema::hasTable('whatsapp_attendance_activities')
                ? AttendanceActivity::where('type', 'failed')->count()
                : 0,
        ];
    }

    protected function openSession(array $person, array $params, $job, array $placed = null)
    {
        if (! $person['user_id']) {
            return ['success' => false, 'error' => 'no_user'];
        }
        $messageId = isset($params['provider_message_id']) ? (string) $params['provider_message_id'] : '';
        if ($messageId !== '') {
            $prior = Attendance::where('whatsapp_message_id', $messageId)->first();
            if ($prior) {
                $this->audit([], $person, $prior->id, 'duplicate_prevented', $messageId);

                return $this->already($prior);
            }
        }
        $stale = $this->rowsFor($person)->where('date', '<', Carbon::today()->toDateString())->where(function ($q) {
            $q->whereNull('checkout')->orWhere('checkout', '');
        })->first();
        if ($stale) {
            $this->audit([], $person, $stale->id, 'checkout_required', (string) $stale->date);

            return ['success' => false, 'error' => 'checkout_required', 'date' => (string) $stale->date];
        }

        try {
            return DB::transaction(function () use ($person, $params, $job, $placed, $messageId) {
                $open = $this->rowsFor($person)->whereDate('date', Carbon::today())->lockForUpdate()->orderByDesc('id')->first();
                if ($open && ! $this->isClosed($open)) {
                    $this->audit([], $person, $open->id, 'duplicate_prevented', 'open');

                    return $this->already($open);
                }
                if ($open && $this->isClosed($open)) {
                    return ['success' => false, 'error' => 'day_closed', 'ended' => $this->clock($open->checkout)];
                }
                $now = Carbon::now();
                $expected = $person['intern_user_id']
                    ? $this->policy->scheduledToday($person['user_id'], $now)['start']
                    : $this->policy->officeExpectedCheckin();
                $schedule = $person['intern_user_id'] ? $this->policy->scheduledToday($person['user_id'], $now) : ['scheduled' => true];
                $row = new Attendance();
                $row->date = $now->toDateString();
                $row->employee_id = $person['employee'] ? $person['employee']->id : null;
                $row->user_id = $person['user_id'];
                $row->intern_user_id = $person['intern_user_id'];
                $row->checkin = $now->format('H:i:s');
                $row->checkout = null;
                $row->status = $this->policy->punctualityStatus($now, $expected);
                $row->note = 'WhatsApp';
                $row->source = 'whatsapp';
                $row->whatsapp_conversation_id = isset($params['conversation_id']) ? $params['conversation_id'] : (isset($person['conversation_id']) ? $person['conversation_id'] : null);
                $row->whatsapp_message_id = $messageId !== '' ? $messageId : null;
                if ($job) {
                    $row->event_assignment_id = $job['assignment']->id;
                }
                if ($placed) {
                    $row->latitude = $placed['latitude'];
                    $row->longitude = $placed['longitude'];
                    $row->location_accuracy = $placed['accuracy'];
                    $row->location_at = $placed['at'];
                    $row->location_status = $placed['status'];
                    $row->distance_meters = $placed['distance_meters'];
                    $row->allowed_radius_meters = $placed['allowed_radius_meters'];
                }
                $row->save();
                if ($job) {
                    $job['assignment']->attendance_status = 'checked_in';
                    $job['assignment']->save();
                }
                $this->audit([], $person, $row->id, 'check_in', $row->checkin);

                return [
                    'success' => true,
                    'attendance_id' => $row->id,
                    'checked_in' => $this->clock($row->checkin),
                    'location_status' => $row->location_status,
                    'off_schedule' => $person['intern_user_id'] && empty($schedule['scheduled']),
                    'assignment_id' => $row->event_assignment_id,
                ];
            });
        } catch (\Throwable $e) {
            $this->audit([], $person, null, 'failed', $e->getMessage());

            return ['success' => false, 'error' => 'failed'];
        }
    }

    protected function closeSession(array $person, array $params, $assignmentId)
    {
        $open = $this->openToday($person);
        if ($assignmentId && $open && (string) $open->event_assignment_id !== (string) $assignmentId) {
            return ['success' => false, 'error' => 'no_open'];
        }
        if (! $open) {
            $closed = $this->todayRow($person);
            if ($closed && $this->isClosed($closed)) {
                return ['success' => false, 'error' => 'day_closed', 'ended' => $this->clock($closed->checkout)];
            }
            $stale = $this->rowsFor($person)->where('date', '<', Carbon::today()->toDateString())->where(function ($q) {
                $q->whereNull('checkout')->orWhere('checkout', '');
            })->first();
            if ($stale) {
                return ['success' => false, 'error' => 'checkout_required', 'date' => (string) $stale->date];
            }

            return ['success' => false, 'error' => 'no_open'];
        }

        try {
            return DB::transaction(function () use ($person, $open, $params) {
                $locked = Attendance::where('id', $open->id)->lockForUpdate()->first();
                if (! $locked || $this->isClosed($locked)) {
                    return ['success' => false, 'error' => 'no_open'];
                }
                $now = Carbon::now();
                $locked->checkout = $now->format('H:i:s');
                $locked->save();
                $minutes = $this->minutesBetween($locked->checkin, $locked->checkout);
                $hours = $this->syncTimesheet($person, $locked, $minutes);
                if ($locked->event_assignment_id) {
                    $this->syncEventTimesheet($locked, $hours);
                }
                $this->audit([], $person, $locked->id, 'check_out', $locked->checkout);

                return [
                    'success' => true,
                    'attendance_id' => $locked->id,
                    'checked_out' => $this->clock($locked->checkout),
                    'duration' => $this->formatDuration($minutes),
                    'hours' => $hours,
                ];
            });
        } catch (\Throwable $e) {
            $this->audit([], $person, $open->id, 'failed', $e->getMessage());

            return ['success' => false, 'error' => 'failed'];
        }
    }

    protected function syncTimesheet(array $person, Attendance $attendance, $minutes)
    {
        if (! $person['user_id'] || ! Schema::hasTable('be_timesheet_entries')) {
            return null;
        }
        $service = app(TimesheetService::class);
        $lunch = 0;
        $week = $this->policy->workingWeek($person['user_id']);
        if ($week) {
            $lunch = $service->lunchMinutesForSpan($minutes, $week->lunch_break_minutes);
        }
        $hours = round(max(0, $minutes - $lunch) / 60, 2);
        $entry = TimesheetEntry::where('user_id', $person['user_id'])->whereDate('entry_date', $attendance->date)->first();
        if ($entry && in_array($entry->status, ['approved'], true)) {
            return (float) $entry->hours;
        }
        if (! $entry) {
            $entry = new TimesheetEntry();
            $entry->user_id = $person['user_id'];
            $entry->employee_name = $person['employee'] ? $person['employee']->name : 'Intern';
            $entry->entry_date = $attendance->date;
        }
        $entry->hours = $hours;
        $entry->notes = 'WhatsApp attendance '.$attendance->id;
        $entry->status = 'submitted';
        $entry->save();
        $service->refreshDayBalance($person['user_id'], $attendance->date);
        $this->audit([], $person, $attendance->id, 'timesheet_updated', (string) $hours);

        return $hours;
    }

    protected function syncEventTimesheet(Attendance $attendance, $hours)
    {
        if (! Schema::hasTable('event_timesheets') || ! Schema::hasTable('event_assignments')) {
            return;
        }
        $assignment = EventAssignment::find($attendance->event_assignment_id);
        if (! $assignment) {
            return;
        }
        $assignment->attendance_status = 'checked_out';
        $assignment->save();
        $sheet = app(EventTimesheetService::class)->findOrCreateForAssignment($assignment);
        app(EventTimesheetService::class)->addEntry($sheet, [
            'work_date' => $attendance->date,
            'hours' => $hours !== null ? $hours : 0,
            'notes' => 'WhatsApp attendance '.$attendance->id,
        ]);
    }

    protected function resolve(array $context, array $params)
    {
        $employee = null;
        $internUserId = null;
        if (! empty($context['employee_id']) && Schema::hasTable('employees')) {
            $employee = Employee::where('id', (int) $context['employee_id'])->where('is_active', true)->first();
        }
        if (! empty($context['intern_user_id']) && Schema::hasTable('internship_enrolments')) {
            $active = InternshipEnrolment::where('student_user_id', (int) $context['intern_user_id'])->where('status', 'active')->exists();
            if ($active) {
                $internUserId = (int) $context['intern_user_id'];
            }
        }
        if (! $employee && ! $internUserId) {
            return ['success' => false, 'error' => 'not_authorized'];
        }
        $text = isset($params['text']) ? strtolower((string) $params['text']) : '';
        $choice = isset($params['context']) ? $params['context'] : '';
        if (strpos($text, 'intern') !== false) {
            $choice = 'intern';
        } elseif (preg_match('/\b(employee|staff|office)\b/', $text)) {
            $choice = 'employee';
        }
        if ($employee && $internUserId && ! in_array($choice, ['employee', 'intern'], true)) {
            return [
                'success' => true,
                'needs_choice' => true,
                'choices' => [
                    ['context' => 'employee', 'title' => 'Employee'],
                    ['context' => 'intern', 'title' => 'Internship'],
                ],
            ];
        }
        if ($choice === 'intern') {
            $employee = null;
        }
        if ($choice === 'employee') {
            $internUserId = null;
        }
        if (! $employee && ! $internUserId) {
            return ['success' => false, 'error' => 'not_authorized'];
        }
        $userId = $internUserId ?: ($employee && $employee->user_id ? (int) $employee->user_id : 0);

        return [
            'success' => true,
            'employee' => $employee,
            'intern_user_id' => $internUserId,
            'user_id' => $userId,
            'conversation_id' => isset($context['conversation_id']) ? $context['conversation_id'] : null,
        ];
    }

    protected function resolveJob(array $person, array $params)
    {
        $token = isset($params['job']) ? (string) $params['job'] : '';
        if ($token === '' && isset($params['text']) && preg_match('/\bjob\s*#?\s*([A-Za-z0-9_-]+)/i', (string) $params['text'], $match)) {
            $token = $match[1];
        }
        if ($token === '' || ! Schema::hasTable('btw_events') || ! Schema::hasTable('event_assignments')) {
            $this->audit([], $person, null, 'assignment_denied', $token);

            return ['success' => false, 'error' => 'not_assigned'];
        }
        $events = Event::query()
            ->where('reference_no', $token)
            ->orWhere('reference_no', 'like', '%'.$token)
            ->limit(5)
            ->get();
        if ($events->isEmpty()) {
            return ['success' => false, 'error' => 'not_assigned'];
        }
        $mine = [];
        $exists = false;
        foreach ($events as $event) {
            $assignments = EventAssignment::where('event_id', $event->id)->get();
            if ($assignments->count() > 0) {
                $exists = true;
            }
            foreach ($assignments as $assignment) {
                if ($this->workerMatches($person, $assignment) && $this->coversToday($assignment, $event)) {
                    $mine[] = ['assignment' => $assignment, 'event' => $event, 'token' => $token];
                }
            }
        }
        if ($mine === []) {
            $this->audit([], $person, null, 'assignment_denied', $token);

            return ['success' => false, 'error' => $exists ? 'not_assigned' : 'not_assigned'];
        }
        if (count($mine) > 1) {
            return $this->jobChoices(collect(array_map(function ($row) {
                return $row['assignment'];
            }, $mine)));
        }

        return ['success' => true] + $mine[0];
    }

    protected function workerMatches(array $person, EventAssignment $assignment)
    {
        $profile = $assignment->workerProfile;
        if (! $profile) {
            return false;
        }
        if ($person['user_id'] && (int) $profile->user_id === (int) $person['user_id']) {
            return true;
        }
        $phone = $person['employee'] ? preg_replace('/\D/', '', (string) $person['employee']->phone_number) : '';
        $workerPhone = preg_replace('/\D/', '', (string) $profile->telephone);

        return $phone !== '' && $workerPhone !== '' && substr($phone, -8) === substr($workerPhone, -8);
    }

    protected function coversToday(EventAssignment $assignment, Event $event)
    {
        $today = Carbon::today();
        if ($assignment->work_start_date && $today->lt($assignment->work_start_date->copy()->startOfDay())) {
            return false;
        }
        if ($assignment->work_end_date && $today->gt($assignment->work_end_date->copy()->endOfDay())) {
            return false;
        }
        if (! $assignment->work_start_date && ! $assignment->work_end_date && $event->event_start_at) {
            $from = $event->setup_start_at ?: ($event->packing_at ?: $event->event_start_at);
            $to = $event->dismantling_end_at ?: ($event->event_end_at ?: $event->event_start_at);

            return $today->between($from->copy()->startOfDay(), $to->copy()->endOfDay());
        }

        return true;
    }

    protected function assignmentsToday(array $person)
    {
        if (! Schema::hasTable('event_assignments')) {
            return collect();
        }
        $rows = EventAssignment::with(['event', 'workerProfile'])->orderByDesc('id')->limit(30)->get();
        $mine = [];
        foreach ($rows as $assignment) {
            $event = $assignment->event;
            if ($event && $this->workerMatches($person, $assignment) && $this->coversToday($assignment, $event)) {
                $mine[] = $assignment;
            }
        }

        return collect($mine);
    }

    protected function placeLocation($event, array $params)
    {
        $lat = isset($params['latitude']) ? $params['latitude'] : null;
        $lng = isset($params['longitude']) ? $params['longitude'] : null;
        if (! $this->location->validCoordinates($lat, $lng)) {
            return ['success' => false, 'error' => 'invalid_location'];
        }
        $at = ! empty($params['location_at']) ? Carbon::parse($params['location_at']) : Carbon::now();
        if (! $this->location->fresh($at)) {
            return ['success' => false, 'error' => 'stale_location'];
        }
        $expectedLat = $event && isset($event->latitude) ? $event->latitude : null;
        $expectedLng = $event && isset($event->longitude) ? $event->longitude : null;
        $radius = $event && ! empty($event->geofence_radius_meters) ? $event->geofence_radius_meters : null;
        $check = $this->location->verify($lat, $lng, $expectedLat, $expectedLng, $radius);

        return [
            'success' => true,
            'latitude' => (float) $lat,
            'longitude' => (float) $lng,
            'accuracy' => isset($params['accuracy']) ? (int) $params['accuracy'] : null,
            'at' => $at,
            'status' => $check['status'],
            'distance_meters' => $check['distance_meters'],
            'allowed_radius_meters' => $check['allowed_radius_meters'],
        ];
    }

    protected function hasFreshLocation(array $params)
    {
        if (! isset($params['latitude']) || ! isset($params['longitude'])) {
            return false;
        }
        $at = ! empty($params['location_at']) ? $params['location_at'] : Carbon::now();

        return $this->location->validCoordinates($params['latitude'], $params['longitude']) && $this->location->fresh($at);
    }

    protected function rowsFor(array $person)
    {
        $query = Attendance::query();
        if ($person['employee']) {
            return $query->where('employee_id', $person['employee']->id);
        }

        return $query->where('intern_user_id', $person['intern_user_id']);
    }

    protected function openToday(array $person)
    {
        return $this->rowsFor($person)->whereDate('date', Carbon::today())->where(function ($q) {
            $q->whereNull('checkout')->orWhere('checkout', '');
        })->orderByDesc('id')->first();
    }

    protected function todayRow(array $person)
    {
        return $this->rowsFor($person)->whereDate('date', Carbon::today())->orderByDesc('id')->first();
    }

    protected function isClosed(Attendance $row)
    {
        return $row->checkout !== null && $row->checkout !== '';
    }

    protected function already(Attendance $row)
    {
        return [
            'success' => true,
            'duplicate' => true,
            'started' => $this->clock($row->checkin),
            'attendance_id' => $row->id,
        ];
    }

    protected function pullJob(array $params)
    {
        if (empty($params['job']) && isset($params['text']) && preg_match('/\bjob\s*#?\s*([A-Za-z0-9_-]+)/i', (string) $params['text'], $match)) {
            $params['job'] = $match[1];
        }

        return $params;
    }

    protected function looksLikeFieldArrival(array $params)
    {
        $text = isset($params['text']) ? strtolower((string) $params['text']) : '';

        return (bool) preg_match('/\b(on site|venue|arrived at the|at the wedding)\b/', $text);
    }

    protected function jobToken(EventAssignment $assignment)
    {
        $event = $assignment->relationLoaded('event') ? $assignment->event : $assignment->event()->first();
        if ($event && $event->reference_no && preg_match('/(\d+)/', (string) $event->reference_no, $match)) {
            return $match[1];
        }

        return $event && $event->reference_no ? (string) $event->reference_no : (string) $assignment->id;
    }

    protected function jobChoices($assignments)
    {
        $choices = [];
        foreach ($assignments as $assignment) {
            $event = $assignment->event;
            $choices[] = [
                'job' => $this->jobToken($assignment),
                'title' => $event ? $event->name : 'Assignment',
            ];
        }

        return ['success' => true, 'needs_choice' => true, 'choices' => $choices];
    }

    protected function minutesBetween($start, $end)
    {
        $a = strtotime((string) $start);
        $b = strtotime((string) $end);
        if ($a === false || $b === false || $b < $a) {
            return 0;
        }

        return (int) round(($b - $a) / 60);
    }

    protected function formatDuration($minutes)
    {
        $minutes = max(0, (int) $minutes);

        return intdiv($minutes, 60).'h '.($minutes % 60).'m';
    }

    protected function clock($time)
    {
        $time = (string) $time;
        if (strlen($time) >= 5) {
            return substr($time, 0, 5);
        }

        return $time;
    }

    protected function staffMayApprove(User $staff)
    {
        $role = Role::find($staff->role_id);
        if (! $role) {
            return false;
        }
        try {
            return $role->hasPermissionTo('whatsapp.attendance.corrections') || (int) $staff->role_id === 1;
        } catch (\Exception $e) {
            return (int) $staff->role_id === 1;
        }
    }

    protected function audit(array $context, array $person, $attendanceId, $type, $body)
    {
        if (! Schema::hasTable('whatsapp_attendance_activities')) {
            return;
        }
        AttendanceActivity::create([
            'conversation_id' => isset($context['conversation_id']) ? $context['conversation_id'] : (isset($person['conversation_id']) ? $person['conversation_id'] : null),
            'actor_user_id' => isset($person['user_id']) ? $person['user_id'] : null,
            'employee_id' => ! empty($person['employee']) ? $person['employee']->id : null,
            'attendance_id' => $attendanceId,
            'type' => $type,
            'body' => substr((string) $body, 0, 500),
        ]);
    }
}
