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
use Illuminate\Support\Facades\Schema;

/**
 * Sends WhatsApp notifications for task assignment, CC, accept, decline, progress, complete, reminders.
 */
class TaskNotificationService extends Controller
{
    /**
     * @return string sent|skip|retry
     */
    protected function sendPhone($phone, $message)
    {
        if (empty(trim((string) $phone))) {
            return 'skip';
        }
        try {
            $this->sendWhatsAppToPhone($phone, $message);

            return 'sent';
        } catch (\Exception $e) {
            $err = $e->getMessage();
            Log::warning('Task WhatsApp failed: ' . $err);
            if (preg_match('/rate|protection|429|timeout|temporar|try again/i', $err)) {
                return 'retry';
            }

            return 'skip';
        }
    }

    protected function phoneForUser(BeyondUser $user)
    {
        return $this->resolveBeyondUserPhone($user);
    }

    public function notifyAssignment(TaskAssignment $assignment)
    {
        $assignment->load(['task']);
        $task = $assignment->task;
        $user = BeyondUser::find($assignment->user_id);
        if (! $task || ! $user) {
            return 'skip';
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

        return $this->sendPhone($this->phoneForUser($user), $message);
    }

    /**
     * Prefer BeyondUser.phone; fall back to profile / customer directory phone.
     */
    protected function resolveBeyondUserPhone(BeyondUser $user)
    {
        $phone = trim((string) ($user->phone ?? ''));
        if ($phone !== '') {
            return $phone;
        }
        try {
            $profile = \App\BeyondProfile::find($user->id);
            if ($profile && trim((string) $profile->phone) !== '') {
                $phone = trim((string) $profile->phone);
                $user->phone = $phone;
                $user->save();

                return $phone;
            }
        } catch (\Throwable $e) {
        }
        try {
            if (! empty($user->email)) {
                $customer = \App\Customer::where('is_active', 1)
                    ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
                    ->whereNotNull('phone_number')
                    ->orderByDesc('id')
                    ->first();
                if ($customer && trim((string) $customer->phone_number) !== '') {
                    $phone = trim((string) $customer->phone_number);
                    $user->phone = $phone;
                    $user->save();

                    return $phone;
                }
            }
        } catch (\Throwable $e) {
        }

        return $phone;
    }

    public function notifyCcRecipient(Task $task, TaskCc $cc)
    {
        $task->loadMissing('assignments');
        $assigneeNames = BeyondUser::whereIn('id', $task->assignments->pluck('user_id'))
            ->pluck('name')->filter()->implode(', ') ?: 'the assignee(s)';

        $user = BeyondUser::find($cc->user_id);
        if (! $user) {
            return 'skip';
        }
        $phone = $this->phoneForUser($user);
        if ($phone === '') {
            return 'skip';
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

        return $this->sendPhone($phone, $msg);
    }

    public function notifyCcOnAssignment(Task $task)
    {
        $task->load(['assignments', 'ccRecipients']);
        $sent = 0;
        foreach ($task->ccRecipients as $cc) {
            if ($this->notifyCcRecipient($task, $cc) === 'sent') {
                $sent++;
            }
        }

        return $sent;
    }

    public function notifyAccepted(TaskAssignment $assignment)
    {
        $this->notifyStatusChange($assignment, 'accepted');
    }

    public function notifyDeclined(TaskAssignment $assignment)
    {
        $this->notifyStatusChange($assignment, 'declined');
    }

    protected function notifyStatusChange(TaskAssignment $assignment, $action)
    {
        $assignment->load('task');
        $task = $assignment->task;
        $assignee = BeyondUser::find($assignment->user_id);
        if (! $task || ! $assignee) {
            return;
        }

        $assigneeName = $assignee->name ?: 'Assignee';
        $accepted = $action === 'accepted';
        $adminTitle = $accepted ? '📊 *TASK ACCEPTED*' : '❌ *TASK DECLINED*';
        $adminLine = $accepted
            ? "*{$assigneeName}* has accepted the task:"
            : "*{$assigneeName}* has declined the task:";
        $ccTitle = $accepted ? '📊 *TASK CC — ACCEPTED*' : '❌ *TASK CC — DECLINED*';
        $ccLine = $accepted
            ? "*{$assigneeName}* has accepted the task you are CC'd on:"
            : "*{$assigneeName}* has declined the task you are CC'd on:";

        $admin = $task->created_by_admin_id ? User::find($task->created_by_admin_id) : null;
        if ($admin && ! empty($admin->phone)) {
            $this->sendPhone($admin->phone, "{$adminTitle}\n━━━━━━━━━━━━━━━\n\n{$adminLine}\n\n▪️ *Task:* {$task->title}\n\n_Beyond Enterprise_");
        }

        foreach (TaskCc::where('task_id', $task->id)->get() as $cc) {
            $user = BeyondUser::find($cc->user_id);
            if (! $user) {
                continue;
            }
            $phone = $this->phoneForUser($user);
            if ($phone === '') {
                continue;
            }
            $this->sendPhone(
                $phone,
                "{$ccTitle}\n━━━━━━━━━━━━━━━\n\nHello *" . ($user->name ?: 'CC') . "*,\n\n{$ccLine}\n\n▪️ *Task:* {$task->title}\n\n_Beyond Enterprise_"
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
                if (! $user) {
                    continue;
                }
                $phone = $this->phoneForUser($user);
                if ($phone === '') {
                    continue;
                }
                $this->sendPhone(
                    $phone,
                    "✅ *TASK CC — COMPLETED*\n━━━━━━━━━━━━━━━\n\nHello *" . ($user->name ?: 'CC') . "*,\n\n*{$assigneeName}* completed the task you are CC'd on:\n\n▪️ *Task:* {$task->title}\n\n_Beyond Enterprise_"
                );
            }

            return;
        }

        foreach (TaskCc::where('task_id', $task->id)->get() as $cc) {
            $user = BeyondUser::find($cc->user_id);
            if (! $user) {
                continue;
            }
            $phone = $this->phoneForUser($user);
            if ($phone === '') {
                continue;
            }
            $this->sendPhone(
                $phone,
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
            if (! $user) {
                continue;
            }
            $phone = $this->phoneForUser($user);
            if ($phone === '') {
                continue;
            }
            $deadline = $task->deadline
                ? $task->deadline->format('d M Y') . ($task->deadline_time ? ' ' . substr((string) $task->deadline_time, 0, 5) : '')
                : '—';
            $desc = TaskPersonalization::personalize($task->description ?: '', TaskPersonalization::userVars($user));
            $descBlock = trim($desc) !== '' ? "\n{$desc}\n" : '';
            $this->sendPhone(
                $phone,
                "⏰ *TASK REMINDER*\n━━━━━━━━━━━━━━━\n\nHello *" . ($user->name ?: 'Team Member') . "*,\n\nReminder for your task:\n\n▪️ *Task:* {$task->title}\n▪️ *Deadline:* {$deadline}\n{$descBlock}\n👉 Update progress:\n" . url('/user/tasks') . "\n\n_Beyond Enterprise_"
            );
        }
    }

    /**
     * Send outstanding assignment + CC WhatsApps for one task, respecting a send budget
     * so Wasender rate limits do not drop the rest of the list.
     *
     * @return int number of API send attempts that succeeded
     */
    public function dispatchPending(Task $task, $budget = 8)
    {
        $task->load(['assignments', 'ccRecipients']);
        $sent = 0;
        $track = $this->tracksWhatsappSent();
        $paused = false;

        foreach ($task->assignments as $assignment) {
            if ($sent >= $budget) {
                break;
            }
            if ($track && $assignment->whatsapp_sent) {
                continue;
            }
            $outcome = $this->notifyAssignment($assignment);
            if ($outcome === 'retry') {
                $paused = true;
                break;
            }
            if ($track) {
                $assignment->whatsapp_sent = true;
                $assignment->save();
            }
            if ($outcome === 'sent') {
                $sent++;
            }
        }

        if (! $paused && $sent < $budget) {
            foreach ($task->ccRecipients as $cc) {
                if ($sent >= $budget) {
                    break;
                }
                if ($track && $cc->whatsapp_sent) {
                    continue;
                }
                $outcome = $this->notifyCcRecipient($task, $cc);
                if ($outcome === 'retry') {
                    break;
                }
                if ($track) {
                    $cc->whatsapp_sent = true;
                    $cc->save();
                }
                if ($outcome === 'sent') {
                    $sent++;
                }
            }
        }

        if ($track) {
            $task->unsetRelation('assignments');
            $task->unsetRelation('ccRecipients');
            $task->load(['assignments', 'ccRecipients']);
            $pendingAssignees = $task->assignments->filter(function ($a) {
                return empty($a->whatsapp_sent);
            })->count();
            $pendingCc = $task->ccRecipients->filter(function ($c) {
                return empty($c->whatsapp_sent);
            })->count();
            if ($pendingAssignees === 0 && $pendingCc === 0) {
                $task->notifications_sent = true;
                $task->is_scheduled = false;
                $task->save();
            }
        } else {
            $task->notifications_sent = true;
            $task->is_scheduled = false;
            $task->save();
        }

        return $sent;
    }

    public function dispatchTaskNotifications(Task $task)
    {
        return $this->dispatchPending($task, 500);
    }

    protected function tracksWhatsappSent()
    {
        return Schema::hasColumn('task_assignments', 'whatsapp_sent')
            && Schema::hasColumn('task_cc', 'whatsapp_sent');
    }
}
