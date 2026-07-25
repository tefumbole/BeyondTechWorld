<?php

namespace App;

use App\Traits\NormalizesWhatsAppPhones;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use NormalizesWhatsAppPhones;

    protected $whatsappPhoneAttributes = ['phone_number'];

    protected $fillable =[
        "customer_group_id", "user_id", "name", "company_name",
        "email", "phone_number", "tax_no", "address", "city",
        "state", "postal_code", "country", "points", "deposit", "expense", "is_active", "credit_limit"
    ];

    public function user()
    {
    	return $this->belongsTo('App\User');
    }

    public function sales()
    {
        return $this->hasMany('App\Sale');
    }
}
