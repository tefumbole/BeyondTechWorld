<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContractReminder extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'contract_id', 'reminder_time', 'label', 'message', 'is_sent', 'sent_at', 'created_by',
    ];

    protected $dates = ['reminder_time', 'sent_at'];

    protected $casts = ['is_sent' => 'boolean'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (! $m->id) {
                $m->id = (string) Str::uuid();
            }
        });
    }

    public function contract()
    {
        return $this->belongsTo(BtwContract::class, 'contract_id');
    }
}
