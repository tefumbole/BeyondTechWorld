<?php

namespace App\Cloud;

use Illuminate\Database\Eloquent\Model;

/**
 * Global platform catalog. Not owned by a company.
 */
class CloudModule extends Model
{
    protected $table = 'cloud_modules';

    protected $fillable = [
        'code',
        'name',
        'description',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function plans()
    {
        return $this->hasMany(CloudPlan::class, 'cloud_module_id');
    }
}
