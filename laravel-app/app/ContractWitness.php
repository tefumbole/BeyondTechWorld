<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContractWitness extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'contract_id', 'for_party', 'person_type', 'person_id', 'identity_snapshot_json', 'signatory_id',
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

    public function setIdentitySnapshotJsonAttribute($value)
    {
        $this->attributes['identity_snapshot_json'] = is_string($value) ? $value : json_encode($value ?: []);
    }
}
