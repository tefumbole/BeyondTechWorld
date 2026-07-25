<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContractDocument extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'contract_id', 'revision_id', 'kind', 'file_path', 'checksum', 'immutable', 'generated_at', 'render_engine',
    ];

    protected $dates = ['generated_at'];

    protected $casts = ['immutable' => 'boolean'];

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
