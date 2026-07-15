<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskMessageTemplate extends Model
{
    protected $table = 'task_message_templates';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'name', 'subject', 'body'];
}
