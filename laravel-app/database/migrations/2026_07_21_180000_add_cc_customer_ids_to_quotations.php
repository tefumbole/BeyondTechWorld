<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCcCustomerIdsToQuotations extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('quotations')) {
            return;
        }

        Schema::table('quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('quotations', 'cc_customer_ids')) {
                $table->string('cc_customer_ids', 500)->nullable()->after('customer_id');
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('quotations') || ! Schema::hasColumn('quotations', 'cc_customer_ids')) {
            return;
        }

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('cc_customer_ids');
        });
    }
}
