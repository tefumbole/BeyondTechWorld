<?php

namespace App\Services;

use App\BeyondUser;
use App\Task;
use App\TaskAssignment;
use App\TaskAttachment;
use App\TaskCategory;
use App\TaskCc;
use App\TaskReminder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaskService
{
    public function categories()
    {
        return TaskCategory::orderBy('name')->get();
    }

    public function eligibleUsers($filter = 'all', $search = '')
    {
        return app(PeopleDirectoryService::class)->eligibleForTasks($filter, $search);
    }

    public function dashboardStats()
    {
        $assignments = TaskAssignment::with('task')->get()->filter(function ($a) {
            return (bool) $a->task;
        });

        $pending = $assignments->where('status', 'Pending')->count();
        $inProgress = $assignments->where('status', 'In Progress')->count();
        $completed = $assignments->where('status', 'Completed')->count();
        $overdue = $assignments->filter(function ($a) {
            return $this->isOverdue($a);
        })->count();

        return [
            'total' => Task::count(),
            'pending' => $pending,
            'in_progress' => $inProgress,
            'completed' => $completed,
            'overdue' => $overdue,
            'open' => $pending + $inProgress + $overdue,
        ];
    }

    public function allTasks($status = null, $q = null)
    {
        if ($status === 'Overdue') {
            $assignments = TaskAssignment::with(['task.category', 'task.ccRecipients', 'task.assignments'])
                ->orderByDesc('created_at')
                ->get()
                ->filter(function ($a) {
                    return $a->task && $this->isOverdue($a);
                });
            $taskIds = $assignments->pluck('task_id')->unique()->values();
            $query = Task::with(['assignments', 'category', 'ccRecipients'])
                ->whereIn('id', $taskIds)
                ->orderByDesc('created_at');
            if ($q) {
                $query->where('title', 'like', '%' . $q . '%');
            }

            return $query->paginate(30);
        }

        $query = Task::with(['assignments', 'category', 'ccRecipients'])->orderByDesc('created_at');
        if ($status === 'scheduled') {
            $query->where('is_scheduled', true)->where('notifications_sent', false);
        } elseif ($status && $status !== 'all') {
            $query->where('status', $status);
        }
        if ($q) {
            $query->where('title', 'like', '%' . $q . '%');
        }

        return $query->paginate(30);
    }

    public function adminPendingAcceptances()
    {
        return TaskAssignment::with(['task'])
            ->where('status', 'Pending')
            ->orderByDesc('created_at')
            ->get()
            ->filter(function ($a) {
                return (bool) $a->task;
            })->values();
    }

    /**
     * Create one or more tasks from the Create Task form payload.
     *
     * @param  array  $rows  Each: subject, description, priority, color, start_date, start_time, end_date, end_time, assignee_ids[], cc_ids[], reminders[], send_mode (now|schedule), schedule_at, pdf
     */
    public function createTasks(array $rows, $adminId = null)
    {
        $created = [];
        $notifier = app(TaskNotificationService::class);

        foreach ($rows as $row) {
            $title = mb_strtoupper(trim((string) ($row['subject'] ?? '')), 'UTF-8');
            if ($title === '') {
                continue;
            }
            $assigneeIds = array_values(array_unique(array_filter((array) ($row['assignee_ids'] ?? []))));
            if (! count($assigneeIds)) {
                continue;
            }

            $directory = app(PeopleDirectoryService::class);
            $resolvedAssignees = [];
            foreach ($assigneeIds as $ref) {
                $bid = $directory->resolveToBeyondUserId($ref);
                if ($bid) {
                    $resolvedAssignees[] = $bid;
                }
            }
            $resolvedAssignees = array_values(array_unique($resolvedAssignees));
            if (! count($resolvedAssignees)) {
                continue;
            }

            $resolvedCc = [];
            foreach (array_values(array_unique(array_filter((array) ($row['cc_ids'] ?? [])))) as $ccRef) {
                $bid = $directory->resolveToBeyondUserId($ccRef);
                if ($bid && ! in_array($bid, $resolvedAssignees, true)) {
                    $resolvedCc[] = $bid;
                }
            }
            $resolvedCc = array_values(array_unique($resolvedCc));

            $sendMode = ($row['send_mode'] ?? 'now') === 'schedule' ? 'schedule' : 'now';
            $scheduledFor = null;
            if ($sendMode === 'schedule' && ! empty($row['schedule_at'])) {
                try {
                    $scheduledFor = Carbon::parse($row['schedule_at']);
                } catch (\Exception $e) {
                    $scheduledFor = null;
                }
            }

            $taskId = (string) Str::uuid();
            $task = Task::create([
                'id' => $taskId,
                'title' => $title,
                'description' => $row['description'] ?? null,
                'priority' => $row['priority'] ?? 'Medium',
                'color' => $row['color'] ?? '#0b3f90',
                'start_date' => $row['start_date'] ?: null,
                'start_time' => $row['start_time'] ?: null,
                'deadline' => $row['end_date'] ?: null,
                'deadline_time' => $row['end_time'] ?: null,
                'status' => 'Pending',
                'created_by_admin_id' => $adminId ?: (Auth::check() ? Auth::id() : null),
                'category_id' => $row['category_id'] ?? null,
                'notification_template' => $row['notification_template'] ?? null,
                'is_scheduled' => $sendMode === 'schedule' && $scheduledFor,
                'scheduled_for' => $sendMode === 'schedule' ? $scheduledFor : null,
                'notifications_sent' => false,
            ]);

            foreach ($resolvedAssignees as $uid) {
                TaskAssignment::create([
                    'id' => (string) Str::uuid(),
                    'task_id' => $taskId,
                    'user_id' => $uid,
                    'status' => 'Pending',
                    'progress' => 0,
                    'invite_token' => (string) Str::uuid(),
                ]);
            }

            foreach ($resolvedCc as $ccId) {
                TaskCc::create([
                    'id' => (string) Str::uuid(),
                    'task_id' => $taskId,
                    'user_id' => $ccId,
                ]);
            }

            foreach ((array) ($row['reminders'] ?? []) as $reminderAt) {
                if (! $reminderAt) {
                    continue;
                }
                try {
                    $rt = Carbon::parse($reminderAt);
                } catch (\Exception $e) {
                    continue;
                }
                TaskReminder::create([
                    'id' => (string) Str::uuid(),
                    'task_id' => $taskId,
                    'reminder_time' => $rt,
                    'is_sent' => false,
                ]);
            }

            if (! empty($row['pdf_path'])) {
                TaskAttachment::create([
                    'id' => (string) Str::uuid(),
                    'task_id' => $taskId,
                    'file_name' => $row['pdf_name'] ?? 'document.pdf',
                    'file_url' => $row['pdf_path'],
                    'attachment_type' => 'source',
                ]);
            }

            if (! $task->is_scheduled) {
                $notifier->dispatchTaskNotifications($task->fresh(['assignments', 'ccRecipients']));
            }

            $created[] = $task;
        }

        return $created;
    }

    public function processScheduledSends()
    {
        $notifier = app(TaskNotificationService::class);
        $due = Task::where('is_scheduled', true)
            ->where('notifications_sent', false)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->get();

        $count = 0;
        foreach ($due as $task) {
            $notifier->dispatchTaskNotifications($task);
            $count++;
        }

        return $count;
    }

    public function processReminders()
    {
        $notifier = app(TaskNotificationService::class);
        $due = TaskReminder::with('task')
            ->where('is_sent', false)
            ->where('reminder_time', '<=', now())
            ->get();

        $count = 0;
        foreach ($due as $reminder) {
            if ($reminder->task) {
                $notifier->notifyReminder($reminder->task);
            }
            $reminder->is_sent = true;
            $reminder->save();
            $count++;
        }

        return $count;
    }

    public function myTasks($userId, $statusFilter = 'All', $categoryFilter = 'All')
    {
        $query = TaskAssignment::with(['task.category'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at');

        if ($statusFilter && $statusFilter !== 'All') {
            if ($statusFilter === 'Overdue') {
                // filter below
            } else {
                $query->where('status', $statusFilter);
            }
        }

        $assignments = $query->get();

        return $assignments->filter(function ($a) use ($categoryFilter, $statusFilter) {
            if (! $a->task) {
                return false;
            }
            if ($statusFilter === 'Overdue' && ! $this->isOverdue($a)) {
                return false;
            }
            if ($categoryFilter && $categoryFilter !== 'All') {
                return $a->task->category_id === $categoryFilter;
            }

            return true;
        })->values();
    }

    public function pendingAcceptances($userId)
    {
        return TaskAssignment::with(['task.category'])
            ->where('user_id', $userId)
            ->where('status', 'Pending')
            ->orderByDesc('created_at')
            ->get()
            ->filter(function ($a) {
                return (bool) $a->task;
            })->values();
    }

    public function findAssignmentForUser($assignmentId, $userId)
    {
        return TaskAssignment::with('task')
            ->where('id', $assignmentId)
            ->where('user_id', $userId)
            ->first();
    }

    public function findByInviteToken($token)
    {
        return TaskAssignment::with('task')->where('invite_token', $token)->first();
    }

    public function accept(TaskAssignment $assignment, $signature)
    {
        if ($assignment->status !== 'Pending' && $assignment->acceptance_signature) {
            return $assignment;
        }

        $assignment->status = 'Accepted';
        $assignment->acceptance_signature = $signature;
        $assignment->signature_at = now();
        $assignment->accepted_at = now();
        $assignment->last_update_at = now();
        $assignment->save();

        try {
            app(TaskNotificationService::class)->notifyAccepted($assignment);
        } catch (\Exception $e) {
            // non-blocking
        }

        return $assignment;
    }

    public function decline(TaskAssignment $assignment)
    {
        $assignment->status = 'Declined';
        $assignment->declined_at = now();
        $assignment->last_update_at = now();
        $assignment->save();

        return $assignment;
    }

    public function updateProgress(TaskAssignment $assignment, $progress, $status)
    {
        $progress = max(0, min(100, (int) $progress));
        $allowed = ['Accepted', 'In Progress', 'Completed'];
        if (! in_array($status, $allowed, true)) {
            $status = $assignment->status;
        }

        if ($progress >= 100) {
            $status = 'Completed';
        }

        $assignment->progress = $progress;
        $assignment->status = $status;
        $assignment->last_update_at = now();
        if ($status === 'Completed' && ! $assignment->completed_at) {
            $assignment->completed_at = now();
        }
        $assignment->save();

        DB::table('task_updates')->insert([
            'id' => (string) Str::uuid(),
            'assignment_id' => $assignment->id,
            'progress' => $progress,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(TaskNotificationService::class)->notifyProgress($assignment, $progress, $status);
        } catch (\Exception $e) {
            // non-blocking
        }

        return $assignment;
    }

    public function removeAssignments(array $ids, $userId)
    {
        return TaskAssignment::whereIn('id', $ids)->where('user_id', $userId)->delete();
    }

    public function deleteTask($taskId)
    {
        TaskAssignment::where('task_id', $taskId)->delete();
        TaskCc::where('task_id', $taskId)->delete();
        TaskReminder::where('task_id', $taskId)->delete();
        TaskAttachment::where('task_id', $taskId)->delete();

        return Task::where('id', $taskId)->delete();
    }

    public function priorityColor($priority)
    {
        switch ($priority) {
            case 'Emergency':
            case 'Critical':
                return '#ef4444';
            case 'High':
                return '#f97316';
            case 'Medium':
                return '#eab308';
            default:
                return '#22c55e';
        }
    }

    public function isOverdue($assignment)
    {
        $task = $assignment->task;
        if (! $task || ! $task->deadline) {
            return false;
        }
        if ($assignment->status === 'Completed' || $assignment->status === 'Declined') {
            return false;
        }

        $end = $task->deadline->copy();
        if ($task->deadline_time) {
            try {
                $end = Carbon::parse($task->deadline->format('Y-m-d') . ' ' . $task->deadline_time);
            } catch (\Exception $e) {
                $end = $task->deadline->endOfDay();
            }
        } else {
            $end = $task->deadline->endOfDay();
        }

        return $end->isPast();
    }
}
