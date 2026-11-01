<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PaymentAttempt extends Model
{
    protected $table = 'payment_attempts';

    protected $fillable = [
        'cloud_tenant_id', 'checkout_key', 'sale_id', 'provider', 'provider_reference',
        'external_reference', 'amount', 'status', 'payload_hash', 'request_payload', 'last_error',
    ];

    protected $casts = [
        'request_payload' => 'array',
    ];
}
