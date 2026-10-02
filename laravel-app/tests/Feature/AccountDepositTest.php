<?php

namespace Tests\Feature;

use App\Account;
use App\Deposit;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountDepositTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->integer('role_id')->default(1);
            $table->boolean('is_active')->default(1);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('account_no')->nullable();
            $table->string('name');
            $table->decimal('initial_balance', 12, 2)->default(0);
            $table->decimal('total_balance', 12, 2)->default(0);
            $table->boolean('is_active')->default(1);
            $table->boolean('is_default')->default(0);
            $table->timestamps();
        });
        Schema::create('deposits', function (Blueprint $table) {
            $table->increments('id');
            $table->double('amount');
            $table->integer('customer_id')->nullable();
            $table->integer('user_id')->nullable();
            $table->text('note')->nullable();
            $table->string('payment_reference')->nullable();
            $table->integer('payment_method')->nullable();
            $table->integer('status')->nullable();
            $table->integer('depositor_id')->nullable();
            $table->integer('customer_group_id')->nullable();
            $table->timestamps();
        });
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_10_02_120000_add_account_id_to_deposits.php',
            '--force' => true,
        ]);
        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedInteger('permission_id');
            $table->unsignedInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
        DB::table('permissions')->insert([
            'id' => 1, 'name' => 'payments-index', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('roles')->insert([
            'id' => 1, 'name' => 'Admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('role_has_permissions')->insert(['permission_id' => 1, 'role_id' => 1]);
    }

    public function test_a_deposit_is_saved_on_the_chosen_account()
    {
        $admin = User::create([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'password' => Hash::make('secret-pass'),
            'role_id' => 1,
            'is_active' => 1,
        ]);
        $account = Account::create([
            'account_no' => 'CASH-01',
            'name' => 'Cash',
            'initial_balance' => 1000,
            'total_balance' => 1000,
            'is_active' => 1,
        ]);

        $this->actingAs($admin)->post('/payment/desposits', [
            'account_id' => $account->id,
            'amount' => 2500,
            'payment_method' => 1,
            'note' => 'Morning cash',
        ])->assertRedirect('/payment/desposits');

        $deposit = Deposit::first();
        $this->assertNotNull($deposit);
        $this->assertEquals($account->id, (int) $deposit->account_id);
        $this->assertEquals(2500, (float) $deposit->amount);
        $this->assertSame('Morning cash', $deposit->note);
        $this->assertEquals(3500, (float) $account->fresh()->total_balance);
    }
}
