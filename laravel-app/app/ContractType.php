<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContractType extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'code', 'name', 'category', 'default_party_a_label', 'default_party_b_label', 'active',
    ];

    protected $casts = ['active' => 'boolean'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($m) {
            if (! $m->id) {
                $m->id = (string) Str::uuid();
            }
        });
    }

    public function templates()
    {
        return $this->hasMany(ContractTemplate::class, 'type_id');
    }
}
