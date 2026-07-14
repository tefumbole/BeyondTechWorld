<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventPublication extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'event_id', 'publish_on_website', 'public_title', 'public_summary', 'public_description',
        'public_flyer_path', 'public_venue', 'public_location',
        'public_contact_name', 'public_contact_phone', 'public_contact_email',
        'registration_url', 'ticket_url', 'external_url',
        'visibility_at', 'unpublish_at', 'is_featured', 'display_order',
        'show_event_time', 'show_setup_info', 'show_countdown',
        'countdown_target_type', 'countdown_custom_at', 'countdown_visible_from',
        'countdown_completion_message', 'hide_countdown_after_completion',
        'public_status_override', 'public_announcement', 'publication_status',
    ];

    protected $dates = [
        'visibility_at', 'unpublish_at', 'countdown_custom_at', 'countdown_visible_from',
    ];

    protected $casts = [
        'publish_on_website' => 'boolean',
        'is_featured' => 'boolean',
        'show_event_time' => 'boolean',
        'show_setup_info' => 'boolean',
        'show_countdown' => 'boolean',
        'hide_countdown_after_completion' => 'boolean',
    ];

    const PUBLICATION_STATUSES = [
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'published' => 'Published',
        'unpublished' => 'Unpublished',
        'archived' => 'Archived',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function (EventPublication $pub) {
            if (! $pub->id) {
                $pub->id = (string) Str::uuid();
            }
        });
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function publicFlyerUrl()
    {
        if (! $this->public_flyer_path) {
            return null;
        }
        if (preg_match('#^(https?:)?//#', $this->public_flyer_path) || strpos($this->public_flyer_path, '/') === 0) {
            return $this->public_flyer_path;
        }

        return url('public/' . ltrim($this->public_flyer_path, '/'));
    }
}
