<?php

namespace App\Cloud;

use Illuminate\Database\Eloquent\Model;

/**
 * Global price row for one module. Edit the database row. Do not copy price into PHP checks.
 */
class CloudPlan extends Model
{
    protected $table = 'cloud_plans';

    protected $fillable = [
        'cloud_module_id',
        'code',
        'name',
        'billing_interval',
        'price',
        'currency',
        'trial_value',
        'trial_unit',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean',
        'trial_value' => 'integer',
    ];

    public function module()
    {
        return $this->belongsTo(CloudModule::class, 'cloud_module_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(CloudSubscription::class, 'cloud_plan_id');
    }
}
