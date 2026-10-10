<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCampayFieldsToDeposits extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('deposits')) {
            return;
        }
        if (! Schema::hasColumn('deposits', 'campay_reference')) {
            Schema::table('deposits', function (Blueprint $table) {
                $table->string('campay_reference')->nullable();
            });
        }
        if (! Schema::hasColumn('deposits', 'payment_link')) {
            Schema::table('deposits', function (Blueprint $table) {
                $table->text('payment_link')->nullable();
            });
        }
        if (! Schema::hasColumn('deposits', 'paid_notice')) {
            Schema::table('deposits', function (Blueprint $table) {
                $table->boolean('paid_notice')->default(0);
            });
        }
    }

    public function down()
    {
        if (! Schema::hasTable('deposits')) {
            return;
        }
        if (Schema::hasColumn('deposits', 'campay_reference')) {
            Schema::table('deposits', function (Blueprint $table) {
                $table->dropColumn('campay_reference');
            });
        }
        if (Schema::hasColumn('deposits', 'payment_link')) {
            Schema::table('deposits', function (Blueprint $table) {
                $table->dropColumn('payment_link');
            });
        }
        if (Schema::hasColumn('deposits', 'paid_notice')) {
            Schema::table('deposits', function (Blueprint $table) {
                $table->dropColumn('paid_notice');
            });
        }
    }
}
