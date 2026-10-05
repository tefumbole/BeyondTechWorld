<?php

namespace App\Nbc;

use Illuminate\Database\Eloquent\Model;

class NbcAnnouncement extends Model
{
    protected $table = 'nbc_announcements';

    protected $fillable = ['title', 'body', 'published_at', 'author_id'];

    protected $dates = ['published_at'];
}
