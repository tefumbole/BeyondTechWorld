<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DocumentPaymentLink extends Model
{
    protected $fillable = [
        'cloud_tenant_id',
        'kind',
        'document_id',
        'token',
        'reference_no',
        'person_name',
        'phone',
        'requested_by',
        'amount',
        'method',
        'status',
        'campay_reference',
        'payment_link',
        'error',
    ];
}
