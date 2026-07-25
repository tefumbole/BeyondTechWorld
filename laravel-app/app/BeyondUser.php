<?php

namespace App;

use App\Traits\NormalizesWhatsAppPhones;
use Illuminate\Foundation\Auth\User as Authenticatable;

class BeyondUser extends Authenticatable
{
    use NormalizesWhatsAppPhones;

    protected $whatsappPhoneAttributes = ['phone'];

    protected $table = 'be_users';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'email', 'username', 'password_hash', 'name', 'role', 'status',
        'phone', 'address', 'must_change_credentials',
    ];

    protected $hidden = ['password_hash'];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function profile()
    {
        return $this->hasOne(BeyondProfile::class, 'id', 'id');
    }
}
