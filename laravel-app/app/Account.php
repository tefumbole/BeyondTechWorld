<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable =[
        "account_no","department_id", "name", "initial_balance", "total_balance", "note", "is_default", "is_active"
    ];

    public function departments() {
        return $this->belongsTo('App\Department', 'department_id');
    }

}
