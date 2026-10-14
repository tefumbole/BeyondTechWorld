<?php

namespace App;

use App\Support\CaroSeats;
use Illuminate\Database\Eloquent\Model;

class CaroInvitation extends Model
{
    protected $table = 'caro_invitations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'phone',
        'phone_digits',
        'name',
        'role',
        'seat_code',
        'image_file',
    ];

    public function roleLabel()
    {
        return CaroSeats::roleLabel($this->role);
    }

    public function imagePath()
    {
        return public_path('caro/out/'.$this->image_file);
    }

    public function imageUrl()
    {
        return asset('public/caro/out/'.$this->image_file);
    }
}
