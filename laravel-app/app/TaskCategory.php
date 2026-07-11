<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskCategory extends Model
{
    protected $table = 'task_categories';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'name', 'color', 'description'];
}
