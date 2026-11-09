<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EventAutoPayment extends Model
{
    protected $table = 'event_auto_payments';

    protected $fillable = [
        'event_id', 'assignment_id', 'amount', 'pay_at', 'status', 'attempt',
        'requested_by', 'approved_by', 'approved_at', 'paid_at', 'result',
    ];

    protected $dates = ['pay_at', 'approved_at', 'paid_at'];

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function assignment()
    {
        return $this->belongsTo(EventAssignment::class, 'assignment_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
