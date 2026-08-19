<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuotationQuotesTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('quotation_quotes')) {
            Schema::create('quotation_quotes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('quotation_id');
                $table->string('mode', 20); // overall|lines
                $table->decimal('proposed_grand_total', 15, 2)->nullable();
                $table->decimal('original_grand_total', 15, 2)->nullable();
                $table->text('client_note')->nullable();
                $table->string('status', 20)->default('pending'); // pending|accepted|rejected
                $table->text('admin_note')->nullable();
                $table->unsignedInteger('admin_user_id')->nullable();
                $table->timestamps();

                $table->index('quotation_id');
                $table->index('status');
            });
        }

        if (! Schema::hasTable('quotation_quote_lines')) {
            Schema::create('quotation_quote_lines', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('quotation_quote_id');
                $table->unsignedInteger('product_quotation_id');
                $table->decimal('original_net_unit_price', 15, 2)->default(0);
                $table->decimal('original_total', 15, 2)->default(0);
                $table->decimal('proposed_net_unit_price', 15, 2)->default(0);
                $table->decimal('proposed_total', 15, 2)->default(0);
                $table->timestamps();

                $table->index('quotation_quote_id');
                $table->index('product_quotation_id');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('quotation_quote_lines');
        Schema::dropIfExists('quotation_quotes');
    }
}
