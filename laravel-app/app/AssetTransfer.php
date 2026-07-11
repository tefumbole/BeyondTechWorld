<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AssetTransfer extends Model
{
    protected $guarded = [];

    public function assets() {
        return $this->belongsTo('App\Asset', 'asset_id', 'id');
    }

    public function fromDepartment() {
        return $this->belongsTo('App\Department', 'from', 'id');
    }
    public function toDepartment() {
        return $this->belongsTo('App\Department', 'from', 'id');
    }
}
