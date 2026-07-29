<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AddDigitalInvitationsPermissions extends Migration
{
    public function up()
    {
        $perms = [
            'invitations_module',
            'invitations.view',
            'invitations.create',
            'invitations.edit',
            'invitations.delete',
            'invitations.check_in',
        ];

        foreach ($perms as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        $admin = Role::where('name', 'Admin')->first();
        if ($admin) {
            foreach ($perms as $name) {
                if (! $admin->hasPermissionTo($name)) {
                    $admin->givePermissionTo($name);
                }
            }
        }
    }

    public function down()
    {
        foreach ([
            'invitations_module',
            'invitations.view',
            'invitations.create',
            'invitations.edit',
            'invitations.delete',
            'invitations.check_in',
        ] as $name) {
            $permission = Permission::where('name', $name)->first();
            if ($permission) {
                $permission->delete();
            }
        }
    }
}
