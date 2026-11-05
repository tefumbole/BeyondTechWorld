<?php

namespace Tests\Feature;

use App\Travel\TravelCatalog;
use App\Travel\TravelCheckout;
use App\Travel\TravelClock;
use App\Travel\TravelContext;
use App\Travel\TravelDispatch;
use App\Travel\TravelException;
use App\Travel\TravelOtp;
use App\Travel\TravelParcels;
use App\Travel\TravelPayments;
use App\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TravelModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_11_05_143000_create_travel_module.php',
            '--force' => true,
        ]);
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_11_05_171500_add_expected_on_to_travel_parcels.php',
            '--force' => true,
        ]);
        config([
            'travel.enabled' => true,
            'travel.sandbox' => true,
            'travel.whatsapp_live' => false,
            'travel.callback_secret' => 'test-secret',
            'travel.platform_tenant_id' => 7,
        ]);
        TravelContext::allow(7);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_corridors_stop_at_melong_or_makenene_and_take_about_nine_hours()
    {
        TravelCatalog::publishAgencyRoutes(7);
        $this->assertNotNull(TravelCatalog::stationByName(7, 'Mutengene'));
        $this->assertNull(TravelCatalog::stationByName(7, 'Mutenge'));
        $names = TravelCatalog::stations(7)->pluck('name')->all();
        $this->assertContains('Mutengene', $names);
        $this->assertNotContains('Melong', $names);
        $this->assertNotContains('Makenene', $names);
        foreach (['Buea', 'Limbe', 'Mutengene'] as $southwest) {
            $this->assertSame('South West', TravelCatalog::stationByName(7, $southwest)->region);
        }
        $this->assertNull(TravelCatalog::stationByName(7, 'Douala')->region);

        $bamenda = TravelCatalog::stationByName(7, 'Bamenda');
        $this->assertCorridor($bamenda->id, 'Douala', 'Melong');
        $this->assertCorridor($bamenda->id, 'Limbe', 'Melong');
        $this->assertCorridor($bamenda->id, 'Buea', 'Melong');
        $this->assertCorridor($bamenda->id, 'Mutengene', 'Melong');
        $this->assertCorridor($bamenda->id, 'Yaoundé', 'Makenene');
        $douala = TravelCatalog::stationByName(7, 'Douala');
        $this->assertCorridor($douala->id, 'Bamenda', 'Melong');

        $departure = app(TravelDispatch::class)->schedule([
            'cloud_tenant_id' => 7,
            'from' => 'Bamenda',
            'to' => 'Douala',
            'date' => TravelClock::today(),
            'shift' => 'morning',
            'class' => 'vip',
            'bus' => 'EP-1',
            'driver' => 'Ada',
            'user_id' => 1,
        ]);
        $trip = DB::table('travel_departures')->where('id', $departure)->first();
        $this->assertSame('Melong', $trip->via);
        $this->assertSame(540, (int) $trip->duration_minutes);
    }

    public function test_module_stays_off_until_enabled()
    {
        config(['travel.enabled' => false]);
        $this->get('/travel')->assertStatus(404);
    }

    public function test_public_desk_lists_only_this_company()
    {
        $own = $this->departure(7, 'Bamenda', 'Douala', 'morning', 'vip', 'EP-A', 'Driver A');
        TravelContext::allow(8);
        TravelCatalog::seedStations(8);
        $this->route(8, 'Bamenda', 'Douala');
        $other = $this->departure(8, 'Bamenda', 'Douala', 'morning', 'vip', 'EP-B', 'Driver B');

        $this->get('/travel')->assertOk()->assertSee('Where are you headed?')->assertSee('Station operations');
        $this->get('/travel/book')->assertOk()->assertSee('8,000')->assertSee('South West')->assertSee('Search a station');
        $trips = TravelCatalog::search(7, 'Bamenda', 'Douala', TravelClock::today());
        $ids = array_map(function ($trip) {
            return (int) $trip->id;
        }, $trips);
        $this->assertContains($own, $ids);
        $this->assertNotContains($other, $ids);
    }

    public function test_reciprocal_routes_can_run_and_a_bus_cannot_overlap()
    {
        $this->route(7, 'Bamenda', 'Douala');
        $this->route(7, 'Douala', 'Bamenda');
        $going = $this->departure(7, 'Bamenda', 'Douala', 'morning', 'vip', 'EP-1', 'Ada');
        $back = $this->departure(7, 'Douala', 'Bamenda', 'morning', 'vip', 'EP-2', 'Beno');
        $this->assertNotSame($going, $back);
        try {
            $this->departure(7, 'Bamenda', 'Douala', 'morning', 'vip', 'EP-1', 'Cara');
            $this->fail('The same bus was assigned twice.');
        } catch (TravelException $e) {
            $this->assertSame('overlap', $e->error);
        }
    }

    public function test_two_buyers_cannot_take_the_same_seat()
    {
        $departure = $this->departure(7, 'Bamenda', 'Douala', 'morning', 'vip', 'EP-1', 'Ada');
        $first = $this->hold($departure, 12, '237670000001', 'Ada');
        try {
            $this->hold($departure, 12, '237670000002', 'Beno');
            $this->fail('The second buyer took a held seat.');
        } catch (TravelException $e) {
            $this->assertSame('seat', $e->error);
        }
        $this->assertSame(1, DB::table('travel_bookings')->where('departure_id', $departure)->count());
        $this->assertSame('held', DB::table('travel_seats')->where('id', $first['seat'])->value('status'));
    }

    public function test_duplicate_payment_callback_issues_one_ticket()
    {
        $departure = $this->departure(7, 'Bamenda', 'Douala', 'morning', 'vip', 'EP-1', 'Ada');
        $held = $this->hold($departure, 8, '237670000001', 'Ada Passenger');
        $payload = [
            'reference' => $held['payment'],
            'amount' => 8000,
            'currency' => 'XAF',
            'status' => 'SUCCESSFUL',
            'provider_reference' => 'sandbox-1',
            'account_name' => 'WALLET PERSON',
        ];
        $first = app(TravelPayments::class)->finalize($payload);
        $second = app(TravelPayments::class)->finalize($payload);
        $this->postCallback($payload);
        $booking = DB::table('travel_bookings')->where('id', $held['booking'])->first();
        $this->assertSame('issued', $first['status']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame('issued', $booking->ticket_status);
        $this->assertSame('Ada Passenger', $booking->passenger_name);
        $this->assertSame('WALLET PERSON', DB::table('travel_payments')->where('reference', $held['payment'])->value('account_name'));
        $this->assertSame(1, DB::table('travel_outbox')->where('dedupe_key', 'ticket:'.$booking->id)->count());
        $this->assertSame(1, DB::table('travel_bookings')->where('ticket_status', 'issued')->count());
    }

    public function test_wrong_amount_does_not_issue_and_a_failure_releases_the_hold()
    {
        $departure = $this->departure(7, 'Bamenda', 'Douala', 'morning', 'vip', 'EP-1', 'Ada');
        $held = $this->hold($departure, 3, '237670000001', 'Ada');
        $wrong = app(TravelPayments::class)->finalize([
            'reference' => $held['payment'],
            'amount' => 100,
            'currency' => 'XAF',
            'status' => 'SUCCESSFUL',
        ]);
        $this->assertSame('pending', $wrong['status']);
        $this->assertSame('held', DB::table('travel_seats')->where('id', $held['seat'])->value('status'));
        app(TravelPayments::class)->finalize([
            'reference' => $held['payment'],
            'amount' => 8000,
            'currency' => 'XAF',
            'status' => 'FAILED',
        ]);
        $this->assertSame('free', DB::table('travel_seats')->where('id', $held['seat'])->value('status'));
        $this->assertSame('none', DB::table('travel_bookings')->where('id', $held['booking'])->value('ticket_status'));
    }

    public function test_late_payment_confirms_only_while_the_same_seat_is_free()
    {
        $departure = $this->departure(7, 'Bamenda', 'Douala', 'morning', 'vip', 'EP-1', 'Ada');
        $open = $this->hold($departure, 4, '237670000001', 'Ada');
        Carbon::setTestNow(Carbon::now()->addMinutes(6));
        $result = app(TravelPayments::class)->finalize([
            'reference' => $open['payment'],
            'amount' => 8000,
            'currency' => 'XAF',
            'status' => 'SUCCESSFUL',
        ]);
        $this->assertSame('issued', $result['status']);

        Carbon::setTestNow();
        $late = $this->hold($departure, 5, '237670000003', 'Cara');
        Carbon::setTestNow(Carbon::now()->addMinutes(6));
        $origin = DB::table('travel_departures')->where('id', $departure)->value('origin_station_id');
        app(TravelCheckout::class)->sellCash(7, $departure, 5, 'Counter Guest', '237670000004', 1, $origin);
        $missed = app(TravelPayments::class)->finalize([
            'reference' => $late['payment'],
            'amount' => 8000,
            'currency' => 'XAF',
            'status' => 'SUCCESSFUL',
        ]);
        $this->assertSame('unallocated', $missed['status']);
        $seat = DB::table('travel_seats')->where('departure_id', $departure)->where('seat_no', 5)->first();
        $this->assertSame('sold', $seat->status);
        $this->assertNotSame($late['booking'], (int) $seat->booking_id);
        $this->assertSame('Counter Guest', DB::table('travel_bookings')->where('id', $seat->booking_id)->value('passenger_name'));
    }

    public function test_cash_void_frees_the_seat_and_cancellation_waits_for_review()
    {
        $departure = $this->departure(7, 'Bamenda', 'Douala', 'evening', 'regular', 'EP-9', 'Dara');
        $origin = DB::table('travel_departures')->where('id', $departure)->value('origin_station_id');
        $booking = app(TravelCheckout::class)->sellCash(7, $departure, 2, 'Cash Guest', '237670000005', 1, $origin);
        app(TravelCheckout::class)->voidCash(7, $booking, 1);
        $this->assertSame('free', DB::table('travel_seats')->where('departure_id', $departure)->where('seat_no', 2)->value('status'));
        $this->assertSame('void', DB::table('travel_bookings')->where('id', $booking)->value('ticket_status'));
        $this->assertSame(0, (int) DB::table('travel_tills')->where('user_id', 1)->value('expected_cash'));

        $kept = app(TravelCheckout::class)->sellCash(7, $departure, 6, 'Kept Guest', '237670000006', 1, $origin);
        app(TravelDispatch::class)->changeStatus(7, $departure, 'cancelled', 'Road closed', 1);
        $this->assertSame('refund_review', DB::table('travel_bookings')->where('id', $kept)->value('payment_status'));
        $this->assertSame(1, DB::table('travel_outbox')->where('dedupe_key', 'cancel:'.$kept)->count());
    }

    public function test_duplicate_parcel_handover_is_rejected_and_tracking_hides_people()
    {
        $departure = $this->departure(7, 'Bamenda', 'Douala', 'morning', 'vip', 'EP-1', 'Ada');
        $origin = (int) DB::table('travel_departures')->where('id', $departure)->value('origin_station_id');
        $destination = (int) DB::table('travel_departures')->where('id', $departure)->value('destination_station_id');
        $parcel = app(TravelParcels::class)->accept([
            'cloud_tenant_id' => 7,
            'departure_id' => $departure,
            'station_id' => $origin,
            'sender_name' => 'Secret Sender',
            'sender_phone' => '237670000007',
            'receiver_name' => 'Secret Receiver',
            'receiver_phone' => '237670000008',
            'description' => 'Box of books',
            'fee_xaf' => 2500,
            'channel' => 'cash',
            'user_id' => 1,
        ]);
        $parcels = app(TravelParcels::class);
        foreach (['assigned', 'loaded', 'in_transit'] as $event) {
            $parcels->scan(7, $parcel, $event, $origin, 1);
        }
        $parcels->scan(7, $parcel, 'received', $destination, 1);
        $parcels->scan(7, $parcel, 'ready', $destination, 1);
        $code = TravelOtp::issue(7, '237670000008', 'parcel:'.$parcel);
        $signature = 'data:image/png;base64,'.str_repeat('a', 40);
        $parcels->collect(7, $parcel, 'otp', $code, $signature, $destination, 1);
        try {
            $parcels->collect(7, $parcel, 'id', 'ID-99887766', $signature, $destination, 1);
            $this->fail('A second handover was recorded.');
        } catch (TravelException $e) {
            $this->assertSame('handover', $e->error);
        }
        $this->assertSame(1, DB::table('travel_handovers')->count());
        $reference = DB::table('travel_parcels')->where('id', $parcel)->value('tracking_ref');
        $this->assertSame(1, DB::table('travel_outbox')->where('dedupe_key', 'parcel-accept:'.$parcel)->count());
        $this->assertSame(1, DB::table('travel_outbox')->where('dedupe_key', 'parcel-ready:'.$parcel)->count());
        $this->get('/travel/track?ref='.$reference)
            ->assertOk()
            ->assertSee('Collected')
            ->assertDontSee('Secret Sender')
            ->assertDontSee('237670000007');
    }

    public function test_station_parcel_sends_the_invoice_only_after_payment()
    {
        TravelCatalog::publishAgencyRoutes(7);
        $from = TravelCatalog::stationByName(7, 'Bamenda');
        $to = TravelCatalog::stationByName(7, 'Douala');
        $tomorrow = Carbon::parse(TravelClock::today())->addDay()->toDateString();
        $cash = app(TravelParcels::class)->accept([
            'cloud_tenant_id' => 7,
            'origin_station_id' => $from->id,
            'destination_station_id' => $to->id,
            'expected_on' => $tomorrow,
            'sender_name' => 'Ada Sender',
            'sender_phone' => '237670000011',
            'receiver_name' => 'Bo Receiver',
            'receiver_phone' => '237670000012',
            'description' => 'Envelope',
            'fee_xaf' => 1500,
            'channel' => 'cash',
            'user_id' => 1,
        ]);
        $this->assertSame($tomorrow, DB::table('travel_parcels')->where('id', $cash)->value('expected_on'));
        $invoice = DB::table('travel_outbox')->where('dedupe_key', 'parcel-accept:'.$cash)->value('body');
        $notice = DB::table('travel_outbox')->where('dedupe_key', 'parcel-receiver:'.$cash)->value('body');
        $this->assertStringContainsString('EPIC TRAVELS', $invoice);
        $this->assertStringContainsString('Ada Sender', $notice);
        $this->assertStringContainsString('Expected delivery', $notice);

        $pending = app(TravelParcels::class)->accept([
            'cloud_tenant_id' => 7,
            'origin_station_id' => $from->id,
            'destination_station_id' => $to->id,
            'expected_on' => $tomorrow,
            'sender_name' => 'Cashless Sender',
            'sender_phone' => '237670000013',
            'receiver_name' => 'Bo Receiver',
            'receiver_phone' => '237670000014',
            'description' => 'Envelope',
            'fee_xaf' => 1500,
            'channel' => 'momo',
            'user_id' => 1,
        ]);
        $this->assertSame(0, DB::table('travel_outbox')->where('dedupe_key', 'parcel-accept:'.$pending)->count());
        $payment = DB::table('travel_payments')->where('parcel_id', $pending)->first();
        app(TravelPayments::class)->finalize([
            'reference' => $payment->reference,
            'amount' => 1500,
            'currency' => 'XAF',
            'status' => 'SUCCESSFUL',
            'account_name' => 'WALLET PERSON',
        ]);
        $this->assertSame('Cashless Sender', DB::table('travel_parcels')->where('id', $pending)->value('sender_name'));
        $this->assertSame('WALLET PERSON', DB::table('travel_payments')->where('id', $payment->id)->value('account_name'));
        $this->assertSame(1, DB::table('travel_outbox')->where('dedupe_key', 'parcel-receiver:'.$pending)->count());

        foreach (['assigned', 'loaded', 'in_transit'] as $event) {
            app(TravelParcels::class)->scan(7, $cash, $event, $from->id, 1);
        }
        app(TravelParcels::class)->scan(7, $cash, 'received', $to->id, 1);
        app(TravelParcels::class)->scan(7, $cash, 'ready', $to->id, 1);
        app(TravelParcels::class)->collect(7, $cash, 'signature', null, 'data:image/png;base64,'.str_repeat('b', 40), $to->id, 1);
        $this->assertSame('collected', DB::table('travel_parcels')->where('id', $cash)->value('status'));
        $this->assertSame('signature', DB::table('travel_handovers')->where('parcel_id', $cash)->value('method'));
    }

    public function test_callback_rejects_a_bad_signature()
    {
        $response = $this->call('POST', '/travel/payments/callback', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TRAVEL_SIGNATURE' => 'nope',
        ], '{"reference":"missing","amount":8000,"currency":"XAF","status":"SUCCESSFUL"}');
        $this->assertSame(403, $response->getStatusCode(), $response->getContent());
    }

    public function test_dispatch_page_renders_for_an_admin()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->integer('role_id')->nullable();
            $table->boolean('is_active')->default(1);
            $table->rememberToken();
            $table->timestamps();
        });
        $user = new User();
        $user->forceFill([
            'name' => 'Dispatcher',
            'email' => 'dispatch@test.test',
            'password' => 'secret',
            'role_id' => 1,
            'is_active' => 1,
        ]);
        $user->save();
        $this->actingAs($user)->get('/travel/staff')->assertOk()->assertSee('Station workspace')->assertSee('Parcels')->assertSee('Counter')->assertSee('Dispatch');
        $this->actingAs($user)->get('/travel/staff/parcels')->assertOk()->assertSee('Sender phone')->assertSee('Search a station')->assertSee('Collection');
        $this->actingAs($user)->get('/travel/staff/dispatch')->assertOk()->assertSee('Dispatch desk')->assertSee('South West');
    }

    protected function assertCorridor($originId, $destinationName, $stopName)
    {
        $destination = TravelCatalog::stationByName(7, $destinationName);
        $route = DB::table('travel_routes')
            ->where('origin_station_id', $originId)
            ->where('destination_station_id', $destination->id)
            ->first();
        $this->assertNotNull($route);
        $this->assertSame(540, (int) $route->duration_minutes);
        $this->assertSame(1, (int) $route->duration_confirmed);
        $this->assertSame($stopName, TravelCatalog::viaLabel($route->id));
    }

    protected function route($tenant, $from, $to)
    {
        TravelCatalog::seedStations($tenant);
        $origin = TravelCatalog::stationByName($tenant, $from);
        $destination = TravelCatalog::stationByName($tenant, $to);
        TravelCatalog::saveRoute($tenant, $origin->id, $destination->id, 360, false);
    }

    protected function departure($tenant, $from, $to, $shift, $class, $bus, $driver)
    {
        $this->route($tenant, $from, $to);

        return app(TravelDispatch::class)->schedule([
            'cloud_tenant_id' => $tenant,
            'from' => $from,
            'to' => $to,
            'date' => TravelClock::today(),
            'shift' => $shift,
            'class' => $class,
            'bus' => $bus,
            'driver' => $driver,
            'user_id' => 1,
        ]);
    }

    protected function hold($departure, $seat, $phone, $name)
    {
        $code = TravelOtp::issue(7, $phone, 'ticket:'.$departure.':'.$seat);
        $booking = app(TravelCheckout::class)->hold(7, $departure, $seat, $name, $phone, $code);
        $row = DB::table('travel_bookings')->where('id', $booking)->first();

        return [
            'booking' => $booking,
            'seat' => (int) $row->seat_id,
            'payment' => $row->payment_reference,
        ];
    }

    protected function postCallback(array $payload)
    {
        $body = json_encode($payload);
        $response = $this->call('POST', '/travel/payments/callback', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_TRAVEL_SIGNATURE' => hash_hmac('sha256', $body, 'test-secret'),
        ], $body);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
    }
}
