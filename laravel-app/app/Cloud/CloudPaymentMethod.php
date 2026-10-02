<?php

namespace App\Cloud;

use Illuminate\Database\Eloquent\Model;

/**
 * Global list of how a Cloud subscription can be paid.
 * Rows point at providers already configured for this install.
 * Secrets stay in the existing Campay and Stripe configuration, not in this table.
 */
class CloudPaymentMethod extends Model
{
    protected $table = 'cloud_payment_methods';

    protected $fillable = [
        'code',
        'name',
        'provider',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];
}
