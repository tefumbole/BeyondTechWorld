<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddRestaurantAndBarRoles extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('roles')) {
            return;
        }

        $this->ensure('Restaurant', 'Kitchen. Food orders only.');
        $this->ensure('Bar', 'Bar. Drink orders only.');
    }

    public function down()
    {
    }

    protected function ensure($name, $description)
    {
        $now = date('Y-m-d H:i:s');
        $existing = DB::table('roles')->where('name', $name)->first();
        if ($existing) {
            DB::table('roles')->where('id', $existing->id)->update([
                'description' => $description,
                'is_active' => 1,
                'updated_at' => $now,
            ]);

            return;
        }

        $row = [
            'name' => $name,
            'description' => $description,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        if (Schema::hasColumn('roles', 'guard_name')) {
            $row['guard_name'] = 'web';
        }
        DB::table('roles')->insert($row);
    }
}
