<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shareholder extends Model
{
    use SoftDeletes;

    protected $table = 'shareholders';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'full_name', 'name', 'email', 'phone_number', 'phone', 'country_code',
        'full_phone_number', 'company_name', 'address', 'nationality', 'shares_assigned',
        'investment_amount', 'signature', 'signature_image_url', 'agreement_signed_at',
        'agreement_pdf_url', 'agreement_pdf_path', 'pdf_generated_at', 'payment_status',
        'reference_number', 'status', 'is_guest', 'user_id', 'submitted_at',
        'approved_at', 'approved_by', 'rejection_reason', 'admin_notes',
    ];

    protected $dates = [
        'agreement_signed_at', 'submitted_at', 'approved_at', 'pdf_generated_at', 'deleted_at',
    ];

    protected $casts = [
        'is_guest' => 'boolean',
        'investment_amount' => 'decimal:2',
    ];
}
