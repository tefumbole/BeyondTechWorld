<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskReminder extends Model
{
    protected $table = 'task_reminders';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'task_id', 'reminder_time', 'is_sent'];

    protected $dates = ['reminder_time'];

    protected $casts = [
        'is_sent' => 'boolean',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'id');
    }
}
