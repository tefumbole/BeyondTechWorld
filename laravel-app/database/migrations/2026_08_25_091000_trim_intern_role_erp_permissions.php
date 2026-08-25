<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class TrimInternRoleErpPermissions extends Migration
{
    /**
     * Interns only need their student portal and timesheets; the original seed
     * also gave them POS, product and booking access.
     */
    protected $revoke = [
        'products-index',
        'sales-index',
        'sales-add',
        'booking_module',
        'booking_create',
    ];

    public function up()
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $role = Role::where('name', 'Intern')->where('guard_name', 'web')->first();
        if (! $role) {
            return;
        }

        foreach ($this->revoke as $name) {
            try {
                if ($role->hasPermissionTo($name)) {
                    $role->revokePermissionTo($name);
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
        $role = Role::where('name', 'Intern')->where('guard_name', 'web')->first();
        if (! $role) {
            return;
        }

        foreach ($this->revoke as $name) {
            try {
                $role->givePermissionTo($name);
            } catch (\Exception $e) {
            }
        }
    }
}
