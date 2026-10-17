<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLetterOrganizations extends Migration
{
    public function up()
    {
        Schema::create('letter_organizations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('reference_prefix', 40);
            $table->string('logo')->nullable();
            $table->string('header')->nullable();
            $table->string('footer')->nullable();
            $table->timestamps();
        });

        Schema::table('letters', function (Blueprint $table) {
            $table->unsignedInteger('organization_id')->nullable()->index()->after('reference');
        });
    }

    public function down()
    {
        Schema::table('letters', function (Blueprint $table) {
            $table->dropColumn('organization_id');
        });
        Schema::dropIfExists('letter_organizations');
    }
}
