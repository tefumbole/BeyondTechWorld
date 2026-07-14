<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventContract extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    const STATUS_DRAFT = 'draft';
    const STATUS_SENT = 'sent';
    const STATUS_WORKER_SIGNED = 'worker_signed';
    const STATUS_APPROVED = 'approved';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'event_id', 'assignment_id', 'template_id', 'reference_no', 'title',
        'rendered_body', 'status', 'signature_token',
        'worker_signed_at', 'worker_signature',
        'admin_signed_at', 'admin_signed_by', 'admin_signature',
        'signed_pdf_path', 'sent_at', 'approved_at', 'created_by',
    ];

    protected $dates = ['worker_signed_at', 'admin_signed_at', 'sent_at', 'approved_at'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (! $m->id) {
                $m->id = (string) Str::uuid();
            }
            if (! $m->signature_token) {
                $m->signature_token = Str::random(48);
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

    public function template()
    {
        return $this->belongsTo(EventContractTemplate::class, 'template_id');
    }

    public function adminSigner()
    {
        return $this->belongsTo(User::class, 'admin_signed_by');
    }

    public function statusLabel()
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SENT => 'Sent for signing',
            self::STATUS_WORKER_SIGNED => 'Worker signed',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_CANCELLED => 'Cancelled',
        ][$this->status] ?? $this->status;
    }
}
