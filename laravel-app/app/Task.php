<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $table = 'tasks';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'title', 'description', 'priority', 'start_date', 'deadline',
        'deadline_time', 'status', 'created_by', 'category_id', 'notification_template',
    ];

    protected $dates = ['start_date', 'deadline'];

    public function category()
    {
        return $this->belongsTo(TaskCategory::class, 'category_id', 'id');
    }

    public function assignments()
    {
        return $this->hasMany(TaskAssignment::class, 'task_id', 'id');
    }
}
