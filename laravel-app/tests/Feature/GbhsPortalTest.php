<?php

namespace Tests\Feature;

use App\Cloud\CloudTenant;
use App\Customer;
use App\Gbhs\GbhsApplication;
use App\Gbhs\GbhsContribution;
use App\Gbhs\GbhsDelivery;
use App\Gbhs\GbhsDonate;
use App\Gbhs\GbhsMember;
use App\Gbhs\GbhsMembership;
use App\Gbhs\GbhsMessenger;
use App\Gbhs\GbhsPayment;
use App\Letter;
use App\Services\Cloud\CloudTenantContext;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GbhsPortalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->schema();
        $tenant = CloudTenant::find(1);
        app(CloudTenantContext::class)->set($tenant);
    }

    public function test_profile_is_refused_before_the_phone_is_verified()
    {
        $this->from('/gbhs/membership')
            ->post('/gbhs/register/profile', ['full_name' => 'Ada'])
            ->assertRedirect('/gbhs/membership')
            ->assertSessionHas('not_permitted');

        $this->assertSame(0, GbhsApplication::count());
    }

    public function test_approval_is_idempotent_and_keeps_the_pricing_group()
    {
        $customer = Customer::create([
            'customer_group_id' => 5,
            'name' => 'Ada',
            'phone_number' => '237670000001',
            'address' => 'Kumbo',
            'city' => 'Kumbo',
            'is_active' => 1,
        ]);
        \DB::table('customer_groups')->insert([
            'id' => 9,
            'name' => 'GBHS EX-students',
            'percentage' => '0',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $application = GbhsApplication::create([
            'reference' => 'GBHS-A-TEST',
            'customer_id' => $customer->id,
            'phone' => '237670000001',
            'phone_verified' => 1,
            'full_name' => 'Ada',
            'status' => 'submitted',
            'consent_announcements' => 0,
        ]);

        $first = GbhsMembership::approve($application, 1);
        $second = GbhsMembership::approve($application->fresh(), 1);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, GbhsMember::count());
        $this->assertSame(1, Letter::count());
        $this->assertSame(5, (int) $customer->fresh()->customer_group_id);
        $this->assertSame(1, \DB::table('customer_group_memberships')->where('customer_id', $customer->id)->where('customer_group_id', 9)->count());
    }

    public function test_a_success_return_does_not_mark_a_donation_paid()
    {
        $payment = GbhsPayment::create([
            'public_token' => 'token-1',
            'donor_name' => 'Ada',
            'amount' => 1000,
            'method' => 'card',
            'provider' => 'stripe',
            'provider_reference' => 'success',
            'status' => 'pending',
        ]);

        $this->assertSame('pending', GbhsDonate::confirm($payment));
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame(0, GbhsContribution::count());
    }

    public function test_a_letter_is_not_sent_twice_to_the_same_group()
    {
        \DB::table('letters')->insert([
            'id' => 7,
            'reference' => 'GBHS/L-/26/0000001',
            'name' => 'Ada',
            'to' => 'Ada',
            'subject' => 'Notice',
            'body' => 'Hello',
            'is_active' => 1,
            'created_by' => 1,
            'people_type' => 'customer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        GbhsDelivery::create([
            'letter_id' => 7,
            'target' => '120@g.us',
            'kind' => 'group',
            'status' => 'sent',
        ]);

        $this->assertSame(['121@g.us'], GbhsMessenger::plan(['120@g.us', '121@g.us', '120@g.us'], 7));
    }

    protected function schema()
    {
        Schema::dropAllTables();
        Schema::create('cloud_tenants', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->string('status')->nullable();
            $table->string('uuid')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
        });
        \DB::table('cloud_tenants')->insert([
            'id' => 1, 'name' => 'Beyond', 'slug' => 'beyond', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::create('customers', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->integer('customer_group_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone_number');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->boolean('is_active')->nullable();
            $table->timestamps();
        });
        Schema::create('customer_groups', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('percentage')->nullable();
            $table->boolean('is_active')->nullable();
            $table->timestamps();
        });
        Schema::create('customer_group_memberships', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('customer_id');
            $table->unsignedInteger('customer_group_id');
            $table->timestamps();
        });
        Schema::create('letters', function ($table) {
            $table->increments('id');
            $table->string('reference');
            $table->string('name');
            $table->text('to');
            $table->text('subject');
            $table->text('body')->nullable();
            $table->boolean('is_active')->default(1);
            $table->boolean('is_approve')->nullable();
            $table->boolean('is_sign')->nullable();
            $table->integer('created_by');
            $table->string('people_type')->nullable();
            $table->timestamps();
        });
        foreach ([
            'gbhs_settings', 'gbhs_applications', 'gbhs_members', 'gbhs_contributions',
            'gbhs_payments', 'gbhs_deliveries', 'gbhs_audit_logs',
        ] as $skipped) {
        }
        Schema::create('gbhs_settings', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->unsignedInteger('letter_organization_id')->nullable();
            $table->string('president_name')->nullable();
            $table->string('president_title')->nullable();
            $table->string('president_signature')->nullable();
            $table->timestamps();
        });
        Schema::create('gbhs_applications', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->string('reference');
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('batch_id')->nullable();
            $table->string('status')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('phone_verified')->default(false);
            $table->string('full_name')->nullable();
            $table->boolean('consent_announcements')->default(false);
            $table->text('review_note')->nullable();
            $table->unsignedInteger('reviewer_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('letter_id')->nullable();
            $table->unsignedInteger('member_id')->nullable();
            $table->timestamps();
        });
        Schema::create('gbhs_members', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->unsignedInteger('application_id')->nullable();
            $table->unsignedInteger('customer_id')->nullable();
            $table->unsignedInteger('batch_id')->nullable();
            $table->string('membership_number');
            $table->string('status')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
        Schema::create('gbhs_contributions', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->unsignedInteger('payment_id')->nullable();
            $table->string('contributor_name')->nullable();
            $table->string('classification');
            $table->integer('amount')->default(0);
            $table->boolean('verified')->default(false);
            $table->boolean('anonymous')->default(false);
            $table->timestamps();
        });
        Schema::create('gbhs_payments', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->string('public_token');
            $table->string('donor_name');
            $table->string('phone')->nullable();
            $table->integer('amount');
            $table->string('method');
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();
            $table->string('status');
            $table->string('error')->nullable();
            $table->boolean('anonymous')->default(false);
            $table->timestamps();
        });
        Schema::create('gbhs_deliveries', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->unsignedInteger('letter_id')->nullable();
            $table->string('target');
            $table->string('kind')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
        Schema::create('gbhs_audit_logs', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->nullable();
            $table->unsignedInteger('actor_id')->nullable();
            $table->string('action');
            $table->string('summary');
            $table->timestamps();
        });
    }
}
