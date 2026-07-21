<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddShowClientDiscountToQuotations extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('quotations', 'show_client_discount')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->boolean('show_client_discount')->default(true)->after('order_discount');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('quotations', 'show_client_discount')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->dropColumn('show_client_discount');
            });
        }
    }
}
