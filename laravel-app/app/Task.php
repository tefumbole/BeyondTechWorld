<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $table = 'tasks';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'title', 'description', 'priority', 'color', 'start_date', 'start_time',
        'deadline', 'deadline_time', 'status', 'created_by', 'created_by_admin_id',
        'category_id', 'notification_template', 'is_scheduled', 'scheduled_for',
        'schedules_json', 'notifications_sent',
    ];

    protected $dates = ['start_date', 'deadline', 'scheduled_for'];

    protected $casts = [
        'is_scheduled' => 'boolean',
        'notifications_sent' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(TaskCategory::class, 'category_id', 'id');
    }

    public function assignments()
    {
        return $this->hasMany(TaskAssignment::class, 'task_id', 'id');
    }

    public function ccRecipients()
    {
        return $this->hasMany(TaskCc::class, 'task_id', 'id');
    }

    public function reminders()
    {
        return $this->hasMany(TaskReminder::class, 'task_id', 'id');
    }

    public function attachments()
    {
        return $this->hasMany(TaskAttachment::class, 'task_id', 'id');
    }

    public function adminCreator()
    {
        return $this->belongsTo(User::class, 'created_by_admin_id', 'id');
    }
}
