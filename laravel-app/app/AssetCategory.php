<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AssetCategory extends Model
{
    protected $fillable =[
        "name", 'image', "parent_id", "is_active"
    ];

}
