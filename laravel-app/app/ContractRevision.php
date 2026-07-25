<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContractRevision extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'contract_id', 'revision_no', 'content_html', 'content_json', 'resolved_data_json',
        'checksum', 'state', 'created_by', 'frozen_at',
    ];

    protected $dates = ['frozen_at'];

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

    public function getResolvedDataJsonAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    public function setResolvedDataJsonAttribute($value)
    {
        $this->attributes['resolved_data_json'] = is_string($value) ? $value : json_encode($value ?: []);
    }

    public function isFrozen()
    {
        return $this->state === 'frozen';
    }
}
