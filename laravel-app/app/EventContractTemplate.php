<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventContractTemplate extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['name', 'contract_type', 'header', 'body', 'footer', 'is_active', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (! $m->id) {
                $m->id = (string) Str::uuid();
            }
        });
    }

    public function contracts()
    {
        return $this->hasMany(EventContract::class, 'template_id');
    }
}
