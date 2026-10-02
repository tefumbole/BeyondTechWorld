<?php

namespace App;

use App\Services\Cloud\BelongsToCloudTenant;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    use BelongsToCloudTenant;
    protected $fillable =[

        "title", "image", "is_active"
    ];

    public function product()
    {
    	return $this->hasMany('App/Product');
    	
    }
}
