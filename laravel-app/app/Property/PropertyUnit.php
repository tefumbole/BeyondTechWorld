<?php

namespace App\Property;

use Illuminate\Database\Eloquent\Model;

class PropertyUnit extends Model
{
    protected $guarded = [];

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}
