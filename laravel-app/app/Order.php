<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    public function orderProducts() {
        return $this->hasMany('App\OrderProduct', 'order_id');
    }

    public function vendor() {
        return $this->belongsTo('App\User', 'vendor_id', 'id');
    }
}
