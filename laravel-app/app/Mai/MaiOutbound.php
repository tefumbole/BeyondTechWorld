<?php

namespace App\Mai;

use Illuminate\Database\Eloquent\Model;

class MaiOutbound extends Model
{
    protected $table = 'mai_outbound_messages';

    protected $fillable = [
        'mai_session_id', 'idempotency_key', 'to_phone', 'body', 'status', 'provider_message_id', 'last_error',
    ];
}
