<?php

namespace App\Nbc;

class NbcAnnouncement extends NbcModel
{
    protected $table = 'nbc_announcements';

    protected $fillable = ['title', 'body', 'published_at', 'author_id'];

    protected $dates = ['published_at'];
}
