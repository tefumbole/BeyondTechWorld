<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AddLetterSignaturesAndFeaturePermissions extends Migration
{
    public function up()
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->string('edit_signature')->nullable()->after('edit_by');
            $table->timestamp('edit_signed_at')->nullable()->after('edit_signature');
            $table->string('approve_signature')->nullable()->after('approved_by');
            $table->timestamp('approve_signed_at')->nullable()->after('approve_signature');
            $table->string('sign_signature')->nullable()->after('signed_by');
            $table->timestamp('sign_signed_at')->nullable()->after('sign_signature');
        });

        $newPermissions = [
            'booking_awaiting_signature',
            'booking_pending_review',
            'booking_signed_contracts',
            'booking_contract_approve',
            'env_setting',
        ];

        foreach ($newPermissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $adminRoles = Role::whereIn('id', [1, 2])->get();
        $allPermissions = Permission::all();

        foreach ($adminRoles as $role) {
            foreach ($allPermissions as $permission) {
                if (!$role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            }
        }
    }

    public function down()
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropColumn([
                'edit_signature',
                'edit_signed_at',
                'approve_signature',
                'approve_signed_at',
                'sign_signature',
                'sign_signed_at',
            ]);
        });

        $names = [
            'booking_awaiting_signature',
            'booking_pending_review',
            'booking_signed_contracts',
            'booking_contract_approve',
            'env_setting',
        ];

        foreach ($names as $name) {
            Permission::where('name', $name)->delete();
        }
    }
}
