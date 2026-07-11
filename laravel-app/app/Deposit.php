<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    protected $guarded =[
    ];

    public function customer()
    {
        return $this->belongsTo('App\Customer');
    }

    public function customerGroup()
    {
        return $this->belongsTo('App\CustomerGroup', 'customer_group_id');
    }

    public function user()
    {
        return $this->belongsTo('App\User');
    }

    public function depositor()
    {
        return $this->belongsTo('App\User', 'depositor_id');
    }
}
