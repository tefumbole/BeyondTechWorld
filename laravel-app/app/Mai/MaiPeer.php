<?php

namespace App\Mai;

use Illuminate\Database\Eloquent\Model;

class MaiPeer extends Model
{
    protected $table = 'mai_peers';

    protected $fillable = [
        'mai_session_id', 'remote_jid', 'phone_digits', 'lid', 'saved_name', 'profile_name',
    ];
}
