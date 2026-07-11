<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable =[

        "name", "code", "type", "requires_quantity", "location", "vendor_id", "barcode_symbology", "brand_id", "category_id", "unit_id", "purchase_unit_id", "sale_unit_id", "cost", "price", "qty", "alert_quantity", "promotion", "promotion_price", "starting_date", "last_date", "tax_id", "tax_method", "image", "file", "is_batch", "is_variant", "is_diffPrice", "featured", "product_list", "qty_list", "price_list", "product_details", "is_active", "rent_price_per_hour", "rent_price_per_day", "rent_price_per_month"
    ];

    public function category()
    {
    	return $this->belongsTo('App\Category');
    }

    public function vendor()
    {
        return $this->belongsTo('App\User', 'vendor_id', 'id');
    }

    public function brand()
    {
    	return $this->belongsTo('App\Brand');
    }

    public function stockDuration()
    {
        return $this->belongsTo('App\stockDuration');
    }

    public function unit()
    {
        return $this->belongsTo('App\Unit');
    }

    public function reviews()
    {
        return $this->hasMany('App\Review')->orderByDesc('id');
    }

    public function variant()
    {
        return $this->belongsToMany('App\Variant', 'product_variants')->withPivot('id', 'item_code', 'additional_price');
    }

    public function warehouses()
    {
        return $this->belongsToMany('App\Warehouse', 'product_warehouse')->withPivot('qty');
    }

    public function scopeSumWarehouseQty($query, $productId, $warehouseId)
    {
        $result = $query->leftJoin('product_warehouse', 'products.id', '=', 'product_warehouse.product_id')->where('products.id', $productId);

        if($warehouseId != 0) {
            $result = $result->where('product_warehouse.warehouse_id', $warehouseId);
        }
        if (!empty($result)) {
            $result = $result->sum('product_warehouse.qty');
        }

        if($result == null) {
            return 0;
        }
        return $result;
    }

    public function scopeActiveStandard($query)
    {
        return $query->where([
            ['is_active', true],
            ['type', 'standard']
        ]);
    }

    public function scopeActiveFeatured($query)
    {
        return $query->where([
            ['is_active', true],
            ['featured', 1]
        ]);
    }
}
