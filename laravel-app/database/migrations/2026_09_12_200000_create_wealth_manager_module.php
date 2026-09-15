<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateWealthManagerModule extends Migration
{
    public function up()
    {
        $this->createBuckets();
        $this->createRules();
        $this->createIncome();
        $this->createExpenseExtras();
        $this->createPrograms();
        $this->createRegisters();
        $this->alterExpenses();
        $this->seedDefaults();
        $this->seedPermissions();
        $this->backfillIncome();
    }

    public function down()
    {
        $columns = [
            'allocation_bucket_id', 'wealth_subcategory_id', 'program_id', 'biller_id',
            'employee_id', 'entity_type', 'payee', 'payment_method', 'document', 'title',
            'created_by', 'updated_by',
        ];
        if (Schema::hasTable('expenses')) {
            Schema::table('expenses', function (Blueprint $table) use ($columns) {
                foreach ($columns as $col) {
                    if (Schema::hasColumn('expenses', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        Schema::dropIfExists('wealth_allocations');
        Schema::dropIfExists('wealth_charity_transactions');
        Schema::dropIfExists('wealth_investments');
        Schema::dropIfExists('wealth_program_budget_lines');
        Schema::dropIfExists('wealth_programs');
        Schema::dropIfExists('wealth_income');
        Schema::dropIfExists('wealth_income_categories');
        Schema::dropIfExists('wealth_expense_subcategories');
        Schema::dropIfExists('wealth_allocation_rule_lines');
        Schema::dropIfExists('wealth_allocation_rules');
        Schema::dropIfExists('wealth_allocation_buckets');
        Schema::dropIfExists('wealth_health_settings');
    }

    protected function createBuckets()
    {
        if (! Schema::hasTable('wealth_allocation_buckets')) {
            Schema::create('wealth_allocation_buckets', function (Blueprint $table) {
                $table->increments('id');
                $table->string('code', 32)->unique();
                $table->string('name');
                $table->string('label', 64)->nullable();
                $table->unsignedTinyInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    protected function createRules()
    {
        if (! Schema::hasTable('wealth_allocation_rules')) {
            Schema::create('wealth_allocation_rules', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('biller_id')->nullable()->index();
                $table->string('basis', 16)->default('gross');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('wealth_allocation_rule_lines')) {
            Schema::create('wealth_allocation_rule_lines', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('rule_id')->index();
                $table->unsignedInteger('bucket_id')->index();
                $table->decimal('percent', 5, 2);
                $table->timestamps();
                $table->unique(['rule_id', 'bucket_id']);
            });
        }
        if (! Schema::hasTable('wealth_health_settings')) {
            Schema::create('wealth_health_settings', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('biller_id')->nullable()->index();
                $table->unsignedTinyInteger('critical_max')->default(39);
                $table->unsignedTinyInteger('attention_max')->default(59);
                $table->unsignedTinyInteger('fair_max')->default(74);
                $table->unsignedTinyInteger('very_good_max')->default(89);
                $table->unsignedTinyInteger('weight_income_expense')->default(25);
                $table->unsignedTinyInteger('weight_cash')->default(20);
                $table->unsignedTinyInteger('weight_operations')->default(15);
                $table->unsignedTinyInteger('weight_investment')->default(15);
                $table->unsignedTinyInteger('weight_charity')->default(10);
                $table->unsignedTinyInteger('weight_savings')->default(10);
                $table->unsignedTinyInteger('weight_budget')->default(5);
                $table->unsignedTinyInteger('ops_over_warn_pct')->default(90);
                $table->unsignedTinyInteger('budget_warn_pct')->default(85);
                $table->unsignedTinyInteger('cash_low_pct')->default(10);
                $table->timestamps();
            });
        }
    }

    protected function createIncome()
    {
        if (! Schema::hasTable('wealth_income_categories')) {
            Schema::create('wealth_income_categories', function (Blueprint $table) {
                $table->increments('id');
                $table->string('code', 64)->unique();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('wealth_expense_subcategories')) {
            Schema::create('wealth_expense_subcategories', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('bucket_id')->index();
                $table->string('code', 64);
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['bucket_id', 'code']);
            });
        }
        if (! Schema::hasTable('wealth_income')) {
            Schema::create('wealth_income', function (Blueprint $table) {
                $table->increments('id');
                $table->string('source_type', 24)->index();
                $table->unsignedInteger('source_id')->nullable();
                $table->dateTime('occurred_at')->index();
                $table->decimal('amount', 15, 2);
                $table->string('currency', 8)->default('XAF');
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->unsignedInteger('biller_id')->nullable()->index();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->unsignedInteger('employee_id')->nullable()->index();
                $table->unsignedInteger('customer_id')->nullable()->index();
                $table->unsignedInteger('program_id')->nullable()->index();
                $table->unsignedInteger('income_category_id')->nullable()->index();
                $table->string('payment_method', 64)->nullable();
                $table->string('reference', 128)->nullable();
                $table->string('attachment')->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 16)->default('posted')->index();
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique(['source_type', 'source_id'], 'wealth_income_source_unique');
            });
        }
    }

    protected function createExpenseExtras()
    {
        if (Schema::hasTable('expense_categories') && ! Schema::hasColumn('expense_categories', 'allocation_bucket_id')) {
            Schema::table('expense_categories', function (Blueprint $table) {
                $table->unsignedInteger('allocation_bucket_id')->nullable()->index()->after('is_active');
                $table->unsignedInteger('wealth_subcategory_id')->nullable()->index()->after('allocation_bucket_id');
            });
        }
    }

    protected function createPrograms()
    {
        if (! Schema::hasTable('wealth_programs')) {
            Schema::create('wealth_programs', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('code', 64)->index();
                $table->unsignedInteger('biller_id')->nullable()->index();
                $table->unsignedInteger('manager_user_id')->nullable()->index();
                $table->text('description')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->decimal('proposed_budget', 15, 2)->default(0);
                $table->decimal('expected_income', 15, 2)->default(0);
                $table->string('currency', 8)->default('XAF');
                $table->string('status', 24)->default('planning')->index();
                $table->string('cover_image')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('wealth_program_budget_lines')) {
            Schema::create('wealth_program_budget_lines', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('program_id')->index();
                $table->string('name');
                $table->decimal('budget_amount', 15, 2)->default(0);
                $table->unsignedInteger('subcategory_id')->nullable()->index();
                $table->unsignedTinyInteger('warn_percent')->default(85);
                $table->unsignedTinyInteger('critical_percent')->default(100);
                $table->timestamps();
            });
        }
    }

    protected function createRegisters()
    {
        if (! Schema::hasTable('wealth_investments')) {
            Schema::create('wealth_investments', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('type', 64)->nullable();
                $table->decimal('amount_invested', 15, 2)->default(0);
                $table->date('invested_on')->nullable()->index();
                $table->unsignedInteger('biller_id')->nullable()->index();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->unsignedInteger('program_id')->nullable()->index();
                $table->unsignedInteger('expense_id')->nullable()->index();
                $table->text('description')->nullable();
                $table->decimal('current_value', 15, 2)->nullable();
                $table->decimal('expected_return', 15, 2)->nullable();
                $table->string('status', 24)->default('active')->index();
                $table->string('document')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('wealth_charity_transactions')) {
            Schema::create('wealth_charity_transactions', function (Blueprint $table) {
                $table->increments('id');
                $table->string('beneficiary');
                $table->unsignedInteger('subcategory_id')->nullable()->index();
                $table->decimal('amount', 15, 2)->default(0);
                $table->date('given_on')->nullable()->index();
                $table->unsignedInteger('biller_id')->nullable()->index();
                $table->unsignedInteger('user_id')->nullable()->index();
                $table->unsignedInteger('program_id')->nullable()->index();
                $table->unsignedInteger('expense_id')->nullable()->index();
                $table->text('description')->nullable();
                $table->string('payment_method', 64)->nullable();
                $table->string('document')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('wealth_allocations')) {
            Schema::create('wealth_allocations', function (Blueprint $table) {
                $table->increments('id');
                $table->string('period_key', 191)->index();
                $table->string('entity_type', 24)->default('all')->index();
                $table->unsignedInteger('entity_id')->nullable()->index();
                $table->unsignedInteger('bucket_id')->index();
                $table->decimal('expected_amount', 15, 2)->default(0);
                $table->decimal('used_amount', 15, 2)->default(0);
                $table->decimal('remaining_amount', 15, 2)->default(0);
                $table->timestamps();
                $table->unique(['period_key', 'entity_type', 'entity_id', 'bucket_id'], 'wealth_alloc_unique');
            });
        }
    }

    protected function alterExpenses()
    {
        if (! Schema::hasTable('expenses')) {
            return;
        }
        $columns = [
            'allocation_bucket_id' => function (Blueprint $table) {
                $table->unsignedInteger('allocation_bucket_id')->nullable()->index();
            },
            'wealth_subcategory_id' => function (Blueprint $table) {
                $table->unsignedInteger('wealth_subcategory_id')->nullable()->index();
            },
            'program_id' => function (Blueprint $table) {
                $table->unsignedInteger('program_id')->nullable()->index();
            },
            'biller_id' => function (Blueprint $table) {
                $table->unsignedInteger('biller_id')->nullable()->index();
            },
            'employee_id' => function (Blueprint $table) {
                $table->unsignedInteger('employee_id')->nullable()->index();
            },
            'entity_type' => function (Blueprint $table) {
                $table->string('entity_type', 24)->nullable()->index();
            },
            'payee' => function (Blueprint $table) {
                $table->string('payee')->nullable();
            },
            'payment_method' => function (Blueprint $table) {
                $table->string('payment_method', 64)->nullable();
            },
            'document' => function (Blueprint $table) {
                $table->string('document')->nullable();
            },
            'title' => function (Blueprint $table) {
                $table->string('title')->nullable();
            },
            'created_by' => function (Blueprint $table) {
                $table->unsignedInteger('created_by')->nullable();
            },
            'updated_by' => function (Blueprint $table) {
                $table->unsignedInteger('updated_by')->nullable();
            },
        ];
        foreach ($columns as $name => $define) {
            if (! Schema::hasColumn('expenses', $name)) {
                Schema::table('expenses', $define);
            }
        }
    }

    protected function seedDefaults()
    {
        $now = date('Y-m-d H:i:s');
        $buckets = [
            ['code' => 'OPERATIONS', 'name' => 'Operations', 'label' => '70% Operations', 'sort_order' => 1],
            ['code' => 'INVESTMENT', 'name' => 'Investment', 'label' => '20% Investment', 'sort_order' => 2],
            ['code' => 'CHARITY', 'name' => 'Charity', 'label' => '10% Charity', 'sort_order' => 3],
        ];
        foreach ($buckets as $b) {
            $exists = DB::table('wealth_allocation_buckets')->where('code', $b['code'])->first();
            if (! $exists) {
                DB::table('wealth_allocation_buckets')->insert($b + ['is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $ops = DB::table('wealth_allocation_buckets')->where('code', 'OPERATIONS')->value('id');
        $inv = DB::table('wealth_allocation_buckets')->where('code', 'INVESTMENT')->value('id');
        $cha = DB::table('wealth_allocation_buckets')->where('code', 'CHARITY')->value('id');

        $ruleId = DB::table('wealth_allocation_rules')->whereNull('biller_id')->where('is_active', 1)->value('id');
        if (! $ruleId) {
            $ruleId = DB::table('wealth_allocation_rules')->insertGetId([
                'biller_id' => null, 'basis' => 'gross', 'is_active' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $percents = [$ops => '70.00', $inv => '20.00', $cha => '10.00'];
        foreach ($percents as $bucketId => $pct) {
            if (! $bucketId) {
                continue;
            }
            $line = DB::table('wealth_allocation_rule_lines')->where('rule_id', $ruleId)->where('bucket_id', $bucketId)->first();
            if (! $line) {
                DB::table('wealth_allocation_rule_lines')->insert([
                    'rule_id' => $ruleId, 'bucket_id' => $bucketId, 'percent' => $pct,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        if (! DB::table('wealth_health_settings')->whereNull('biller_id')->first()) {
            DB::table('wealth_health_settings')->insert([
                'biller_id' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $incomeCats = [
            ['code' => 'SALES', 'name' => 'Sales'],
            ['code' => 'POS', 'name' => 'POS'],
            ['code' => 'SERVICE', 'name' => 'Service'],
            ['code' => 'RENTAL', 'name' => 'Rental'],
            ['code' => 'PROGRAM', 'name' => 'Program'],
            ['code' => 'OTHER', 'name' => 'Other'],
        ];
        foreach ($incomeCats as $c) {
            if (! DB::table('wealth_income_categories')->where('code', $c['code'])->first()) {
                DB::table('wealth_income_categories')->insert($c + ['is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $subs = [
            'OPERATIONS' => ['Salary', 'Fuel', 'Transport', 'Rent', 'Utilities', 'Food', 'Personal', 'Business operations', 'Repairs', 'Equipment maintenance', 'Production', 'Logistics', 'Marketing', 'Other'],
            'INVESTMENT' => ['Equipment Investment', 'Property', 'Vehicle', 'Savings', 'Business Expansion', 'Technology', 'Stocks/Securities', 'New Business', 'Capital Expenditure', 'Other Investment'],
            'CHARITY' => ['Church', 'Donations', 'Medical Support', 'School Support', 'Community Support', 'Family Assistance', 'Foundation', 'Benevolence', 'Other Charity'],
        ];
        $bucketIds = ['OPERATIONS' => $ops, 'INVESTMENT' => $inv, 'CHARITY' => $cha];
        foreach ($subs as $code => $names) {
            $bid = $bucketIds[$code];
            if (! $bid) {
                continue;
            }
            foreach ($names as $name) {
                $slug = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($name)));
                if (! DB::table('wealth_expense_subcategories')->where('bucket_id', $bid)->where('code', $slug)->first()) {
                    DB::table('wealth_expense_subcategories')->insert([
                        'bucket_id' => $bid, 'code' => $slug, 'name' => $name, 'is_active' => 1,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    protected function seedPermissions()
    {
        $names = [
            'wealth.view', 'wealth.manage',
            'wealth.income.view', 'wealth.income.create', 'wealth.income.edit',
            'wealth.expenses.view', 'wealth.expenses.create', 'wealth.expenses.edit',
            'wealth.programs.view', 'wealth.programs.manage',
            'wealth.investments.view', 'wealth.investments.manage',
            'wealth.charity.view', 'wealth.charity.manage',
            'wealth.reports.view', 'wealth.settings.manage',
        ];
        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        foreach ([1, 2] as $roleId) {
            $role = Role::find($roleId);
            if (! $role) {
                continue;
            }
            foreach ($names as $name) {
                if (! $role->hasPermissionTo($name)) {
                    $role->givePermissionTo($name);
                }
            }
        }
    }

    protected function backfillIncome()
    {
        if (! Schema::hasTable('sales') || ! Schema::hasTable('wealth_income')) {
            return;
        }
        $currency = 'XAF';
        if (Schema::hasTable('general_settings')) {
            $curId = DB::table('general_settings')->orderBy('id')->value('currency');
            if ($curId && Schema::hasTable('currencies')) {
                $code = DB::table('currencies')->where('id', $curId)->value('code');
                if ($code) {
                    $currency = $code;
                }
            }
        }
        $sales = DB::table('sales')
            ->where('sale_status', 1)
            ->where('payment_status', 4)
            ->where('paid_amount', '>', 0)
            ->orderBy('id');
        $now = date('Y-m-d H:i:s');
        $sales->chunk(100, function ($chunk) use ($currency, $now) {
            foreach ($chunk as $sale) {
                $exists = DB::table('wealth_income')->where('source_type', $sale->cash_register_id ? 'pos' : 'sale')->where('source_id', $sale->id)->first();
                if ($exists) {
                    continue;
                }
                if (DB::table('wealth_income')->where('source_id', $sale->id)->whereIn('source_type', ['sale', 'pos'])->first()) {
                    continue;
                }
                $isPos = ! empty($sale->cash_register_id);
                DB::table('wealth_income')->insert([
                    'source_type' => $isPos ? 'pos' : 'sale',
                    'source_id' => $sale->id,
                    'occurred_at' => $sale->created_at ?: $now,
                    'amount' => number_format((float) $sale->paid_amount, 2, '.', ''),
                    'currency' => $currency,
                    'title' => 'Sale '.$sale->reference_no,
                    'description' => $sale->sale_note,
                    'biller_id' => $sale->biller_id,
                    'user_id' => $sale->user_id,
                    'customer_id' => $sale->customer_id,
                    'payment_method' => null,
                    'reference' => $sale->reference_no,
                    'status' => 'posted',
                    'created_by' => $sale->user_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }
}
