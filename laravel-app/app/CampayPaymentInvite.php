<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CampayPaymentInvite extends Model
{
    protected $table = 'campay_payment_invites';

    protected $fillable = ['cloud_tenant_id', 'reason', 'people', 'sent_at'];

    protected $casts = [
        'people' => 'array',
        'sent_at' => 'datetime',
    ];
}
