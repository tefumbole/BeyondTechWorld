<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DedupePermissionsAndEnsureInternRole extends Migration
{
    public function up()
    {
        $this->dedupePermissions();
        $this->ensureInternRole();
        $this->flushPermissionCache();
    }

    public function down()
    {
        // Duplicates are not restored; only the guard rail is removed.
        try {
            Schema::table('permissions', function (Blueprint $table) {
                $table->dropUnique('permissions_name_guard_name_unique');
            });
        } catch (\Exception $e) {
        }
    }

    /**
     * Spatie lookups resolve a name to a single row, so duplicate rows leave
     * roles half-linked: granting or revoking hits one row and the menu checks
     * the other.
     */
    protected function dedupePermissions()
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $groups = DB::table('permissions')
            ->select('name', 'guard_name', DB::raw('MIN(id) as keep_id'))
            ->groupBy('name', 'guard_name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $extraIds = DB::table('permissions')
                ->where('name', $group->name)
                ->where('guard_name', $group->guard_name)
                ->where('id', '<>', $group->keep_id)
                ->pluck('id')
                ->all();

            if (empty($extraIds)) {
                continue;
            }

            $this->repointPivot('role_has_permissions', 'role_id', $extraIds, (int) $group->keep_id);
            $this->repointModelPivot($extraIds, (int) $group->keep_id);

            DB::table('permissions')->whereIn('id', $extraIds)->delete();
        }

        try {
            Schema::table('permissions', function (Blueprint $table) {
                $table->unique(['name', 'guard_name'], 'permissions_name_guard_name_unique');
            });
        } catch (\Exception $e) {
        }
    }

    protected function repointPivot($table, $ownerColumn, array $extraIds, $keepId)
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $rows = DB::table($table)->whereIn('permission_id', $extraIds)->get();
        foreach ($rows as $row) {
            $owner = $row->{$ownerColumn};
            $exists = DB::table($table)
                ->where('permission_id', $keepId)
                ->where($ownerColumn, $owner)
                ->exists();
            if (! $exists) {
                DB::table($table)->insert([
                    'permission_id' => $keepId,
                    $ownerColumn => $owner,
                ]);
            }
        }

        DB::table($table)->whereIn('permission_id', $extraIds)->delete();
    }

    protected function repointModelPivot(array $extraIds, $keepId)
    {
        $table = 'model_has_permissions';
        if (! Schema::hasTable($table)) {
            return;
        }

        $rows = DB::table($table)->whereIn('permission_id', $extraIds)->get();
        foreach ($rows as $row) {
            $exists = DB::table($table)
                ->where('permission_id', $keepId)
                ->where('model_type', $row->model_type)
                ->where('model_id', $row->model_id)
                ->exists();
            if (! $exists) {
                DB::table($table)->insert([
                    'permission_id' => $keepId,
                    'model_type' => $row->model_type,
                    'model_id' => $row->model_id,
                ]);
            }
        }

        DB::table($table)->whereIn('permission_id', $extraIds)->delete();
    }

    /**
     * Interns need the employee timesheet screens plus their student portal.
     */
    protected function ensureInternRole()
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $permissions = [
            'timesheets_module',
            'timesheets.employee',
            'internship_module',
            'internship.dashboard.view',
            'internship.student',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role = Role::where('name', 'Intern')->where('guard_name', 'web')->first();
        if (! $role) {
            $role = $this->createInternRole();
        }
        if (! $role) {
            return;
        }

        foreach ($permissions as $name) {
            try {
                if (! $role->hasPermissionTo($name)) {
                    $role->givePermissionTo($name);
                }
            } catch (\Exception $e) {
            }
        }
    }

    protected function createInternRole()
    {
        try {
            return Role::create(['name' => 'Intern', 'guard_name' => 'web', 'is_active' => 1]);
        } catch (\Exception $e) {
        }

        try {
            DB::table('roles')->insert([
                'name' => 'Intern',
                'guard_name' => 'web',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            return null;
        }

        return Role::where('name', 'Intern')->where('guard_name', 'web')->first();
    }

    protected function flushPermissionCache()
    {
        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Exception $e) {
        }
    }
}
