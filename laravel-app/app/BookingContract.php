<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BookingContract extends Model
{
    const STATUS_PENDING_CLIENT = 'pending_client';
    const STATUS_PENDING_REVIEW = 'pending_review';
    const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'booking_id',
        'contract_type',
        'signature_token',
        'qr_token',
        'agreement_read_at',
        'signed_at',
        'review_status',
        'signature_image',
        'admin_signature_image',
        'admin_signed_at',
        'admin_signed_by',
        'approved_at',
        'id_card_path',
        'signed_pdf_path',
        'client_user_id',
        'client_username',
        'generated_password',
    ];

    protected $dates = [
        'agreement_read_at',
        'signed_at',
        'admin_signed_at',
        'approved_at',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function clientUser()
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function adminSigner()
    {
        return $this->belongsTo(User::class, 'admin_signed_by');
    }

    public function isApproved()
    {
        return $this->review_status === self::STATUS_APPROVED
            || ($this->signed_at && empty($this->review_status));
    }

    public function isPendingReview()
    {
        return $this->review_status === self::STATUS_PENDING_REVIEW;
    }
}
