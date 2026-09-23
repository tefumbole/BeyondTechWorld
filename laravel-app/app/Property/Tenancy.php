<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class Tenancy extends Model
{
    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();
        static::saving(function (Tenancy $row) {
            $row->active_unit_lock = $row->status === 'ACTIVE' ? (int) $row->unit_id : null;
        });
    }

    public function unit()
    {
        return $this->belongsTo(PropertyUnit::class, 'unit_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
