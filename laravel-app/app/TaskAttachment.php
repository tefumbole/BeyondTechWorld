<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskAttachment extends Model
{
    protected $table = 'task_attachments';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'task_id', 'update_id', 'file_name', 'file_url', 'attachment_type'];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id', 'id');
    }
}
