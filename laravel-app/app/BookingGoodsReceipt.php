<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BookingGoodsReceipt extends Model
{
    protected $fillable = [
        'booking_id',
        'user_id',
        'reference_no',
        'signature_token',
        'signed_at',
        'signature_image',
        'delivered_signed_at',
        'delivered_signature_image',
        'delivered_by_name',
        'delivery_note_pdf_path',
        'signed_pdf_path',
        'signature_sent_at',
        'delivered_signature_sent_at',
    ];

    protected $dates = [
        'signed_at',
        'signature_sent_at',
        'delivered_signed_at',
        'delivered_signature_sent_at',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
