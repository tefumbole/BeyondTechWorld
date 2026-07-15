<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WaAnnouncementCategory extends Model
{
    protected $table = 'wa_announcement_categories';
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
