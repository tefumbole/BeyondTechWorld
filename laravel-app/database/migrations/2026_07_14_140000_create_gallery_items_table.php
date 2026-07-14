<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGalleryItemsTable extends Migration
{
    public function up()
    {
        Schema::create('gallery_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('type', 32);
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('media_url', 2048)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('gallery_items');
    }
}
