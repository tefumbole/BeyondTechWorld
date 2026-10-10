<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PublicDonation extends Model
{
    protected $fillable = [
        'token',
        'person_name',
        'phone',
        'amount',
        'note',
        'method',
        'status',
        'campay_reference',
        'payment_link',
        'error',
    ];
}
