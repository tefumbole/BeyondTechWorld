<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CampayPayoutRequest extends Model
{
    protected $table = 'campay_payout_requests';

    protected $fillable = ['cloud_tenant_id', 'requester_name', 'note', 'status'];

    public function lines()
    {
        return $this->hasMany(CampayPayout::class, 'request_id');
    }
}
