<?php

namespace App\Services\Cloud;

use App\Cloud\CloudTenantType;
use App\GeneralSetting;

/**
 * Customer companies use their CloudTenant name.
 * BeyondTechWorld keeps the existing general settings appearance.
 */
class CloudBranding
{
    public function documentName($tenant = null)
    {
        if (! $tenant) {
            $tenant = app(CloudTenantContext::class)->get();
        }
        if ($tenant && $tenant->type !== CloudTenantType::INTERNAL) {
            $name = trim((string) ($tenant->legal_name ?: $tenant->system_name ?: $tenant->name));
            if ($name !== '') {
                return $name;
            }
        }
        if (class_exists(GeneralSetting::class)) {
            $settings = GeneralSetting::query()->first();
            if ($settings && ! empty($settings->site_title)) {
                return $settings->site_title;
            }
        }

        return 'Beyond Enterprise';
    }
}
