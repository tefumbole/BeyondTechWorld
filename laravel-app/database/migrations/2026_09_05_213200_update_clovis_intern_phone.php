<?php

use App\Support\WhatsAppPhone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UpdateClovisInternPhone extends Migration
{
    public function up()
    {
        $new = WhatsAppPhone::sanitizeForStorage('+237 6 82 79 42 29');

        if (Schema::hasTable('applications')) {
            DB::table('applications')
                ->where(function ($q) {
                    $q->where('email', 'nkwaincloviz@gmail.com')
                        ->orWhere('full_name', 'like', '%Kwain Clovis%');
                })
                ->update([
                    'phone' => $new,
                    'whatsapp_number' => $new,
                ]);
        }

        if (Schema::hasTable('users')) {
            DB::table('users')
                ->where(function ($q) {
                    $q->where('email', 'nkwaincloviz@gmail.com')
                        ->orWhere('phone', '237675321739')
                        ->orWhere('phone', '675321739');
                })
                ->where(function ($q) {
                    $q->where('email', 'nkwaincloviz@gmail.com')
                        ->orWhere('name', 'like', '%Clovis%');
                })
                ->update(['phone' => $new]);
        }
    }

    public function down()
    {
        // Keep the corrected number.
    }
}
