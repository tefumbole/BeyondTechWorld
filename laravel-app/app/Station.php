<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    protected $fillable =[
        "name", 'region_id'
    ];

    public function regions()
    {
        return $this->belongsTo('App\Region', 'region_id');

    }

}
