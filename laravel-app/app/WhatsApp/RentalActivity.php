<?php

namespace App\WhatsApp;

use Illuminate\Database\Eloquent\Model;

class RentalActivity extends Model
{
    protected $table = 'whatsapp_rental_activities';

    protected $fillable = ['rental_request_id', 'type', 'body', 'meta', 'actor_user_id'];
}
