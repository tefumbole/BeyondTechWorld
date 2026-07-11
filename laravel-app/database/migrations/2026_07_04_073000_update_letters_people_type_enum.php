<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class UpdateLettersPeopleTypeEnum extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE letters MODIFY people_type ENUM('user','customer','csv','all') NOT NULL DEFAULT 'user'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE letters MODIFY people_type ENUM('user','customer') NOT NULL DEFAULT 'user'");
    }
}
