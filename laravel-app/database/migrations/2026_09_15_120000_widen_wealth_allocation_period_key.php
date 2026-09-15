<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class WidenWealthAllocationPeriodKey extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('wealth_allocations') || ! Schema::hasColumn('wealth_allocations', 'period_key')) {
            return;
        }
        Schema::table('wealth_allocations', function (Blueprint $table) {
            $table->string('period_key', 191)->change();
        });
    }

    public function down()
    {
        if (! Schema::hasTable('wealth_allocations') || ! Schema::hasColumn('wealth_allocations', 'period_key')) {
            return;
        }
        Schema::table('wealth_allocations', function (Blueprint $table) {
            $table->string('period_key', 32)->change();
        });
    }
}
