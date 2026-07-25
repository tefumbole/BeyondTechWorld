<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContractValue extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'contract_id', 'revision_id', 'placeholder_key', 'value_json',
        'source_type', 'source_id', 'manually_overridden',
    ];

    protected $casts = ['manually_overridden' => 'boolean'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (! $m->id) {
                $m->id = (string) Str::uuid();
            }
        });
    }

    public function getValue()
    {
        $raw = $this->attributes['value_json'] ?? null;
        if ($raw === null) {
            return null;
        }
        $decoded = json_decode($raw, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $raw;
    }

    public function setValueJsonAttribute($value)
    {
        $this->attributes['value_json'] = is_string($value) ? $value : json_encode($value);
    }
}
