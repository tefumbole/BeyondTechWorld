<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BeyondProfile extends Model
{
    protected $table = 'be_profiles';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'email', 'full_name', 'phone', 'role', 'username', 'address',
        'must_change_credentials', 'status', 'avatar_url',
    ];
}
