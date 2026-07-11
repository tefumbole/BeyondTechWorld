<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable =[
        "customer_group_id", "user_id", "name", "company_name",
        "email", "phone_number", "tax_no", "address", "city",
        "state", "postal_code", "country", "points", "deposit", "expense", "is_active", "credit_limit"
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function (Customer $customer) {
            if (!empty($customer->phone_number)) {
                $customer->phone_number = \App\Support\WhatsAppPhone::sanitizeForStorage($customer->phone_number);
            }
        });
    }

    public function user()
    {
    	return $this->belongsTo('App\User');
    }

    public function sales()
    {
        return $this->hasMany('App\Sale');
    }
}
