<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class paymentRequest extends Model
{
    protected $guarded = [];

    public function order() {
        return $this->belongsTo('App\Order', 'order_id', 'id');
    }

    public function vendor() {
        return $this->belongsTo('App\User', 'vendor_id', 'id');
    }
}
