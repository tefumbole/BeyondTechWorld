<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EventPackageComponent extends Model
{
    protected $fillable = [
        'event_package_id', 'component_type', 'category_key', 'product_id',
        'qty', 'required', 'search_query',
    ];

    protected $casts = [
        'qty' => 'integer',
        'required' => 'boolean',
    ];

    public function package()
    {
        return $this->belongsTo(EventPackage::class, 'event_package_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
