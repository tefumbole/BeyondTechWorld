<?php

namespace App\Services\Cloud;

use App\Cloud\CloudTenant;

/**
 * Run one job inside a CloudTenant, then clear it.
 * Workers must not keep a process-wide tenant after the job ends.
 */
class CloudTenantContextRunner
{
    public function run($cloudTenantId, callable $callback)
    {
        $context = app(CloudTenantContext::class);
        $tenant = CloudTenant::find($cloudTenantId);
        if (! $tenant) {
            throw new \RuntimeException('Unknown CloudTenant.');
        }

        $context->set($tenant);
        try {
            return $callback($tenant);
        } finally {
            $context->clear();
        }
    }
}
