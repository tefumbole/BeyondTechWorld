<?php

namespace App\Services\Cloud;

use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantStatus;
use App\Cloud\CloudTenantType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reports, and only when asked, writes nullable cloud_tenant_id values.
 * Child tables that follow a parent are counted and then skipped.
 */
class BeyondOwnershipRehearsal
{
    const LIVE_DATABASE = 'beyondtechworld_laravel';

    public function liveDatabase()
    {
        return DB::connection()->getDatabaseName() === self::LIVE_DATABASE;
    }

    public function report($execute, $seedTestTenant)
    {
        if ($execute && $this->liveDatabase()) {
            throw new \RuntimeException('Refusing to write ownership on the live beyondtechworld_laravel database.');
        }
        if ($seedTestTenant && $this->liveDatabase()) {
            throw new \RuntimeException('Refusing to create a test company on the live database.');
        }

        $tenant = CloudTenant::where('slug', CloudInternalTenantService::SLUG)
            ->where('type', CloudTenantType::INTERNAL)
            ->first();

        $lines = [];
        $before = [];
        foreach ($this->tables() as $table => $meta) {
            $before[$table] = $this->countTable($table);
            $lines[] = $this->inspect($table, $meta, $tenant, $execute);
        }

        $test = null;
        if ($seedTestTenant && $tenant) {
            $test = $this->seedTestTenant();
        }

        $relationships = $this->relationships();

        return [
            'database' => DB::connection()->getDatabaseName(),
            'executed' => (bool) $execute,
            'tenant_id' => $tenant ? $tenant->id : null,
            'lines' => $lines,
            'before' => $before,
            'relationships' => $relationships,
            'test_tenant' => $test,
        ];
    }

    public function tables()
    {
        return [
            'products' => ['mode' => 'direct', 'parent' => null],
            'categories' => ['mode' => 'direct', 'parent' => null],
            'brands' => ['mode' => 'direct', 'parent' => null],
            'units' => ['mode' => 'direct', 'parent' => null],
            'warehouses' => ['mode' => 'direct', 'parent' => null],
            'customers' => ['mode' => 'direct', 'parent' => null],
            'suppliers' => ['mode' => 'direct', 'parent' => null],
            'sales' => ['mode' => 'direct', 'parent' => null],
            'quotations' => ['mode' => 'direct', 'parent' => null],
            'payments' => ['mode' => 'direct', 'parent' => null],
            'bookings' => ['mode' => 'direct', 'parent' => null],
            'whatsapp_contacts' => ['mode' => 'direct', 'parent' => null],
            'whatsapp_conversations' => ['mode' => 'direct', 'parent' => null],
            'product_sales' => ['mode' => 'derived', 'parent' => 'sales'],
            'product_quotation' => ['mode' => 'derived', 'parent' => 'quotations'],
            'booking_products' => ['mode' => 'derived', 'parent' => 'bookings'],
            'whatsapp_messages' => ['mode' => 'derived', 'parent' => 'whatsapp_conversations'],
        ];
    }

    protected function inspect($table, array $meta, $tenant, $execute)
    {
        $line = [
            'table' => $table,
            'mode' => $meta['mode'],
            'parent' => $meta['parent'],
            'total' => 0,
            'already_owned' => 0,
            'unowned' => 0,
            'would_assign' => 0,
            'ambiguous' => 0,
            'skipped' => 0,
            'errors' => '',
        ];

        if (! Schema::hasTable($table)) {
            $line['skipped'] = 1;
            $line['errors'] = 'table missing';

            return $line;
        }

        $line['total'] = (int) DB::table($table)->count();

        if ($meta['mode'] === 'derived') {
            $line['skipped'] = $line['total'];
            $line['errors'] = 'ownership derives from '.$meta['parent'];

            return $line;
        }

        if (! Schema::hasColumn($table, 'cloud_tenant_id')) {
            $line['unowned'] = $line['total'];
            $line['skipped'] = $line['total'];
            $line['errors'] = 'cloud_tenant_id is not on this table yet';

            return $line;
        }

        if (! $tenant) {
            $line['unowned'] = (int) DB::table($table)->whereNull('cloud_tenant_id')->count();
            $line['errors'] = 'INTERNAL BeyondTechWorld tenant is missing';

            return $line;
        }

        $line['already_owned'] = (int) DB::table($table)->where('cloud_tenant_id', $tenant->id)->count();
        $line['unowned'] = (int) DB::table($table)->whereNull('cloud_tenant_id')->count();
        $line['ambiguous'] = (int) DB::table($table)
            ->whereNotNull('cloud_tenant_id')
            ->where('cloud_tenant_id', '!=', $tenant->id)
            ->count();
        $line['would_assign'] = $line['unowned'];

        if ($execute && $line['would_assign'] > 0) {
            DB::table($table)->whereNull('cloud_tenant_id')->update([
                'cloud_tenant_id' => $tenant->id,
            ]);
            $line['already_owned'] += $line['would_assign'];
            $line['unowned'] = 0;
        }

        return $line;
    }

    protected function countTable($table)
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        return (int) DB::table($table)->count();
    }

    public function relationships()
    {
        $checks = [
            ['sales', 'customer_id', 'customers', 'id'],
            ['quotations', 'customer_id', 'customers', 'id'],
            ['bookings', 'customer_id', 'customers', 'id'],
            ['product_sales', 'sale_id', 'sales', 'id'],
            ['product_quotation', 'quotation_id', 'quotations', 'id'],
            ['booking_products', 'booking_id', 'bookings', 'id'],
            ['whatsapp_messages', 'conversation_id', 'whatsapp_conversations', 'id'],
        ];
        $out = [];
        foreach ($checks as $check) {
            $out[] = $this->orphanCount($check[0], $check[1], $check[2], $check[3]);
        }

        return $out;
    }

    protected function orphanCount($child, $childKey, $parent, $parentKey)
    {
        $row = [
            'child' => $child,
            'parent' => $parent,
            'orphans' => null,
            'note' => '',
        ];
        if (! Schema::hasTable($child) || ! Schema::hasTable($parent)) {
            $row['note'] = 'table missing';

            return $row;
        }
        if (! Schema::hasColumn($child, $childKey) || ! Schema::hasColumn($parent, $parentKey)) {
            $row['note'] = 'column missing';

            return $row;
        }

        $row['orphans'] = (int) DB::table($child.' as child')
            ->whereNotNull('child.'.$childKey)
            ->whereNotExists(function ($query) use ($parent, $parentKey, $childKey) {
                $query->select(DB::raw(1))
                    ->from($parent.' as parent')
                    ->whereColumn('parent.'.$parentKey, 'child.'.$childKey);
            })
            ->count();

        return $row;
    }

    public function seedTestTenant()
    {
        if ($this->liveDatabase()) {
            throw new \RuntimeException('Refusing to create a test company on the live database.');
        }

        $tenant = CloudTenant::where('slug', 'phase-1c-test-customer')->first();
        if (! $tenant) {
            $tenant = CloudTenant::create([
                'name' => 'Phase 1C Test Customer',
                'slug' => 'phase-1c-test-customer',
                'type' => CloudTenantType::CUSTOMER,
                'status' => CloudTenantStatus::ACTIVE,
                'currency' => 'XAF',
                'timezone' => 'Africa/Douala',
            ]);
        }

        $productId = $this->insertTestProduct($tenant->id);
        $customerId = $this->insertTestCustomer($tenant->id);

        return [
            'tenant_id' => $tenant->id,
            'product_id' => $productId,
            'customer_id' => $customerId,
        ];
    }

    protected function insertTestProduct($tenantId)
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'cloud_tenant_id')) {
            return null;
        }
        $existing = DB::table('products')->where('name', 'Test Tenant Product')->value('id');
        if ($existing) {
            return (int) $existing;
        }

        $row = [
            'name' => 'Test Tenant Product',
            'cloud_tenant_id' => $tenantId,
        ];
        if (Schema::hasColumn('products', 'code')) {
            $row['code'] = 'P1C-TEST-PRODUCT';
        }
        if (Schema::hasColumn('products', 'type')) {
            $row['type'] = 'standard';
        }
        if (Schema::hasColumn('products', 'barcode_symbology')) {
            $row['barcode_symbology'] = 'C128';
        }
        foreach (['category_id', 'unit_id', 'purchase_unit_id', 'sale_unit_id'] as $column) {
            if (Schema::hasColumn('products', $column)) {
                $row[$column] = $this->firstId($this->sourceTable($column)) ?: 1;
            }
        }
        if (Schema::hasColumn('products', 'cost')) {
            $row['cost'] = '0';
        }
        if (Schema::hasColumn('products', 'price')) {
            $row['price'] = '0';
        }
        if (Schema::hasColumn('products', 'is_active')) {
            $row['is_active'] = 0;
        }
        if (Schema::hasColumn('products', 'created_at')) {
            $row['created_at'] = now();
            $row['updated_at'] = now();
        }

        return (int) DB::table('products')->insertGetId($row);
    }

    protected function insertTestCustomer($tenantId)
    {
        if (! Schema::hasTable('customers') || ! Schema::hasColumn('customers', 'cloud_tenant_id')) {
            return null;
        }
        $existing = DB::table('customers')->where('name', 'Test Tenant Customer')->value('id');
        if ($existing) {
            return (int) $existing;
        }

        $row = [
            'name' => 'Test Tenant Customer',
            'cloud_tenant_id' => $tenantId,
        ];
        if (Schema::hasColumn('customers', 'customer_group_id')) {
            $row['customer_group_id'] = $this->firstId('customer_groups') ?: 1;
        }
        if (Schema::hasColumn('customers', 'phone_number')) {
            $row['phone_number'] = '00000000000';
        }
        if (Schema::hasColumn('customers', 'address')) {
            $row['address'] = 'Test address';
        }
        if (Schema::hasColumn('customers', 'city')) {
            $row['city'] = 'Test';
        }
        if (Schema::hasColumn('customers', 'is_active')) {
            $row['is_active'] = 0;
        }
        if (Schema::hasColumn('customers', 'created_at')) {
            $row['created_at'] = now();
            $row['updated_at'] = now();
        }

        return (int) DB::table('customers')->insertGetId($row);
    }

    protected function sourceTable($column)
    {
        if ($column === 'category_id') {
            return 'categories';
        }

        return 'units';
    }

    protected function firstId($table)
    {
        if (! Schema::hasTable($table)) {
            return null;
        }

        return DB::table($table)->orderBy('id')->value('id');
    }
}
