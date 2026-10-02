<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only ownership check. It does not update rows.
 */
class AuditCloudOwnership extends Command
{
    protected $signature = 'cloud:audit-ownership';

    protected $description = 'Report unowned rows and cross-company links. Does not write.';

    public function handle()
    {
        $tables = [
            'products', 'categories', 'brands', 'units', 'warehouses', 'customers', 'suppliers',
            'sales', 'quotations', 'payments', 'bookings',
            'whatsapp_contacts', 'whatsapp_conversations',
            'cloud_sms_connections', 'cloud_sms_accounts', 'cloud_messaging_notifications',
            'cloud_messaging_attempts', 'cloud_sms_messages', 'cloud_sms_ledger',
        ];
        $unowned = 0;
        $invalid = 0;
        foreach ($tables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'cloud_tenant_id')) {
                $this->line($table.'  skipped');
                continue;
            }
            $total = (int) DB::table($table)->count();
            $nulls = (int) DB::table($table)->whereNull('cloud_tenant_id')->count();
            $bad = 0;
            if (Schema::hasTable('cloud_tenants')) {
                $bad = (int) DB::table($table)
                    ->whereNotNull($table.'.cloud_tenant_id')
                    ->whereNotExists(function ($query) use ($table) {
                        $query->select(DB::raw(1))
                            ->from('cloud_tenants')
                            ->whereColumn('cloud_tenants.id', $table.'.cloud_tenant_id');
                    })
                    ->count();
            }
            $unowned += $nulls;
            $invalid += $bad;
            $this->line($table.'  total='.$total.'  unowned='.$nulls.'  invalid_tenant='.$bad);
        }
        $links = $this->linkViolations();
        $this->line('cross_tenant_links='.$links);
        $this->line('unowned_total='.$unowned);
        $this->line('invalid_tenant_total='.$invalid);

        return 0;
    }

    protected function linkViolations()
    {
        $pairs = [
            ['sales', 'customer_id', 'customers'],
            ['quotations', 'customer_id', 'customers'],
            ['bookings', 'customer_id', 'customers'],
            ['payments', 'sale_id', 'sales'],
            ['whatsapp_conversations', 'contact_id', 'whatsapp_contacts'],
        ];
        $count = 0;
        foreach ($pairs as $pair) {
            list($child, $fk, $parent) = $pair;
            if (! Schema::hasTable($child) || ! Schema::hasTable($parent)) {
                continue;
            }
            if (! Schema::hasColumn($child, 'cloud_tenant_id') || ! Schema::hasColumn($parent, 'cloud_tenant_id') || ! Schema::hasColumn($child, $fk)) {
                continue;
            }
            $count += (int) DB::table($child.' as child')
                ->join($parent.' as parent', 'parent.id', '=', 'child.'.$fk)
                ->whereNotNull('child.cloud_tenant_id')
                ->whereNotNull('parent.cloud_tenant_id')
                ->whereColumn('child.cloud_tenant_id', '!=', 'parent.cloud_tenant_id')
                ->count();
        }

        return $count;
    }
}
