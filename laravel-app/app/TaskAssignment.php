<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskAssignment extends Model
{
    protected $table = 'task_assignments';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'task_id', 'user_id', 'status', 'progress', 'acceptance_signature',
        'signature_at', 'accepted_at', 'declined_at', 'completed_at', 'last_update_at',
        'invite_token',
    ];

    protected $dates = ['signature_at', 'accepted_at', 'declined_at', 'completed_at', 'last_update_at'];

    protected $casts = [
        'progress' => 'integer',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'id');
    }
}
