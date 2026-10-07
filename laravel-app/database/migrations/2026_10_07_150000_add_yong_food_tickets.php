<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddYongFoodTickets extends Migration
{
    public function up()
    {
        if (Schema::hasTable('yong_invitations') && ! Schema::hasColumn('yong_invitations', 'ticket_code')) {
            Schema::table('yong_invitations', function (Blueprint $table) {
                $table->string('ticket_code', 20)->nullable()->unique();
                $table->string('food_file', 191)->nullable();
                $table->timestamp('eaten_at')->nullable();
                $table->timestamp('attended_at')->nullable();
                $table->timestamp('thanked_at')->nullable();
            });
        }

        if (! Schema::hasTable('yong_reviews')) {
            Schema::create('yong_reviews', function (Blueprint $table) {
                $table->increments('id');
                $table->string('invitation_id', 40)->nullable();
                $table->string('name', 120);
                $table->unsignedTinyInteger('rating');
                $table->text('comment')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('yong_gallery')) {
            Schema::create('yong_gallery', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 120)->nullable();
                $table->string('image_file', 191);
                $table->string('caption', 180)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('yong_gallery');
        Schema::dropIfExists('yong_reviews');
    }
}
