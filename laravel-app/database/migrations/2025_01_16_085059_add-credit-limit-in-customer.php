<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCreditLimitInCustomer extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->integer('credit_limit')->nullable();
        });
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->integer('credit_limit')->nullable();
        });
        Schema::table('sales', function (Blueprint $table) {
            $table->integer('paid_by_id')->nullable();
            $table->integer('customer_group_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('credit_limit');
        });
    }
}
