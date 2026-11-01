<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateManagerRole extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $role = DB::table('roles')->where('name', 'Manager')->where('guard_name', 'web')->first();
        if (! $role) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'Manager',
                'guard_name' => 'web',
                'is_active' => 1,
                'description' => 'Dashboard, POS, sales, quotations, products, events, internship, supervisor, and people. Login requires a WhatsApp code.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $roleId = (int) $role->id;
            DB::table('roles')->where('id', $roleId)->update([
                'is_active' => 1,
                'description' => 'Dashboard, POS, sales, quotations, products, events, internship, supervisor, and people. Login requires a WhatsApp code.',
                'updated_at' => $now,
            ]);
        }

        $names = [
            'one_time_otp',
            'sales-index',
            'sales-add',
            'quotes-index',
            'quotes-add',
            'products-index',
            'products-add',
            'category',
            'events_module',
            'events.view',
            'events.create',
            'events.update',
            'events.settings',
            'internship_module',
            'internship.dashboard.view',
            'internship.programs.view',
            'internship.enrolments.view',
            'internship.enrolments.create',
            'internship.supervise',
            'internship.submissions.view',
            'internship.submissions.grade',
            'internship.tasks.view',
            'users-index',
            'customers-index',
            'customers-add',
            'billers-index',
        ];

        foreach ($names as $name) {
            $permission = DB::table('permissions')
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->orderBy('id')
                ->first();
            if (! $permission) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $permissionId = (int) $permission->id;
            }
            $linked = DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->exists();
            if (! $linked) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        $needles = [
            'amah willort kebeng',
            'kebung gracious',
            'kebeng gracious',
            'tanni lee',
            'amah willort',
        ];
        $users = DB::table('users')
            ->where(function ($query) {
                $query->where('is_deleted', 0)->orWhereNull('is_deleted');
            })
            ->get(['id', 'name']);
        foreach ($users as $user) {
            $name = strtolower(trim(preg_replace('/\s+/', ' ', (string) $user->name)));
            foreach ($needles as $needle) {
                if ($name === $needle || strpos($name, $needle) !== false) {
                    DB::table('users')->where('id', $user->id)->update([
                        'role_id' => $roleId,
                        'updated_at' => $now,
                    ]);
                    break;
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
