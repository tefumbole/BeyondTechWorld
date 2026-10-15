<?php

use App\User;
use App\WhatsApp\WhatsAppOwnerUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AuthorizeOwnerWhatsappAi extends Migration
{
    public function up()
    {
        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            $perm = Permission::firstOrCreate(['name' => 'whatsapp.owner', 'guard_name' => 'web']);
            $manage = Permission::firstOrCreate(['name' => 'whatsapp.ai.manage', 'guard_name' => 'web']);
            foreach (Role::whereIn('id', [1, 2])->get() as $role) {
                try {
                    $role->givePermissionTo([$perm, $manage]);
                } catch (\Exception $e) {
                }
            }
        }

        $owner = $this->businessUser();
        if ($owner && Schema::hasTable('whatsapp_owner_users')) {
            $phone = '250794006160';
            WhatsAppOwnerUser::updateOrCreate(
                ['user_id' => $owner->id, 'normalized_phone' => $phone],
                ['enabled' => true]
            );
            $extra = preg_replace('/\D/', '', (string) $owner->additional_phone);
            if ($extra === '') {
                $owner->additional_phone = $phone;
                $owner->save();
            }
        }

        if (Schema::hasTable('whatsapp_settings')) {
            app(\App\Services\WhatsApp\OwnerCommandService::class)->setAssistantEnabled(true, $owner ? $owner->id : null);
        }
    }

    public function down()
    {
    }

    protected function businessUser()
    {
        if (! Schema::hasTable('users')) {
            return null;
        }
        $needles = ['237675321739', '675321739', '+237675321739'];
        $user = User::query()
            ->where(function ($rows) {
                $rows->where('is_deleted', false)->orWhereNull('is_deleted');
            })
            ->where(function ($rows) use ($needles) {
                $rows->whereIn('phone', $needles)->orWhereIn('additional_phone', $needles);
            })
            ->orderBy('id')
            ->first();
        if ($user) {
            return $user;
        }

        return User::query()
            ->where('role_id', 1)
            ->where(function ($rows) {
                $rows->where('is_active', true)->orWhereNull('is_active');
            })
            ->orderBy('id')
            ->first();
    }
}
