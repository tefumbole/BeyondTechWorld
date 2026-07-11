<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReviewFieldsToBookingContracts extends Migration
{
    public function up()
    {
        Schema::table('booking_contracts', function (Blueprint $table) {
            $table->string('review_status', 32)->default('pending_client')->after('signed_at');
            $table->longText('admin_signature_image')->nullable()->after('signature_image');
            $table->timestamp('admin_signed_at')->nullable()->after('admin_signature_image');
            $table->unsignedInteger('admin_signed_by')->nullable()->after('admin_signed_at');
            $table->timestamp('approved_at')->nullable()->after('admin_signed_by');
        });

        \DB::table('booking_contracts')
            ->whereNotNull('signed_at')
            ->update([
                'review_status' => 'approved',
                'approved_at' => \DB::raw('signed_at'),
            ]);
    }

    public function down()
    {
        Schema::table('booking_contracts', function (Blueprint $table) {
            $table->dropColumn([
                'review_status',
                'admin_signature_image',
                'admin_signed_at',
                'admin_signed_by',
                'approved_at',
            ]);
        });
    }
}
