<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $guarded = [];

    public function units()
    {
        return $this->hasMany(PropertyUnit::class, 'property_id');
    }
}
