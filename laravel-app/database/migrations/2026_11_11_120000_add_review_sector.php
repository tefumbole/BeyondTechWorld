<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddReviewSector extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('company_reviews')) {
            return;
        }

        Schema::table('company_reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('company_reviews', 'sector')) {
                $table->string('sector', 32)->nullable();
            }
            if (! Schema::hasColumn('company_reviews', 'sector_other')) {
                $table->string('sector_other', 191)->nullable();
            }
        });

        if (Schema::hasColumn('company_reviews', 'rating_value')) {
            DB::table('company_reviews')
                ->whereNotNull('rating_value')
                ->where('rating', '<=', 5)
                ->update(['rating' => DB::raw('rating * 2')]);
        }
    }

    public function down()
    {
        if (! Schema::hasTable('company_reviews')) {
            return;
        }

        Schema::table('company_reviews', function (Blueprint $table) {
            if (Schema::hasColumn('company_reviews', 'sector_other')) {
                $table->dropColumn('sector_other');
            }
            if (Schema::hasColumn('company_reviews', 'sector')) {
                $table->dropColumn('sector');
            }
        });
    }
}
