<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContractSignatory extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'contract_id', 'revision_id', 'role', 'party_id', 'person_type', 'person_id',
        'email', 'phone', 'display_name', 'stage', 'required', 'status', 'signed_at',
        'signature_image', 'typed_name', 'declined_reason', 'ip_address', 'user_agent',
    ];

    protected $dates = ['signed_at'];

    protected $casts = ['required' => 'boolean'];

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

    public function requests()
    {
        return $this->hasMany(SignatureRequest::class, 'signatory_id');
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isExternal()
    {
        return in_array($this->role, ['party_b', 'witness_a', 'witness_b', 'party_a'], true)
            && $this->role !== 'admin';
    }
}
