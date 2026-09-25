<?php

namespace App\WhatsApp;

use App\User;
use Illuminate\Database\Eloquent\Model;

class WhatsAppOwnerUser extends Model
{
    protected $table = 'whatsapp_owner_users';

    protected $fillable = ['user_id', 'normalized_phone', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
