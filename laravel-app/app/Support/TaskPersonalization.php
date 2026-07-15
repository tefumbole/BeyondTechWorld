<?php

namespace App\Support;

class TaskPersonalization
{
    public static function defaultAssignmentTemplate()
    {
        return "📋 *NEW TASK ASSIGNMENT*\n━━━━━━━━━━━━━━━\n\nHello *{Name}*,\n\nYou have been assigned a new task:\n\n▪️ *Task:* {subject}\n▪️ *Priority:* {priority}\n▪️ *Start:* {start_date}\n▪️ *Deadline:* {deadline}\n\n{description}\n\n👉 Open this link to *Accept* or *Reject* your task:\n{login_link}\n\n_Beyond Enterprise_";
    }

    public static function personalize($template, array $vars)
    {
        if ($template === null || $template === '') {
            return '';
        }
        $result = $template;
        foreach ($vars as $key => $value) {
            $result = preg_replace('/\{' . preg_quote($key, '/') . '\}/i', (string) ($value ?? ''), $result);
        }

        return $result;
    }

    public static function userVars($user)
    {
        return [
            'Name' => $user->name ?? '',
            'name' => $user->name ?? '',
            'Phone' => $user->phone ?? '',
            'Phone Number' => $user->phone ?? '',
            'phone' => $user->phone ?? '',
            'Email' => $user->email ?? '',
            'email' => $user->email ?? '',
            'Address' => $user->address ?? '',
            'address' => $user->address ?? '',
        ];
    }

    public static function taskVars($task, $loginLink = '')
    {
        $start = $task->start_date
            ? $task->start_date->format('d M Y') . ($task->start_time ? ' ' . substr((string) $task->start_time, 0, 5) : '')
            : '—';
        $deadline = $task->deadline
            ? $task->deadline->format('d M Y') . ($task->deadline_time ? ' ' . substr((string) $task->deadline_time, 0, 5) : '')
            : '—';

        return [
            'subject' => $task->title,
            'task_title' => $task->title,
            'priority' => $task->priority,
            'start_date' => $start,
            'deadline' => $deadline,
            'login_link' => $loginLink,
        ];
    }
}
