<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable =[
        "name", "code", "is_active"
    ];

    public function accounts() {
        return $this->hasOne('App\Account');
    }
}
