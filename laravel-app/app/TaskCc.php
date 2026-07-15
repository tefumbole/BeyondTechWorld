<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskCc extends Model
{
    protected $table = 'task_cc';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'task_id', 'user_id'];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(BeyondUser::class, 'user_id', 'id');
    }
}
