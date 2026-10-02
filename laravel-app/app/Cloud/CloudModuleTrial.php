<?php

namespace App\Cloud;

use Illuminate\Database\Eloquent\Model;

class CloudModuleTrial extends Model
{
    protected $table = 'cloud_module_trials';

    const INTRODUCTORY = 'INTRODUCTORY';
    const ADMIN = 'ADMIN';

    protected $fillable = [
        'cloud_tenant_id',
        'cloud_module_id',
        'source',
        'normalized_phone',
        'started_at',
        'ends_at',
        'actor_user_id',
    ];

    protected $dates = [
        'started_at',
        'ends_at',
    ];
}
