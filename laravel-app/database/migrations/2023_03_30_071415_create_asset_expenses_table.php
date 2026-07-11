<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAssetExpensesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('asset_expenses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('reference_no');
            $table->integer('expense_category_id')->nullable();
            $table->integer('asset_id');
            $table->integer('account_id')->nullable();
            $table->integer('user_id');

//            automobile
            $table->integer('start_km')->nullable();
            $table->integer('end_km')->nullable();
            $table->integer('total_km')->nullable();
            $table->string('approved')->nullable();
            $table->text('reason_for_trip')->nullable();

//            photocopies
            $table->integer('num_of_photocopies')->nullable();

            $table->double('amount')->nullable();
            $table->text('note')->nullable();
            $table->date('date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('asset_expenses');
    }
}
