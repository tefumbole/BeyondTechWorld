<?php

namespace App\Nbc;

use Illuminate\Database\Eloquent\Model;

class NbcAttendance extends Model
{
    protected $table = 'nbc_attendance';

    protected $fillable = ['member_id', 'event_id', 'clock_in', 'clock_out'];

    protected $dates = ['clock_in', 'clock_out'];

    public function member()
    {
        return $this->belongsTo(NbcMember::class, 'member_id');
    }

    public function event()
    {
        return $this->belongsTo(NbcEvent::class, 'event_id');
    }
}
