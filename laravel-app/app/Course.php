<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $table = 'courses';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'legacy_id', 'name', 'slug', 'description', 'price', 'duration',
        'delivery_mode', 'category', 'curriculum_json', 'icon', 'color',
        'sort_order', 'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function getSectionsAttribute()
    {
        return json_decode($this->curriculum_json ?: '[]', true) ?: [];
    }
}
