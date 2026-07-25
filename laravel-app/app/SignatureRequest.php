<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SignatureRequest extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'signatory_id', 'token_hash', 'channel', 'sent_at', 'expires_at', 'attempts', 'revoked_at', 'opened_at',
    ];

    protected $dates = ['sent_at', 'expires_at', 'revoked_at', 'opened_at'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (! $m->id) {
                $m->id = (string) Str::uuid();
            }
        });
    }

    public function signatory()
    {
        return $this->belongsTo(ContractSignatory::class, 'signatory_id');
    }

    public function isActive()
    {
        return empty($this->revoked_at)
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
