<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeliveredSignatureToGoodsReceipts extends Migration
{
    public function up()
    {
        Schema::table('booking_goods_receipts', function (Blueprint $table) {
            $table->timestamp('delivered_signed_at')->nullable()->after('signature_image');
            $table->longText('delivered_signature_image')->nullable()->after('delivered_signed_at');
            $table->string('delivered_by_name')->nullable()->after('delivered_signature_image');
            $table->timestamp('delivered_signature_sent_at')->nullable()->after('signature_sent_at');
        });
    }

    public function down()
    {
        Schema::table('booking_goods_receipts', function (Blueprint $table) {
            $table->dropColumn([
                'delivered_signed_at',
                'delivered_signature_image',
                'delivered_by_name',
                'delivered_signature_sent_at',
            ]);
        });
    }
}
