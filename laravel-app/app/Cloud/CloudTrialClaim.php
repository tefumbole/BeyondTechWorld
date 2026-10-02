<?php

namespace App\Cloud;

use Illuminate\Database\Eloquent\Model;

/**
 * One phone number may take a given module trial once.
 * The row stays if the company is removed, so the same number cannot start that trial again.
 */
class CloudTrialClaim extends Model
{
    protected $table = 'cloud_trial_claims';

    protected $fillable = [
        'normalized_phone',
        'cloud_module_id',
        'cloud_tenant_id',
        'claimed_at',
    ];

    protected $dates = [
        'claimed_at',
    ];

    public function module()
    {
        return $this->belongsTo(CloudModule::class, 'cloud_module_id');
    }

    public function cloudTenant()
    {
        return $this->belongsTo(CloudTenant::class, 'cloud_tenant_id');
    }
}
