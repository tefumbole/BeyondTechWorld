<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRentalProductAndReturnNotificationFields extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'requires_quantity')) {
                $table->boolean('requires_quantity')->default(true)->after('type');
            }
        });

        Schema::table('booking_products', function (Blueprint $table) {
            if (!Schema::hasColumn('booking_products', 'return_reminder_sent_at')) {
                $table->timestamp('return_reminder_sent_at')->nullable()->after('is_notified');
            }
            if (!Schema::hasColumn('booking_products', 'late_notice_sent_at')) {
                $table->timestamp('late_notice_sent_at')->nullable()->after('return_reminder_sent_at');
            }
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'requires_quantity')) {
                $table->dropColumn('requires_quantity');
            }
        });

        Schema::table('booking_products', function (Blueprint $table) {
            if (Schema::hasColumn('booking_products', 'return_reminder_sent_at')) {
                $table->dropColumn('return_reminder_sent_at');
            }
            if (Schema::hasColumn('booking_products', 'late_notice_sent_at')) {
                $table->dropColumn('late_notice_sent_at');
            }
        });
    }
}
