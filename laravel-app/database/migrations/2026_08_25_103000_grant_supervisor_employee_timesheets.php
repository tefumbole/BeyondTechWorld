<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class GrantSupervisorEmployeeTimesheets extends Migration
{
    protected $permissions = [
        'timesheets_module',
        'timesheets.employee',
    ];

    public function up()
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        foreach ($this->permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role = Role::where('name', 'Internship Supervisor')->where('guard_name', 'web')->first();
        if (! $role) {
            return;
        }

        foreach ($this->permissions as $name) {
            try {
                if (! $role->hasPermissionTo($name)) {
                    $role->givePermissionTo($name);
                }
            } catch (\Exception $e) {
            }
        }

        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Exception $e) {
        }
    }

    public function down()
    {
        $role = Role::where('name', 'Internship Supervisor')->where('guard_name', 'web')->first();
        if (! $role) {
            return;
        }

        foreach ($this->permissions as $name) {
            try {
                $role->revokePermissionTo($name);
            } catch (\Exception $e) {
            }
        }
    }
}
