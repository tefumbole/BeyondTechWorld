<?php

namespace App\Services\Cloud;

use App\Cloud\CloudModule;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTrialClaim;
use App\Support\WhatsAppPhone;

/**
 * Trial gate for Beyond Cloud. Does not start a subscription and does not take payment.
 */
class CloudTrialEligibility
{
    public function normalizePhone($phone)
    {
        return WhatsAppPhone::sanitizeForStorage($phone);
    }

    public function alreadyUsed($phone, CloudModule $module)
    {
        $normalized = $this->normalizePhone($phone);
        if ($normalized === '') {
            return false;
        }

        return CloudTrialClaim::where('normalized_phone', $normalized)
            ->where('cloud_module_id', $module->id)
            ->exists();
    }

    /**
     * Record that this phone has used this module's trial.
     * A second attempt for the same module and phone is refused.
     * Another module is a different trial.
     *
     * @param  \App\Cloud\CloudTenant|null  $tenant
     * @return \App\Cloud\CloudTrialClaim
     */
    public function claim($phone, CloudModule $module, CloudTenant $tenant = null)
    {
        $normalized = $this->normalizePhone($phone);
        if ($normalized === '') {
            throw new \InvalidArgumentException('A phone number is required for a trial.');
        }
        if ($this->alreadyUsed($normalized, $module)) {
            throw new \RuntimeException('This phone number has already used the trial for this module.');
        }

        return CloudTrialClaim::create([
            'normalized_phone' => $normalized,
            'cloud_module_id' => $module->id,
            'cloud_tenant_id' => $tenant ? $tenant->id : null,
            'claimed_at' => now(),
        ]);
    }
}
