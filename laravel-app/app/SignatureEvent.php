<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SignatureEvent extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'contract_id', 'revision_id', 'signatory_id', 'event_type', 'event_at',
        'actor_type', 'actor_id', 'ip_address', 'user_agent', 'metadata_json',
    ];

    protected $dates = ['event_at'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (! $m->id) {
                $m->id = (string) Str::uuid();
            }
            if (! $m->event_at) {
                $m->event_at = now();
            }
        });
    }
}
