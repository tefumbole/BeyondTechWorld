<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PosSetting extends Model
{
    protected $table = 'pos_setting';
    protected $fillable =[
        "customer_id", "warehouse_id", "biller_id", "product_number", "stripe_public_key", "stripe_secret_key", "keybord_active"
    ];

    public function getStripePublicKeyAttribute($value)
    {
        $key = trim((string) config('services.stripe.key'));

        return $key !== '' ? $key : $value;
    }

    public function getStripeSecretKeyAttribute($value)
    {
        $secret = trim((string) config('services.stripe.secret'));

        return $secret !== '' ? $secret : $value;
    }
}
