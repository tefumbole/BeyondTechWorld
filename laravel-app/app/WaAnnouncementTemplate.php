<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WaAnnouncementTemplate extends Model
{
    protected $table = 'wa_announcement_templates';
    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo(WaAnnouncementCategory::class, 'category_id');
    }
}
