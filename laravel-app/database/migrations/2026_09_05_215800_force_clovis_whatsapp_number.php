<?php

use App\Support\WhatsAppPhone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ForceClovisWhatsappNumber extends Migration
{
    public function up()
    {
        $new = WhatsAppPhone::sanitizeForStorage('+237 6 82 79 42 29');
        $old = ['237675321739', '675321739', '+237675321739'];

        if (Schema::hasTable('applications')) {
            DB::table('applications')
                ->where(function ($q) {
                    $q->where('email', 'nkwaincloviz@gmail.com')
                        ->orWhere('full_name', 'Kwain Clovis Bin Afubum');
                })
                ->update([
                    'phone' => $new,
                    'whatsapp_number' => $new,
                ]);
        }

        if (Schema::hasTable('users')) {
            $rows = DB::table('users')
                ->where('is_deleted', 0)
                ->where(function ($q) {
                    $q->where('email', 'nkwaincloviz@gmail.com')
                        ->orWhere('name', 'Kwain Clovis Bin Afubum');
                })
                ->get();

            foreach ($rows as $row) {
                $extra = [
                    'phone' => $new,
                ];
                if (in_array((string) $row->additional_phone, $old, true)) {
                    $extra['additional_phone'] = null;
                }
                $usernameDigits = preg_replace('/\D+/', '', (string) $row->username);
                if (in_array($usernameDigits, ['237675321739', '675321739'], true)) {
                    $extra['username'] = $new;
                }
                DB::table('users')->where('id', $row->id)->update($extra);
            }
        }

        if (Schema::hasTable('customers')) {
            DB::table('customers')
                ->where(function ($q) {
                    $q->where('email', 'nkwaincloviz@gmail.com')
                        ->orWhere('name', 'Kwain Clovis Bin Afubum');
                })
                ->update(['phone_number' => $new]);
        }
    }

    public function down()
    {
        // Keep the corrected intern number.
    }
}
