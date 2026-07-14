<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventWorkerPayment extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'event_id', 'assignment_id', 'worker_profile_id', 'reference_no',
        'amount', 'payment_method', 'mobile_money_number', 'status',
        'paid_at', 'receipt_path', 'notes', 'created_by', 'approved_by',
    ];

    protected $dates = ['paid_at'];

    protected $casts = ['amount' => 'integer'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (! $m->id) {
                $m->id = (string) Str::uuid();
            }
        });
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function assignment()
    {
        return $this->belongsTo(EventAssignment::class, 'assignment_id');
    }

    public function workerProfile()
    {
        return $this->belongsTo(EventWorkerProfile::class, 'worker_profile_id');
    }

    public function statusLabel()
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PAID => 'Paid',
            self::STATUS_CANCELLED => 'Cancelled',
        ][$this->status] ?? $this->status;
    }
}
