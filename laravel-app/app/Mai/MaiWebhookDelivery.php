<?php

namespace App\Mai;

use Illuminate\Database\Eloquent\Model;

class MaiWebhookDelivery extends Model
{
    protected $table = 'mai_webhook_deliveries';

    protected $fillable = [
        'mai_webhook_id', 'event_id', 'payload', 'attempts', 'next_attempt_at', 'delivered_at', 'last_error',
    ];

    protected $dates = ['next_attempt_at', 'delivered_at'];
}
