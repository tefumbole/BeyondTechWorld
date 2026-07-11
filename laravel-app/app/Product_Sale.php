<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Product_Sale extends Model
{
    protected $table = 'product_sales';
    protected $fillable =[
        "sale_id", "product_id", "category_id", "warehouse_id", "product_batch_id", "multi_product_batch_id", "multi_product_batch_qty", "variant_id", "qty", "sale_unit_id", "net_unit_price", "discount", "tax_rate", "tax", "total"
    ];
}
