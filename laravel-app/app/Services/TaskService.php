<?php

namespace App\Services;

use App\Task;
use App\TaskAssignment;
use App\TaskCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaskService
{
    public function categories()
    {
        return TaskCategory::orderBy('name')->get();
    }

    public function myTasks($userId, $statusFilter = 'All', $categoryFilter = 'All')
    {
        $query = TaskAssignment::with(['task.category'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at');

        if ($statusFilter && $statusFilter !== 'All') {
            $query->where('status', $statusFilter);
        }

        $assignments = $query->get();

        return $assignments->filter(function ($a) use ($categoryFilter) {
            if (! $a->task) {
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

        return $assignment;
    }

    public function removeAssignments(array $ids, $userId)
    {
        return TaskAssignment::whereIn('id', $ids)->where('user_id', $userId)->delete();
    }

    public function priorityColor($priority)
    {
        switch ($priority) {
            case 'Critical': return '#ef4444';
            case 'High': return '#f97316';
            case 'Medium': return '#eab308';
            default: return '#22c55e';
        }
    }

    public function isOverdue($assignment)
    {
        $task = $assignment->task;
        if (! $task || ! $task->deadline) {
            return false;
        }
        if ($assignment->status === 'Completed') {
            return false;
        }

        return $task->deadline->endOfDay()->isPast();
    }
}
