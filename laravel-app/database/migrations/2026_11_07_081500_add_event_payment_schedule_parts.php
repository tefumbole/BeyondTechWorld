<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEventPaymentScheduleParts extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('event_auto_payments')) {
            return;
        }
        Schema::table('event_auto_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('event_auto_payments', 'assignment_id')) {
                $table->string('assignment_id', 36)->nullable()->after('event_id');
            }
            if (! Schema::hasColumn('event_auto_payments', 'amount')) {
                $table->unsignedInteger('amount')->nullable()->after('assignment_id');
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('event_auto_payments')) {
            return;
        }
        Schema::table('event_auto_payments', function (Blueprint $table) {
            if (Schema::hasColumn('event_auto_payments', 'amount')) {
                $table->dropColumn('amount');
            }
            if (Schema::hasColumn('event_auto_payments', 'assignment_id')) {
                $table->dropColumn('assignment_id');
            }
        });
    }
}
