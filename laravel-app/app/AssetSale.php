<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AssetSale extends Model
{
    protected $guarded = [];


    public function saleDetails() {
        return $this->hasMany('App\AssetSaleDetail', 'asset_sale_id');
    }
}
