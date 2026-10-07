<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class YongInvitation extends Model
{
    protected $table = 'yong_invitations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'phone',
        'name',
        'position',
        'pledge_amount',
        'invitation_type',
        'image_file',
        'food_file',
        'ticket_code',
        'pdf_file',
        'payment_status',
        'campay_reference',
        'stripe_session_id',
        'paid_at',
        'eaten_at',
        'attended_at',
        'thanked_at',
    ];

    protected $casts = [
        'pledge_amount' => 'integer',
        'paid_at' => 'datetime',
        'eaten_at' => 'datetime',
        'attended_at' => 'datetime',
        'thanked_at' => 'datetime',
    ];

    public function hasPledge()
    {
        return (int) $this->pledge_amount >= 5000;
    }

    public function isPremium()
    {
        return $this->hasPledge();
    }

    public function imagePath()
    {
        return public_path('yong/out/'.$this->image_file);
    }

    public function imageUrl()
    {
        return asset('public/yong/out/'.$this->image_file);
    }

    public function foodPath()
    {
        return public_path('yong/out/'.$this->food_file);
    }

    public function pdfPath()
    {
        return $this->pdf_file ? public_path('yong/out/'.$this->pdf_file) : null;
    }

    public function pledgeLabel()
    {
        $amount = (int) $this->pledge_amount;

        return $amount > 0 ? number_format($amount).' FCFA' : 'no pledge';
    }

    public function typeLabel()
    {
        if ($this->invitation_type === 'clergy') {
            return 'Clergy';
        }
        if ($this->invitation_type === 'gold') {
            return 'Gold';
        }

        return 'Standard';
    }

    public function positionLabel()
    {
        $labels = [
            'friend' => 'Friend',
            'family' => 'Family',
            'clergy' => 'Clergy',
            'guest' => 'Guest',
        ];

        return isset($labels[$this->position]) ? $labels[$this->position] : 'Guest';
    }
}
