<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateTravelModule extends Migration
{
    public function up()
    {
        Schema::create('travel_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id')->unique();
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });

        Schema::create('travel_stations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->string('name');
            $table->string('kind', 20)->default('terminal');
            $table->string('region', 40)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'name']);
        });

        Schema::create('travel_routes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->unsignedInteger('origin_station_id');
            $table->unsignedInteger('destination_station_id');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('duration_confirmed')->default(false);
            $table->boolean('enabled')->default(false);
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'origin_station_id', 'destination_station_id'], 'travel_routes_pair');
        });

        Schema::create('travel_route_stops', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->unsignedInteger('route_id');
            $table->unsignedInteger('station_id');
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->unique(['route_id', 'position']);
        });

        Schema::create('travel_vehicles', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->string('code');
            $table->string('class', 20);
            $table->unsignedSmallInteger('capacity');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['cloud_tenant_id', 'code']);
        });

        Schema::create('travel_drivers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('travel_departures', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->unsignedInteger('route_id');
            $table->unsignedInteger('origin_station_id');
            $table->unsignedInteger('destination_station_id');
            $table->unsignedInteger('vehicle_id');
            $table->unsignedInteger('driver_id');
            $table->string('class', 20);
            $table->string('shift', 20);
            $table->date('operating_date');
            $table->string('depart_local', 5);
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('via')->nullable();
            $table->dateTime('scheduled_at');
            $table->dateTime('estimated_departure_at')->nullable();
            $table->dateTime('actual_departure_at')->nullable();
            $table->dateTime('actual_arrival_at')->nullable();
            $table->dateTime('block_until');
            $table->string('status', 20)->default('scheduled');
            $table->unsignedInteger('fare_xaf');
            $table->unsignedSmallInteger('capacity');
            $table->string('delay_reason')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
            $table->index(['cloud_tenant_id', 'operating_date', 'status'], 'travel_departures_day');
        });

        Schema::create('travel_seats', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->unsignedInteger('departure_id');
            $table->unsignedSmallInteger('seat_no');
            $table->boolean('accessible')->default(false);
            $table->string('status', 20)->default('free');
            $table->unsignedInteger('booking_id')->nullable();
            $table->dateTime('hold_expires_at')->nullable();
            $table->timestamps();
            $table->unique(['departure_id', 'seat_no']);
        });

        Schema::create('travel_bookings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->unsignedInteger('departure_id');
            $table->unsignedInteger('seat_id')->nullable();
            $table->unsignedSmallInteger('requested_seat');
            $table->string('reference', 32)->unique();
            $table->string('passenger_name');
            $table->string('phone', 20);
            $table->string('payer_phone', 20)->nullable();
            $table->unsignedInteger('amount_xaf');
            $table->string('channel', 20);
            $table->string('payment_reference', 64)->nullable()->unique();
            $table->string('payment_status', 20)->default('pending');
            $table->string('ticket_status', 20)->default('none');
            $table->dateTime('boarded_at')->nullable();
            $table->unsignedInteger('sold_by')->nullable();
            $table->timestamps();
            $table->index(['cloud_tenant_id', 'phone']);
        });

        Schema::create('travel_payments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->unsignedInteger('booking_id')->nullable();
            $table->unsignedInteger('parcel_id')->nullable();
            $table->string('reference', 64)->unique();
            $table->unsignedInteger('amount_xaf');
            $table->string('currency', 3)->default('XAF');
            $table->string('status', 20)->default('pending');
            $table->string('channel', 20);
            $table->string('provider_reference')->nullable();
            $table->string('account_name')->nullable();
            $table->string('failure_reason')->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('travel_otps', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->string('phone', 20);
            $table->string('purpose', 80);
            $table->string('code_hash', 64);
            $table->dateTime('expires_at');
            $table->dateTime('consumed_at')->nullable();
            $table->timestamps();
            $table->index(['phone', 'purpose']);
        });

        Schema::create('travel_outbox', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->string('phone', 20);
            $table->text('body');
            $table->string('dedupe_key', 120)->unique();
            $table->string('status', 20)->default('pending');
            $table->string('provider', 20)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();
        });

        Schema::create('travel_parcels', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->string('tracking_ref', 40)->unique();
            $table->unsignedInteger('origin_station_id');
            $table->unsignedInteger('destination_station_id');
            $table->unsignedInteger('departure_id')->nullable();
            $table->string('sender_name');
            $table->string('sender_phone', 20);
            $table->string('receiver_name');
            $table->string('receiver_phone', 20);
            $table->string('description');
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedSmallInteger('package_count')->default(1);
            $table->unsignedInteger('fee_xaf');
            $table->string('tariff_source', 20)->default('manual');
            $table->string('status', 30)->default('accepted');
            $table->string('payment_status', 20)->default('pending');
            $table->unsignedInteger('accepted_by')->nullable();
            $table->timestamps();
        });

        Schema::create('travel_tariffs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->unsignedInteger('origin_station_id');
            $table->unsignedInteger('destination_station_id');
            $table->unsignedInteger('min_grams')->default(0);
            $table->unsignedInteger('max_grams')->nullable();
            $table->unsignedInteger('fee_xaf');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('travel_custody', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->unsignedInteger('parcel_id');
            $table->unsignedInteger('station_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('event', 30);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('travel_handovers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->unsignedInteger('parcel_id')->unique();
            $table->unsignedInteger('station_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('method', 10);
            $table->string('id_hint', 8)->nullable();
            $table->longText('signature');
            $table->dateTime('collected_at');
            $table->timestamps();
        });

        Schema::create('travel_tills', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('station_id');
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->unsignedInteger('expected_cash')->default(0);
            $table->unsignedInteger('declared_cash')->nullable();
            $table->timestamps();
        });

        Schema::create('travel_audits', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cloud_tenant_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('action', 60);
            $table->string('subject_type', 40);
            $table->unsignedInteger('subject_id')->nullable();
            $table->text('payload')->nullable();
            $table->timestamps();
        });

        $this->grantPermissions();
    }

    public function down()
    {
        Schema::dropIfExists('travel_audits');
        Schema::dropIfExists('travel_tills');
        Schema::dropIfExists('travel_handovers');
        Schema::dropIfExists('travel_custody');
        Schema::dropIfExists('travel_tariffs');
        Schema::dropIfExists('travel_parcels');
        Schema::dropIfExists('travel_outbox');
        Schema::dropIfExists('travel_otps');
        Schema::dropIfExists('travel_payments');
        Schema::dropIfExists('travel_bookings');
        Schema::dropIfExists('travel_seats');
        Schema::dropIfExists('travel_departures');
        Schema::dropIfExists('travel_drivers');
        Schema::dropIfExists('travel_vehicles');
        Schema::dropIfExists('travel_route_stops');
        Schema::dropIfExists('travel_routes');
        Schema::dropIfExists('travel_stations');
        Schema::dropIfExists('travel_settings');
    }

    protected function grantPermissions()
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }
        $role = DB::table('roles')->where('id', 1)->first();
        if (! $role) {
            $role = DB::table('roles')->where('name', 'Admin')->first();
        }
        if (! $role) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        foreach (['travel-dispatch', 'travel-counter', 'travel-parcels', 'travel-finance', 'travel-admin'] as $name) {
            $permission = DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->first();
            if (! $permission) {
                $permissionId = DB::table('permissions')->insertGetId([
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $permissionId = (int) $permission->id;
            }
            $linked = DB::table('role_has_permissions')
                ->where('role_id', $role->id)
                ->where('permission_id', $permissionId)
                ->exists();
            if (! $linked) {
                DB::table('role_has_permissions')->insert([
                    'permission_id' => $permissionId,
                    'role_id' => $role->id,
                ]);
            }
        }
    }
}
