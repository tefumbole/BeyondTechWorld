<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GrantManagerPriceChange extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $role = DB::table('roles')->where('name', 'Manager')->where('guard_name', 'web')->first();
        if (! $role) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $permission = DB::table('permissions')
            ->where('name', 'price-change')
            ->where('guard_name', 'web')
            ->orderBy('id')
            ->first();
        if (! $permission) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => 'price-change',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $permissionId = (int) $permission->id;
        }

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

        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Exception $e) {
        }
    }

    public function down()
    {
    }
}
