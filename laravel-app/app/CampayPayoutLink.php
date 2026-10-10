<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CampayPayoutLink extends Model
{
    protected $table = 'campay_payout_links';

    protected $fillable = ['token', 'cloud_tenant_id'];
}
