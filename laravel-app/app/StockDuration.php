<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockDuration extends Model
{
    public $timestamps = false;
    protected $fillable =[

        "product_id", "out_of_stock", "restock"
    ];

    public function product()
    {
    	return $this->hasMany('App/Product');

    }
}
