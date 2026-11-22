<?php

use App\User;
use App\WhatsApp\WhatsAppOwnerUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class AuthorizeTranslatorCommandPhone extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('whatsapp_owner_users') || ! Schema::hasTable('users')) {
            return;
        }

        $userId = null;
        $existing = WhatsAppOwnerUser::where('enabled', true)->orderBy('id')->first();
        if ($existing) {
            $userId = (int) $existing->user_id;
        }
        if (! $userId) {
            $user = User::query()
                ->where('role_id', 1)
                ->where(function ($rows) {
                    $rows->where('is_deleted', false)->orWhereNull('is_deleted');
                })
                ->orderBy('id')
                ->first();
            $userId = $user ? (int) $user->id : 0;
        }
        if (! $userId) {
            return;
        }

        WhatsAppOwnerUser::updateOrCreate(
            ['user_id' => $userId, 'normalized_phone' => '237671553091'],
            ['enabled' => true]
        );
    }

    public function down()
    {
        if (! Schema::hasTable('whatsapp_owner_users')) {
            return;
        }
        WhatsAppOwnerUser::where('normalized_phone', '237671553091')->delete();
    }
}
