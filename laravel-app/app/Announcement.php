<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $table = 'announcements';
    protected $guarded = [];

    public function createdBy() {
        return $this->belongsTo('App\User', 'created_by', 'id');
    }

    public function attachmentLib() {
        return $this->hasMany('App\AnnouncementAttachment', 'announcement_id', 'id');
    }
}
