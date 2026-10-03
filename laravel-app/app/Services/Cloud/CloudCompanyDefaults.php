<?php

namespace App\Services\Cloud;

use App\Biller;
use App\Brand;
use App\Category;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantMembership;
use App\Cloud\CloudTenantSetting;
use App\Cloud\CloudTenantType;
use App\Customer;
use App\CustomerGroup;
use App\Unit;
use App\User;
use App\Warehouse;
use Illuminate\Support\Facades\Schema;

/**
 * First-time records a new company needs before it can sell.
 * Walk-in is the default customer. Warehouse, unit, brand, category,
 * and the sales biller use the company name, or the person's name.
 */
class CloudCompanyDefaults
{
    public function ensure(CloudTenant $tenant)
    {
        if ($tenant->type !== CloudTenantType::CUSTOMER) {
            return;
        }
        if (! Schema::hasTable('cloud_tenant_settings') || ! $this->tablesReady()) {
            return;
        }
        if ($this->ready($tenant)) {
            return;
        }

        $context = app(CloudTenantContext::class);
        $previous = $context->tenant();
        $context->set($tenant);
        try {
            $label = $this->label($tenant);
            $warehouse = $this->warehouse($tenant, $label);
            $unit = $this->unit($tenant, $label);
            $brand = $this->brand($label);
            $category = $this->category($label);
            $biller = $this->biller($tenant, $label);
            $this->walkIn($tenant, $label);
            $this->assignStaff($tenant, $warehouse, $biller);
            $this->remember($tenant->id, 'default_warehouse_id', $warehouse ? $warehouse->id : '');
            $this->remember($tenant->id, 'unit_id', $unit ? $unit->id : '');
            $this->remember($tenant->id, 'category_id', $category ? $category->id : '');
            $this->remember($tenant->id, 'default_biller_id', $biller ? $biller->id : '');
            if ($brand) {
                $this->remember($tenant->id, 'default_brand_id', $brand->id);
            }
            $this->remember($tenant->id, 'defaults_ready', '1');
        } finally {
            if ($previous) {
                $context->set($previous);
            } else {
                $context->clear();
            }
        }
    }

    protected function tablesReady()
    {
        foreach (['customers', 'customer_groups', 'warehouses', 'units', 'billers', 'brands', 'categories'] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        return true;
    }

    protected function ready(CloudTenant $tenant)
    {
        return CloudTenantSetting::where('cloud_tenant_id', $tenant->id)
            ->where('key', 'defaults_ready')
            ->where('value', '1')
            ->exists();
    }

    protected function label(CloudTenant $tenant)
    {
        $kind = CloudTenantSetting::where('cloud_tenant_id', $tenant->id)
            ->where('key', 'account_kind')
            ->value('value');
        $name = $kind === 'personal'
            ? ($tenant->name ?: $tenant->system_name)
            : ($tenant->system_name ?: $tenant->name);
        $name = trim((string) $name);

        return $name !== '' ? mb_substr($name, 0, 80) : 'Company';
    }

    protected function warehouse(CloudTenant $tenant, $label)
    {
        $existing = Warehouse::where('is_active', true)->first();
        if ($existing) {
            return $existing;
        }
        $row = $this->only('warehouses', [
            'name' => $label,
            'phone' => $this->phone($tenant),
            'email' => $this->email($tenant),
            'address' => $this->address($tenant, $label),
            'is_active' => 1,
        ]);

        return Warehouse::create($row);
    }

    protected function unit(CloudTenant $tenant, $label)
    {
        $existing = Unit::where('is_active', true)->first();
        if ($existing) {
            return $existing;
        }
        $row = $this->only('units', [
            'unit_code' => 'PC'.$tenant->id,
            'unit_name' => $label,
            'base_unit' => null,
            'operator' => '*',
            'operation_value' => 1,
            'is_active' => 1,
        ]);

        return Unit::create($row);
    }

    protected function brand($label)
    {
        $existing = Brand::where('is_active', true)->first();
        if ($existing) {
            return $existing;
        }

        return Brand::create($this->only('brands', [
            'title' => $label,
            'is_active' => 1,
        ]));
    }

    protected function category($label)
    {
        $existing = Category::where('is_active', true)->first();
        if ($existing) {
            return $existing;
        }

        return Category::create($this->only('categories', [
            'name' => $label,
            'parent_id' => null,
            'is_active' => 1,
        ]));
    }

    protected function biller(CloudTenant $tenant, $label)
    {
        $saved = CloudTenantSetting::where('cloud_tenant_id', $tenant->id)
            ->where('key', 'default_biller_id')
            ->value('value');
        if ($saved && Biller::where('id', $saved)->where('is_active', true)->exists()) {
            return Biller::find($saved);
        }
        $row = $this->only('billers', [
            'name' => $label,
            'company_name' => $label,
            'email' => $this->email($tenant),
            'phone_number' => $this->phone($tenant),
            'address' => $this->address($tenant, $label),
            'city' => $this->city($tenant),
            'country' => $tenant->country,
            'is_active' => 1,
        ]);

        return Biller::create($row);
    }

    protected function walkIn(CloudTenant $tenant, $label)
    {
        $existing = Customer::where('is_active', true)->where('name', 'Walk-in')->first();
        if ($existing) {
            return $existing;
        }
        $groupId = CustomerGroup::where('is_active', true)->value('id');
        if (! $groupId) {
            $group = CustomerGroup::create($this->only('customer_groups', [
                'name' => 'General',
                'percentage' => 0,
                'is_active' => 1,
            ]));
            $groupId = $group->id;
        }
        $row = $this->only('customers', [
            'customer_group_id' => $groupId,
            'name' => 'Walk-in',
            'company_name' => $label,
            'email' => $this->email($tenant),
            'phone_number' => $this->phone($tenant),
            'address' => $this->address($tenant, $label),
            'city' => $this->city($tenant),
            'country' => $tenant->country,
            'deposit' => 0,
            'expense' => 0,
            'points' => 0,
            'is_active' => 1,
        ]);

        return Customer::create($row);
    }

    protected function assignStaff(CloudTenant $tenant, $warehouse, $biller)
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('cloud_tenant_memberships')) {
            return;
        }
        $ids = CloudTenantMembership::where('cloud_tenant_id', $tenant->id)->pluck('user_id');
        if ($ids->isEmpty()) {
            return;
        }
        foreach (User::whereIn('id', $ids)->get() as $user) {
            $dirty = false;
            if ($warehouse && Schema::hasColumn('users', 'warehouse_id') && ! $user->warehouse_id) {
                $user->warehouse_id = $warehouse->id;
                $dirty = true;
            }
            if ($biller && Schema::hasColumn('users', 'biller_id') && ! $user->biller_id) {
                $user->biller_id = $biller->id;
                $dirty = true;
            }
            if ($dirty) {
                $payload = [];
                if (Schema::hasColumn('users', 'warehouse_id')) {
                    $payload['warehouse_id'] = $user->warehouse_id;
                }
                if (Schema::hasColumn('users', 'biller_id')) {
                    $payload['biller_id'] = $user->biller_id;
                }
                User::where('id', $user->id)->update($payload);
            }
        }
    }

    protected function remember($tenantId, $key, $value)
    {
        $row = CloudTenantSetting::firstOrNew([
            'cloud_tenant_id' => $tenantId,
            'key' => $key,
        ]);
        $row->value = (string) $value;
        $row->type = 'string';
        $row->save();
    }

    protected function only($table, array $row)
    {
        $kept = [];
        foreach ($row as $key => $value) {
            if (Schema::hasColumn($table, $key)) {
                $kept[$key] = $value;
            }
        }

        return $kept;
    }

    protected function phone(CloudTenant $tenant)
    {
        $phone = trim((string) $tenant->phone);

        return $phone !== '' ? $phone : '000000000';
    }

    protected function email(CloudTenant $tenant)
    {
        $email = trim((string) $tenant->email);

        return $email !== '' ? $email : 'company'.$tenant->id.'@localhost';
    }

    protected function address(CloudTenant $tenant, $label)
    {
        $address = trim((string) $tenant->address);

        return $address !== '' ? $address : $label;
    }

    protected function city(CloudTenant $tenant)
    {
        $city = trim((string) $tenant->city);

        return $city !== '' ? $city : 'N/A';
    }
}
