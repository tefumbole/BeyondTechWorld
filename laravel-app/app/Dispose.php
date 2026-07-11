<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Dispose extends Model
{
    protected $guarded = [];

    public function assets() {
        return $this->belongsTo('App\Asset', 'asset_id', 'id');
    }
}
