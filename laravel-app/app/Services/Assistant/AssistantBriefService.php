<?php

namespace App\Services\Assistant;

use App\Assistant\AssistantBrief;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantType;
use App\Services\Cloud\CloudTenantContext;
use Illuminate\Support\Facades\Schema;

class AssistantBriefService
{
    public function visible()
    {
        $query = AssistantBrief::query()->orderByDesc('id');
        if (! Schema::hasColumn('assistant_briefs', 'cloud_tenant_id')) {
            return $query;
        }
        $context = app(CloudTenantContext::class);
        if ($context->has() && $context->tenant() && $context->tenant()->type === CloudTenantType::CUSTOMER) {
            return $query->where('cloud_tenant_id', $context->id());
        }
        $internalId = $this->internalId();

        return $query->where(function ($rows) use ($internalId) {
            $rows->whereNull('cloud_tenant_id');
            if ($internalId) {
                $rows->orWhere('cloud_tenant_id', $internalId);
            }
        });
    }

    public function promptText()
    {
        if (! Schema::hasTable('assistant_briefs')) {
            return '';
        }
        $lines = [];
        foreach ($this->visible()->where('enabled', true)->limit(20)->get() as $brief) {
            if ($brief->ends_at && $brief->ends_at->lt(now())) {
                continue;
            }
            $when = '';
            if ($brief->starts_at && $brief->ends_at) {
                $when = ' from '.$brief->starts_at->format('j M Y H:i').' until '.$brief->ends_at->format('j M Y H:i');
            } elseif ($brief->starts_at) {
                $when = ' from '.$brief->starts_at->format('j M Y H:i');
            } elseif ($brief->ends_at) {
                $when = ' until '.$brief->ends_at->format('j M Y H:i');
            }
            $lines[] = '- '.$brief->title.$when.': '.trim((string) $brief->details);
        }
        if ($lines === []) {
            return '';
        }

        return "What is true right now. When someone asks where you are, what you are doing, or about one of these events, answer from this and do not add facts that are not written here.\n"
            .implode("\n", $lines);
    }

    public function tenantIdForSave()
    {
        $context = app(CloudTenantContext::class);
        if ($context->has()) {
            return (int) $context->id();
        }

        return $this->internalId();
    }

    protected function internalId()
    {
        if (! Schema::hasTable('cloud_tenants')) {
            return null;
        }
        $id = CloudTenant::where('slug', config('cloud.internal_slug', 'beyondtechworld'))->value('id');

        return $id ? (int) $id : null;
    }
}
