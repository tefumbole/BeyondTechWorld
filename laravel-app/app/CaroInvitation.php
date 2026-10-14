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
        'presented_at',
        'thanks_sent_at',
        'invitation_sent_at',
    ];

    protected $dates = [
        'presented_at',
        'thanks_sent_at',
        'invitation_sent_at',
    ];

    public function roleLabel()
    {
        return CaroSeats::roleLabel($this->role);
    }

    public function seatLabel()
    {
        return CaroSeats::label($this->seat_code);
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
