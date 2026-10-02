<?php

namespace App\Services\Cloud;

class CloudFileGuard
{
    public function allows($record)
    {
        if (! $record || ! CloudTenantColumns::present($record)) {
            return true;
        }
        $contextId = app(CloudTenantContext::class)->id();
        if (! $contextId || ! $record->cloud_tenant_id) {
            return false;
        }

        return (int) $record->cloud_tenant_id === (int) $contextId;
    }
}
