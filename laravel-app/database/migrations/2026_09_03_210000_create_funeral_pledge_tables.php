<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateFuneralPledgeTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('funeral_campaigns')) {
            Schema::create('funeral_campaigns', function (Blueprint $table) {
                $table->increments('id');
                $table->string('slug', 80)->unique();
                $table->string('title');
                $table->string('honoree_name');
                $table->unsignedTinyInteger('age')->nullable();
                $table->dateTime('funeral_at');
                $table->unsignedInteger('target_amount')->default(0);
                $table->string('admin_name')->nullable();
                $table->string('admin_phone', 32)->nullable();
                $table->boolean('enabled')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('funeral_items')) {
            Schema::create('funeral_items', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('campaign_id');
                $table->string('category', 80);
                $table->string('name');
                $table->unsignedInteger('target_amount')->nullable();
                $table->boolean('is_open')->default(false);
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();
                $table->index(['campaign_id', 'sort']);
            });
        }

        if (! Schema::hasTable('funeral_pledges')) {
            Schema::create('funeral_pledges', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('campaign_id');
                $table->unsignedInteger('item_id');
                $table->unsignedInteger('customer_id')->nullable();
                $table->string('name');
                $table->string('phone', 32);
                $table->unsignedInteger('amount');
                $table->string('kind', 20)->default('pledge');
                $table->string('status', 20)->default('pledged');
                $table->string('campay_reference', 80)->nullable();
                $table->dateTime('paid_at')->nullable();
                $table->timestamps();
                $table->index(['item_id', 'status']);
                $table->index(['campaign_id', 'status']);
            });
        }

        $this->seedCampaign();
    }

    public function down()
    {
        Schema::dropIfExists('funeral_pledges');
        Schema::dropIfExists('funeral_items');
        Schema::dropIfExists('funeral_campaigns');
    }

    protected function seedCampaign()
    {
        if (DB::table('funeral_campaigns')->where('slug', 'pangwayu')->exists()) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $campaignId = DB::table('funeral_campaigns')->insertGetId([
            'slug' => 'pangwayu',
            'title' => 'Funeral of Pa Ngwayu Francis',
            'honoree_name' => 'Pa Ngwayu Francis',
            'age' => 73,
            'funeral_at' => '2026-09-26 00:00:00',
            'target_amount' => 3101000,
            'admin_name' => 'Pa Ngwayu Richard',
            'admin_phone' => '237677318405',
            'enabled' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sort = 0;
        $rows = [];
        foreach ($this->budgetItems() as $row) {
            $sort++;
            $rows[] = [
                'campaign_id' => $campaignId,
                'category' => $row[0],
                'name' => $row[1],
                'target_amount' => $row[2],
                'is_open' => $row[3] ? 1 : 0,
                'sort' => $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('funeral_items')->insert($rows);
    }

    protected function budgetItems()
    {
        return [
            ['food', 'Church', 450000, false],
            ['food', 'Military honours', 150000, false],
            ['food', 'Cow', 450000, false],
            ['food', 'Palm oil', 30000, false],
            ['food', 'Vegetable oil', 30000, false],
            ['food', 'Rice', 25000, false],
            ['food', 'Tomatoes (4 baskets)', 20000, false],
            ['food', 'Huckleberry', 20000, false],
            ['food', 'Bitter leaf', 10000, false],
            ['food', 'Onion', 6000, false],
            ['food', 'Eru', 10000, false],
            ['food', 'Crayfish', 2000, false],
            ['food', 'Dry fish', 2000, false],
            ['food', 'Drinks', 155000, false],
            ['food', 'Fish', 60000, false],
            ['food', 'Achu', 10000, false],
            ['food', 'Chicken', 150000, false],
            ['food', 'Water leaf', null, true],
            ['food', 'Species', 5000, false],
            ['food', 'Salt', 4000, false],
            ['takeaway', 'Eggs (120)', 10000, false],
            ['takeaway', 'Flour', 11000, false],
            ['takeaway', 'Accessories', 15000, false],
            ['logistics', 'Dressing (white shirt / tie)', null, true],
            ['logistics', 'Mortuary fee', 400000, false],
            ['logistics', 'Casket / burial', 100000, false],
            ['logistics', 'Tents', 140000, false],
            ['logistics', 'Transportation', 75000, false],
            ['logistics', 'Sound system (Roland)', 50000, false],
            ['logistics', 'Roll-ups (2)', 50000, false],
            ['logistics', 'Enlargement (2)', 50000, false],
            ['logistics', 'Chairs', 50000, false],
            ['logistics', 'Printing of the program', 50000, false],
            ['logistics', 'Tables', 30000, false],
            ['logistics', 'Decoration', 30000, false],
            ['logistics', 'Tissue paper', 5000, false],
            ['logistics', 'Plates', 20000, false],
            ['logistics', 'Spoons', 5000, false],
            ['logistics', 'Wood', 30000, false],
            ['logistics', 'Contingency', 200000, false],
            ['logistics', 'Visit to palace & quarter', 200000, false],
            ['other', 'Other', null, true],
        ];
    }
}
