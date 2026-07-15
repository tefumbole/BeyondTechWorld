<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WaAnnouncement extends Model
{
    protected $table = 'wa_announcements';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected $casts = [
        'is_scheduled' => 'boolean',
        'send_whatsapp' => 'boolean',
        'scheduled_for' => 'datetime',
        'sent_count' => 'integer',
        'cc_sent_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function category()
    {
        return $this->belongsTo(WaAnnouncementCategory::class, 'category_id');
    }

    public function reminders()
    {
        return $this->hasMany(WaAnnouncementReminder::class, 'announcement_id');
    }

    public function recipients()
    {
        $json = $this->recipients_json;
        if (! $json) {
            return [];
        }
        $data = is_array($json) ? $json : json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    public function ccRecipients()
    {
        $json = $this->cc_json;
        if (! $json) {
            return [];
        }
        $data = is_array($json) ? $json : json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    public function schedules()
    {
        $json = $this->schedules_json;
        if (! $json) {
            return [];
        }
        $data = is_array($json) ? $json : json_decode($json, true);

        return is_array($data) ? $data : [];
    }
}
