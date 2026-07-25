<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContractLink extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    const ALLOWED_TYPES = ['event', 'quotation', 'booking', 'sale', 'project', 'property', 'invoice', 'shareholder'];

    protected $fillable = [
        'id', 'contract_id', 'link_type', 'link_id', 'relationship', 'is_primary',
    ];

    protected $casts = ['is_primary' => 'boolean'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (! $m->id) {
                $m->id = (string) Str::uuid();
            }
        });
    }
}
