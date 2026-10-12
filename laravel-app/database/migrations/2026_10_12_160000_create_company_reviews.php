<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompanyReviews extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('company_review_invites')) {
            Schema::create('company_review_invites', function (Blueprint $table) {
                $table->increments('id');
                $table->string('token', 64)->unique();
                $table->string('phone', 30);
                $table->string('person_name', 191)->nullable();
                $table->string('source', 40);
                $table->unsignedInteger('source_id')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
                $table->unique(['source', 'source_id']);
            });
        }

        if (! Schema::hasTable('company_reviews')) {
            Schema::create('company_reviews', function (Blueprint $table) {
                $table->increments('id');
                $table->string('google_sub', 64)->unique();
                $table->string('google_email', 191);
                $table->string('reviewer_name', 191);
                $table->string('google_photo', 500)->nullable();
                $table->string('country', 80);
                $table->unsignedTinyInteger('rating');
                $table->text('comment');
                $table->string('photo_path', 255)->nullable();
                $table->string('signature_path', 255)->nullable();
                $table->unsignedInteger('invite_id')->nullable();
                $table->boolean('published')->default(false);
                $table->boolean('hidden_by_staff')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('company_reviews');
        Schema::dropIfExists('company_review_invites');
    }
}
