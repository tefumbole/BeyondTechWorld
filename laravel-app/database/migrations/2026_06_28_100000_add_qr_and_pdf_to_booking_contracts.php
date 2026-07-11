<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddQrAndPdfToBookingContracts extends Migration
{
    public function up()
    {
        Schema::table('booking_contracts', function (Blueprint $table) {
            $table->string('qr_token', 64)->nullable()->unique()->after('signature_token');
            $table->string('signed_pdf_path')->nullable()->after('id_card_path');
        });
    }

    public function down()
    {
        Schema::table('booking_contracts', function (Blueprint $table) {
            $table->dropColumn(['qr_token', 'signed_pdf_path']);
        });
    }
}
