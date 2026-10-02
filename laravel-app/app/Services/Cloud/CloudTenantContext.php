<?php

namespace App\Services\Cloud;

use App\Cloud\CloudTenant;

/**
 * The active company for this request or job.
 * Callers must set it from the server. Request input is not a source.
 * A missing company is not "every company".
 */
class CloudTenantContext
{
    /** @var CloudTenant|null */
    protected $tenant;

    /** @var int */
    protected $bypass = 0;

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

    public function has()
    {
        return $this->tenant !== null;
    }

    public function clear()
    {
        $this->tenant = null;

        return $this;
    }

    public function requireTenant()
    {
        if (! $this->tenant) {
            throw new MissingCloudTenantException('No CloudTenant is active.');
        }

        return $this->tenant;
    }

    public function require()
    {
        return $this->requireTenant();
    }

    public function bypassing()
    {
        return $this->bypass > 0;
    }

    /**
     * Privileged read for a token lookup or an audit. Not for tenant-facing screens.
     */
    public function withoutIsolation(callable $callback)
    {
        $this->bypass++;
        try {
            return $callback();
        } finally {
            $this->bypass--;
        }
    }
}
