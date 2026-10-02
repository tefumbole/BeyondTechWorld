<?php

namespace App\Services\Cloud;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Selected directly owned models only.
 * Without an active company the query matches nothing.
 */
class CloudTenantScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (! config('cloud.isolate_queries')) {
            return;
        }
        $context = app(CloudTenantContext::class);
        if ($context->bypassing()) {
            return;
        }
        if (! BelongsToCloudTenant::columnOn($model)) {
            return;
        }
        if (! $context->has()) {
            $builder->whereRaw('1 = 0');

            return;
        }
        $builder->where($model->getTable().'.cloud_tenant_id', $context->id());
    }
}
