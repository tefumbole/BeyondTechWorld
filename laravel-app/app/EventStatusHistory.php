<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EventStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_id', 'previous_status', 'new_status', 'changed_by', 'note', 'changed_at',
    ];

    protected $dates = ['changed_at'];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
