<?php

namespace App\Appointment;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    const CONFIRMED = 'CONFIRMED';
    const CANCELLED = 'CANCELLED';
    const COMPLETED = 'COMPLETED';

    protected $guarded = [];

    protected $dates = ['starts_at', 'ends_at'];

    public function activities()
    {
        return $this->hasMany(AppointmentActivity::class, 'appointment_id');
    }
}
