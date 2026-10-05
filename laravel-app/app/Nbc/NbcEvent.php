<?php

namespace App\Nbc;

use Illuminate\Database\Eloquent\Model;

class NbcEvent extends Model
{
    protected $table = 'nbc_events';

    protected $fillable = [
        'title', 'kind', 'starts_at', 'ends_at', 'place', 'body', 'sort', 'published', 'author_id',
    ];

    protected $dates = ['starts_at', 'ends_at'];
}
