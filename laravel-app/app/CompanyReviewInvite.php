<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CompanyReviewInvite extends Model
{
    protected $fillable = [
        'token',
        'phone',
        'person_name',
        'source',
        'source_id',
        'sent_at',
    ];
}
