<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Cloud\CloudMembershipRole;
use App\Cloud\CloudMembershipStatus;
use App\Cloud\CloudSubscriptionStatus;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantMembership;
use App\Cloud\CloudTenantStatus;
use App\Cloud\CloudTenantType;
use App\Cloud\CloudModule;
use App\Cloud\CloudPlan;
use App\Cloud\CloudSubscription;
use App\Services\Cloud\CloudSlug;
use Faker\Generator as Faker;

$factory->define(CloudTenant::class, function (Faker $faker) {
    $name = $faker->company;

    return [
        'name' => $name,
        'slug' => CloudSlug::fromName($name).'-'.$faker->unique()->numerify('###'),
        'type' => CloudTenantType::CUSTOMER,
        'status' => CloudTenantStatus::PENDING,
        'currency' => 'XAF',
        'timezone' => 'Africa/Douala',
    ];
});

$factory->define(CloudTenantMembership::class, function () {
    return [
        'membership_role' => CloudMembershipRole::STAFF,
        'is_owner' => false,
        'status' => CloudMembershipStatus::ACTIVE,
        'joined_at' => now(),
    ];
});

$factory->define(CloudModule::class, function (Faker $faker) {
    return [
        'code' => 'MODULE_'.$faker->unique()->numerify('###'),
        'name' => $faker->words(2, true),
        'description' => $faker->sentence,
        'active' => true,
        'sort_order' => 10,
    ];
});

$factory->define(CloudPlan::class, function (Faker $faker) {
    return [
        'code' => 'PLAN_'.$faker->unique()->numerify('###'),
        'name' => $faker->words(2, true),
        'billing_interval' => 'MONTH',
        'price' => '1000.00',
        'currency' => 'XAF',
        'trial_value' => 24,
        'trial_unit' => 'HOUR',
        'active' => true,
        'sort_order' => 10,
    ];
});

$factory->define(CloudSubscription::class, function () {
    return [
        'status' => CloudSubscriptionStatus::TRIALING,
    ];
});
