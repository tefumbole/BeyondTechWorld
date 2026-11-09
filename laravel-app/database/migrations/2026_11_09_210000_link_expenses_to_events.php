<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class LinkExpensesToEvents extends Migration
{
    public function up()
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'event_id')) {
                $table->string('event_id', 64)->nullable();
            }
            if (! Schema::hasColumn('expenses', 'quotation_id')) {
                $table->unsignedInteger('quotation_id')->nullable();
            }
            if (! Schema::hasColumn('expenses', 'payment_id')) {
                $table->unsignedInteger('payment_id')->nullable();
            }
        });
        Schema::table('expense_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('expense_categories', 'budget')) {
                $table->decimal('budget', 14, 2)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('expenses', function (Blueprint $table) {
            foreach (['event_id', 'quotation_id', 'payment_id'] as $column) {
                if (Schema::hasColumn('expenses', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::table('expense_categories', function (Blueprint $table) {
            if (Schema::hasColumn('expense_categories', 'budget')) {
                $table->dropColumn('budget');
            }
        });
    }
}
