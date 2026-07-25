<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContractAuditLog extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id', 'contract_id', 'actor_type', 'actor_id', 'action', 'before_json', 'after_json', 'ip_address', 'created_at',
    ];

    protected $dates = ['created_at'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (! $m->id) {
                $m->id = (string) Str::uuid();
            }
            if (! $m->created_at) {
                $m->created_at = now();
            }
        });
    }
}
