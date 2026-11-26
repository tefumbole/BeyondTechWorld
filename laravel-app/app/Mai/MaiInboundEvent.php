<?php

namespace App\Mai;

use Illuminate\Database\Eloquent\Model;

class MaiInboundEvent extends Model
{
    protected $table = 'mai_inbound_events';

    protected $fillable = ['mai_session_id', 'event_id', 'kind'];
}
