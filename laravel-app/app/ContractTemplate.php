<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContractTemplate extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'type_id', 'name', 'code', 'description', 'current_version_id', 'layout_id', 'active', 'created_by',
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

    public function type()
    {
        return $this->belongsTo(ContractType::class, 'type_id');
    }

    public function versions()
    {
        return $this->hasMany(ContractTemplateVersion::class, 'template_id')->orderByDesc('version_no');
    }

    public function currentVersion()
    {
        return $this->belongsTo(ContractTemplateVersion::class, 'current_version_id');
    }
}
