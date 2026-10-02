<?php

namespace App\Services\Cloud;

/**
 * Holds the active CloudTenant for a future request or job.
 * Nothing in the existing ERP sets or reads this during Phase 1B.
 * A missing tenant must not be treated as "all companies".
 */
class CloudTenantContext
{
    /** @var \App\Cloud\CloudTenant|null */
    protected $tenant;

    public function set($tenant)
    {
        $this->tenant = $tenant;

        return $this;
    }

    public function get()
    {
        return $this->tenant;
    }

    public function id()
    {
        return $this->tenant ? $this->tenant->getKey() : null;
    }

    public function clear()
    {
        $this->tenant = null;

        return $this;
    }

    public function requireTenant()
    {
        if (! $this->tenant) {
            throw new \RuntimeException('No CloudTenant is active.');
        }

        return $this->tenant;
    }
}
