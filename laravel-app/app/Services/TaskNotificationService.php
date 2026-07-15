<?php

namespace App\Services;

use App\BeyondUser;
use App\Http\Controllers\Controller;
use App\Task;
use App\TaskAssignment;
use App\TaskCc;
use App\User;
use App\Support\TaskPersonalization;
use Illuminate\Support\Facades\Log;

/**
 * Sends WhatsApp notifications for task assignment, CC, accept, progress, complete, reminders.
 */
class TaskNotificationService extends Controller
{
    protected function sendPhone($phone, $message)
    {
        if (empty(trim((string) $phone))) {
            return false;
        }
        try {
            $this->sendWhatsAppToPhone($phone, $message);

            return true;
        } catch (\Exception $e) {
            Log::warning('Task WhatsApp failed: ' . $e->getMessage());

            return false;
        }
    }

    public function notifyAssignment(TaskAssignment $assignment)
    {
        $assignment->load(['task']);
        $task = $assignment->task;
        $user = BeyondUser::find($assignment->user_id);
        if (! $task || ! $user) {
            return false;
        }

        $link = url('/task-invite/' . $assignment->invite_token);
        $userVars = TaskPersonalization::userVars($user);
        $description = TaskPersonalization::personalize($task->description ?: '', $userVars);
        $template = $task->notification_template ?: TaskPersonalization::defaultAssignmentTemplate();
        $vars = array_merge($userVars, TaskPersonalization::taskVars($task, $link), [
            'description' => $description,
            'task_message' => $description,
        ]);
        $message = TaskPersonalization::personalize($template, $vars);

        return $this->sendPhone($user->phone, $message);
    }

    public function notifyCcOnAssignment(Task $task)
    {
        $task->load(['assignments', 'ccRecipients']);
        $assigneeNames = BeyondUser::whereIn('id', $task->assignments->pluck('user_id'))
            ->pluck('name')->filter()->implode(', ') ?: 'the assignee(s)';

        $sent = 0;
        foreach ($task->ccRecipients as $cc) {
            $user = BeyondUser::find($cc->user_id);
            if (! $user || empty($user->phone)) {
                continue;
            }
            $start = $task->start_date
                ? $task->start_date->format('d M Y') . ($task->start_time ? ' ' . substr((string) $task->start_time, 0, 5) : '')
                : '—';
            $deadline = $task->deadline
                ? $task->deadline->format('d M Y') . ($task->deadline_time ? ' ' . substr((string) $task->deadline_time, 0, 5) : '')
                : '—';
            $desc = TaskPersonalization::personalize($task->description ?: '', TaskPersonalization::userVars($user));
            $msg = "📋 *TASK CC NOTIFICATION*\n━━━━━━━━━━━━━━━\n\n";
            $msg .= "Hello *" . ($user->name ?: 'Team Member') . "*,\n\n";
            $msg .= "You have been CC'd on a task assigned to *{$assigneeNames}*:\n\n";
            $msg .= "▪️ *Task:* {$task->title}\n";
            $msg .= "▪️ *Priority:* " . ($task->priority ?: 'Medium') . "\n";
            $msg .= "▪️ *Start:* {$start}\n";
            $msg .= "▪️ *Deadline:* {$deadline}\n";
            if (trim($desc) !== '') {
                $msg .= "\n{$desc}\n";
            }
            $msg .= "\nYou will receive progress updates on this task.\n\n";
            $msg .= "👉 View tasks:\n" . url('/user/tasks') . "\n\n_Beyond Enterprise_";

            if ($this->sendPhone($user->phone, $msg)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function notifyAccepted(TaskAssignment $assignment)
    {
        $assignment->load('task');
        $task = $assignment->task;
        $assignee = BeyondUser::find($assignment->user_id);
        if (! $task || ! $assignee) {
            return;
        }

        $assigneeName = $assignee->name ?: 'Assignee';

        // Admin / creator
        $admin = $task->created_by_admin_id ? User::find($task->created_by_admin_id) : null;
        if ($admin && ! empty($admin->phone)) {
            $this->sendPhone($admin->phone, "📊 *TASK ACCEPTED*\n━━━━━━━━━━━━━━━\n\n*{$assigneeName}* has accepted the task:\n\n▪️ *Task:* {$task->title}\n\n_Beyond Enterprise_");
        }

        // CC recipients
        foreach (TaskCc::where('task_id', $task->id)->get() as $cc) {
            $user = BeyondUser::find($cc->user_id);
            if (! $user || empty($user->phone)) {
                continue;
            }
            $this->sendPhone(
                $user->phone,
                "📊 *TASK CC — ACCEPTED*\n━━━━━━━━━━━━━━━\n\nHello *" . ($user->name ?: 'CC') . "*,\n\n*{$assigneeName}* has accepted the task you are CC'd on:\n\n▪️ *Task:* {$task->title}\n\n_Beyond Enterprise_"
            );
        }
    }

    public function notifyProgress(TaskAssignment $assignment, $progress, $status, $comment = null)
    {
        $assignment->load('task');
        $task = $assignment->task;
        $assignee = BeyondUser::find($assignment->user_id);
        if (! $task || ! $assignee) {
            return;
        }
        $assigneeName = $assignee->name ?: 'Assignee';
        $commentBlock = $comment ? "\n▪️ *Note:* {$comment}" : '';

        if ($status === 'Completed') {
            $admin = $task->created_by_admin_id ? User::find($task->created_by_admin_id) : null;
            if ($admin && ! empty($admin->phone)) {
                $this->sendPhone($admin->phone, "✅ *TASK COMPLETED*\n━━━━━━━━━━━━━━━\n\n*{$assigneeName}* completed:\n\n▪️ *Task:* {$task->title}\n\n_Beyond Enterprise_");
            }
            foreach (TaskCc::where('task_id', $task->id)->get() as $cc) {
                $user = BeyondUser::find($cc->user_id);
                if (! $user || empty($user->phone)) {
                    continue;
                }
                $this->sendPhone(
                    $user->phone,
                    "✅ *TASK CC — COMPLETED*\n━━━━━━━━━━━━━━━\n\nHello *" . ($user->name ?: 'CC') . "*,\n\n*{$assigneeName}* completed the task you are CC'd on:\n\n▪️ *Task:* {$task->title}\n\n_Beyond Enterprise_"
                );
            }

            return;
        }

        foreach (TaskCc::where('task_id', $task->id)->get() as $cc) {
            $user = BeyondUser::find($cc->user_id);
            if (! $user || empty($user->phone)) {
                continue;
            }
            $this->sendPhone(
                $user->phone,
                "📋 *TASK CC — PROGRESS UPDATE*\n━━━━━━━━━━━━━━━\n\nHello *" . ($user->name ?: 'CC') . "*,\n\nYou are CC on a task assigned to *{$assigneeName}*:\n\n▪️ *Task:* {$task->title}\n▪️ *Realization:* {$progress}%\n▪️ *Status:* {$status}{$commentBlock}\n\n_Beyond Enterprise_"
            );
        }
    }

    public function notifyReminder(Task $task)
    {
        $task->load('assignments');
        foreach ($task->assignments as $assignment) {
            if (in_array($assignment->status, ['Completed', 'Declined'], true)) {
                continue;
            }
            $user = BeyondUser::find($assignment->user_id);
            if (! $user || empty($user->phone)) {
                continue;
            }
            $deadline = $task->deadline
                ? $task->deadline->format('d M Y') . ($task->deadline_time ? ' ' . substr((string) $task->deadline_time, 0, 5) : '')
                : '—';
            $this->sendPhone(
                $user->phone,
                "⏰ *TASK REMINDER*\n━━━━━━━━━━━━━━━\n\nHello *" . ($user->name ?: 'Team Member') . "*,\n\nReminder for your task:\n\n▪️ *Task:* {$task->title}\n▪️ *Deadline:* {$deadline}\n\n👉 Update progress:\n" . url('/user/tasks') . "\n\n_Beyond Enterprise_"
            );
        }
    }

    public function dispatchTaskNotifications(Task $task)
    {
        $task->load(['assignments', 'ccRecipients']);
        foreach ($task->assignments as $assignment) {
            $this->notifyAssignment($assignment);
        }
        $this->notifyCcOnAssignment($task);
        $task->notifications_sent = true;
        $task->save();
    }
}
