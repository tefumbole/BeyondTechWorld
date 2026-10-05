<?php

namespace App\Nbc;

use Illuminate\Database\Eloquent\Model;

class NbcTask extends Model
{
    protected $table = 'nbc_tasks';

    protected $fillable = ['title', 'body', 'assignee_id', 'due_on', 'status', 'author_id'];

    protected $dates = ['due_on'];

    public function assignee()
    {
        return $this->belongsTo(NbcMember::class, 'assignee_id');
    }
}
