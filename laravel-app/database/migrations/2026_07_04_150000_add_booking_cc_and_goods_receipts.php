<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AddBookingCcAndGoodsReceipts extends Migration
{
    public function up()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('cc_customer_ids')->nullable()->after('customer_id');
        });

        Schema::create('booking_goods_receipts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('booking_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('reference_no', 64);
            $table->string('signature_token', 64)->unique();
            $table->timestamp('signed_at')->nullable();
            $table->longText('signature_image')->nullable();
            $table->string('delivery_note_pdf_path')->nullable();
            $table->string('signed_pdf_path')->nullable();
            $table->timestamp('signature_sent_at')->nullable();
            $table->timestamps();
        });

        Permission::firstOrCreate(['name' => 'booking_goods_received', 'guard_name' => 'web']);

        $adminRoles = Role::whereIn('id', [1, 2])->get();
        $permission = Permission::where('name', 'booking_goods_received')->first();

        foreach ($adminRoles as $role) {
            if ($permission && !$role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('booking_goods_receipts');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('cc_customer_ids');
        });

        Permission::where('name', 'booking_goods_received')->delete();
    }
}
