<?php

namespace App\Nbc;

class NbcEvent extends NbcModel
{
    protected $table = 'nbc_events';

    protected $fillable = [
        'title', 'kind', 'starts_at', 'ends_at', 'place', 'body', 'sort', 'published', 'author_id',
    ];

    protected $dates = ['starts_at', 'ends_at'];
}
