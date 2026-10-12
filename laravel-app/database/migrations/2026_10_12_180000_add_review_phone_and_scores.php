<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddReviewPhoneAndScores extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('company_reviews')) {
            return;
        }

        Schema::table('company_reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('company_reviews', 'phone')) {
                $table->string('phone', 30)->nullable()->index();
            }
            if (! Schema::hasColumn('company_reviews', 'rating_value')) {
                $table->unsignedTinyInteger('rating_value')->nullable();
            }
            if (! Schema::hasColumn('company_reviews', 'rating_quality')) {
                $table->unsignedTinyInteger('rating_quality')->nullable();
            }
            if (! Schema::hasColumn('company_reviews', 'rating_esthetics')) {
                $table->unsignedTinyInteger('rating_esthetics')->nullable();
            }
            if (! Schema::hasColumn('company_reviews', 'rating_professionalism')) {
                $table->unsignedTinyInteger('rating_professionalism')->nullable();
            }
            if (! Schema::hasColumn('company_reviews', 'rating_time')) {
                $table->unsignedTinyInteger('rating_time')->nullable();
            }
        });

        DB::statement('ALTER TABLE company_reviews MODIFY google_sub VARCHAR(64) NULL');
        DB::statement('ALTER TABLE company_reviews MODIFY google_email VARCHAR(191) NULL');

        try {
            Schema::table('company_reviews', function (Blueprint $table) {
                $table->dropUnique(['google_sub']);
            });
        } catch (\Throwable $e) {
        }
    }

    public function down()
    {
        Schema::table('company_reviews', function (Blueprint $table) {
            foreach (['phone', 'rating_value', 'rating_quality', 'rating_esthetics', 'rating_professionalism', 'rating_time'] as $column) {
                if (Schema::hasColumn('company_reviews', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
