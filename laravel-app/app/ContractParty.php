<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContractParty extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'contract_id', 'side', 'subject_type', 'subject_id', 'role_label',
        'identity_snapshot_json', 'representative_snapshot_json',
    ];

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

    public function snapshot()
    {
        $raw = $this->attributes['identity_snapshot_json'] ?? null;

        return $raw ? json_decode($raw, true) : [];
    }

    public function setIdentitySnapshotJsonAttribute($value)
    {
        $this->attributes['identity_snapshot_json'] = is_string($value) ? $value : json_encode($value ?: []);
    }

    public function displayName()
    {
        $s = $this->snapshot();

        return $s['name'] ?? $s['display_name'] ?? '—';
    }
}
