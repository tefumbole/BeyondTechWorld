<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderSubmission extends Model
{
    protected $table = 'order_submissions';

    protected $fillable = [
        'cloud_tenant_id', 'idempotency_key', 'payload_hash', 'sale_id', 'user_id', 'status',
    ];
}
