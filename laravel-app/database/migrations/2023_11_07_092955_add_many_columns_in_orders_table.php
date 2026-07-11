<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddManyColumnsInOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
//            $table->string('subject')->nullable();
//            $table->string('project_title')->nullable();
//            $table->string('project_guide_lines')->nullable();
//            $table->string('citation_sytle')->nullable();
//            $table->string('font_style')->nullable();
//            $table->string('language')->nullable();
//            $table->string('sample_doc')->nullable();
//            $table->string('citation_style')->nullable();
//            $table->integer('references')->nullable();
//            $table->tinyInteger('quality_double_checker')->default(0);
//            $table->tinyInteger('abstract_page')->default(0);
//            $table->tinyInteger('one_page_summary')->default(0);
//            $table->tinyInteger('grammar_checker')->default(0);
//            $table->tinyInteger('preferred_expert')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            //
        });
    }
}
