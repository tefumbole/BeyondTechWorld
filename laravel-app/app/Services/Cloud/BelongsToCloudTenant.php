<?php

namespace App\Services\Cloud;

use Illuminate\Database\Eloquent\Model;

/**
 * Server-side ownership for one company.
 * cloud_tenant_id is not mass-assignable. The active context wins.
 */
trait BelongsToCloudTenant
{
    public static function bootBelongsToCloudTenant()
    {
        static::addGlobalScope(new CloudTenantScope());
        static::creating(function (Model $model) {
            static::assignTenant($model);
            static::assertRelations($model);
        });
        static::updating(function (Model $model) {
            static::keepTenant($model);
            static::assertRelations($model);
        });
    }

    public static function columnOn(Model $model)
    {
        return CloudTenantColumns::present($model);
    }

    /**
     * Foreign keys that must belong to the same company.
     *
     * @return array<string, string>
     */
    public function cloudTenantRelations()
    {
        return [];
    }

    protected static function assignTenant(Model $model)
    {
        if (! static::columnOn($model)) {
            return;
        }
        $context = app(CloudTenantContext::class);
        if ($context->bypassing()) {
            return;
        }
        if (! $context->has()) {
            throw new MissingCloudTenantException('Cannot save '.$model->getTable().' without an active company.');
        }
        $model->setAttribute('cloud_tenant_id', $context->id());
    }

    protected static function keepTenant(Model $model)
    {
        if (! static::columnOn($model)) {
            return;
        }
        $context = app(CloudTenantContext::class);
        if ($context->bypassing()) {
            return;
        }
        if (! $context->has()) {
            throw new MissingCloudTenantException('Cannot update '.$model->getTable().' without an active company.');
        }
        $original = $model->getOriginal('cloud_tenant_id');
        if ($original !== null && (int) $original !== (int) $context->id()) {
            throw new CrossTenantRelationException('That record belongs to another company.');
        }
        $model->setAttribute('cloud_tenant_id', $context->id());
    }

    protected static function assertRelations(Model $model)
    {
        if (! static::columnOn($model)) {
            return;
        }
        $context = app(CloudTenantContext::class);
        if ($context->bypassing()) {
            return;
        }
        $relations = $model->cloudTenantRelations();
        foreach ($relations as $foreignKey => $class) {
            $id = $model->getAttribute($foreignKey);
            if ($id === null || $id === '' || $id === 0) {
                continue;
            }
            if (! class_exists($class)) {
                continue;
            }
            $related = new $class();
            if (! $related instanceof Model || ! static::columnOn($related)) {
                continue;
            }
            $found = $class::query()->whereKey($id)->exists();
            if (! $found) {
                throw new CrossTenantRelationException('That '.$foreignKey.' belongs to another company.');
            }
        }
    }
}
