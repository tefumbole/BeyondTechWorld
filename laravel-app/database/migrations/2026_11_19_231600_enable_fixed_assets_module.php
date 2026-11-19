<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnableFixedAssetsModule extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $names = [
            'fixed_assets',
            'fixed_assets_report',
            'asset-index',
            'asset-add',
            'asset-edit',
            'asset-delete',
            'asset-type-index',
            'asset-type-add',
            'asset-type-edit',
            'asset-type-delete',
            'asset-expense-index',
            'asset-expense-add',
            'asset-expense-edit',
            'asset-expense-delete',
            'activity-index',
            'activity-add',
            'activity-edit',
            'activity-delete',
            'asset-sale',
            'asset-transfer',
            'asset-disppose',
            'donor-index',
            'donor-add',
            'donor-edit',
            'donor-delete',
            'region-index',
            'region-add',
            'region-edit',
            'region-delete',
            'station-index',
            'station-add',
            'station-edit',
            'station-delete',
        ];

        $now = date('Y-m-d H:i:s');
        $permissionIds = [];
        foreach ($names as $name) {
            $permission = DB::table('permissions')
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->orderBy('id')
                ->first();
            if (! $permission) {
                $permissionIds[] = DB::table('permissions')->insertGetId([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                continue;
            }
            $permissionIds[] = (int) $permission->id;
        }

        $roles = DB::table('roles')
            ->where('guard_name', 'web')
            ->whereIn('name', ['Admin', 'Owner', 'Manager', 'Fixed Assets'])
            ->get();

        foreach ($roles as $role) {
            foreach ($permissionIds as $permissionId) {
                $linked = DB::table('role_has_permissions')
                    ->where('role_id', $role->id)
                    ->where('permission_id', $permissionId)
                    ->exists();
                if (! $linked) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $permissionId,
                        'role_id' => $role->id,
                    ]);
                }
            }
        }

        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Exception $e) {
        }
    }

    public function down()
    {
    }
}
