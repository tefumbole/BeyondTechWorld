<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class YongDonation extends Model
{
    protected $table = 'yong_donations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'phone',
        'name',
        'name_source',
        'amount',
        'method',
        'status',
        'campay_reference',
        'stripe_session_id',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'paid_at' => 'datetime',
    ];
}
