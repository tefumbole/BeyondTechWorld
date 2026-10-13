<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNbcMemberProfile extends Migration
{
    public function up()
    {
        $schema = Schema::connection('nbc');
        if ($schema->hasTable('nbc_members') && ! $schema->hasColumn('nbc_members', 'profile')) {
            $schema->table('nbc_members', function (Blueprint $table) {
                $table->longText('profile')->nullable();
            });
        }
    }

    public function down()
    {
        $schema = Schema::connection('nbc');
        if ($schema->hasTable('nbc_members') && $schema->hasColumn('nbc_members', 'profile')) {
            $schema->table('nbc_members', function (Blueprint $table) {
                $table->dropColumn('profile');
            });
        }
    }
}
