<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGbhsTestimonials extends Migration
{
    public function up()
    {
        if (Schema::hasTable('gbhs_testimonials')) {
            return;
        }
        Schema::create('gbhs_testimonials', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable()->index();
            $table->unsignedInteger('customer_id')->nullable();
            $table->string('name');
            $table->string('phone', 32);
            $table->text('body');
            $table->string('signature_path')->nullable();
            $table->string('selfie_path')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('gbhs_testimonials');
    }
}
