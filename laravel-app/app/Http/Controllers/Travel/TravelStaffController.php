<?php

namespace App\Http\Controllers\Travel;

use App\Travel\TravelCatalog;
use App\Travel\TravelCheckout;
use App\Travel\TravelClock;
use App\Travel\TravelPhone;
use App\Travel\TravelContext;
use App\Travel\TravelDispatch;
use App\Travel\TravelException;
use App\Travel\TravelParcels;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class TravelStaffController extends Controller
{
    public function hub()
    {
        $user = auth()->user();
        if (! $user || (! $this->allows($user, 'dispatch') && ! $this->allows($user, 'counter') && ! $this->allows($user, 'parcels') && ! $this->allows($user, 'finance'))) {
            abort(403);
        }

        return view('travel.staff');
    }

    public function dispatchForm()
    {
        $this->guard('dispatch');
        $tenant = $this->tenant();

        return view('travel.dispatch', $this->desk($tenant));
    }

    public function fixture()
    {
        $this->guard('dispatch');
        if (! config('travel.sandbox')) {
            abort(404);
        }
        TravelCatalog::stagingFixture($this->tenant(), TravelClock::today());

        return back()->with('status', 'Staging departures loaded. Durations are placeholders until the agency confirms them.');
    }

    public function route(Request $request)
    {
        $this->guard('dispatch');
        $tenant = $this->tenant();
        try {
            TravelCatalog::saveRoute(
                $tenant,
                (int) $request->input('origin_station_id'),
                (int) $request->input('destination_station_id'),
                (int) $request->input('duration_minutes'),
                (bool) $request->input('confirmed')
            );
        } catch (TravelException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Route saved.');
    }

    public function schedule(Request $request)
    {
        $this->guard('dispatch');
        try {
            app(TravelDispatch::class)->schedule([
                'cloud_tenant_id' => $this->tenant(),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'date' => $request->input('date'),
                'shift' => $request->input('shift'),
                'class' => $request->input('class'),
                'bus' => $request->input('bus'),
                'driver' => $request->input('driver'),
                'user_id' => auth()->id(),
            ]);
        } catch (TravelException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('status', 'Trip scheduled.');
    }

    public function status(Request $request, $id)
    {
        $this->guard('dispatch');
        try {
            app(TravelDispatch::class)->changeStatus(
                $this->tenant(),
                (int) $id,
                $request->input('status'),
                $request->input('reason'),
                auth()->id()
            );
        } catch (TravelException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Status updated.');
    }

    public function manifest($id)
    {
        $this->guard('dispatch');
        $tenant = $this->tenant();
        $departure = DB::table('travel_departures')->where('cloud_tenant_id', $tenant)->where('id', $id)->first();
        if (! $departure) {
            abort(404);
        }
        $station = (int) request('station_id');
        if ($station !== (int) $departure->origin_station_id) {
            abort(403);
        }
        $bookings = DB::table('travel_bookings')
            ->where('departure_id', $departure->id)
            ->whereIn('ticket_status', ['issued', 'boarded', 'noshow'])
            ->orderBy('requested_seat')
            ->get();

        return view('travel.manifest', [
            'departure' => $departure,
            'bookings' => $bookings,
            'station' => $station,
            'origin' => DB::table('travel_stations')->where('id', $departure->origin_station_id)->value('name'),
            'destination' => DB::table('travel_stations')->where('id', $departure->destination_station_id)->value('name'),
        ]);
    }

    public function board(Request $request)
    {
        $this->guard('counter');
        try {
            app(TravelCheckout::class)->board(
                $this->tenant(),
                $request->input('reference'),
                (int) $request->input('station_id'),
                auth()->id()
            );
        } catch (TravelException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Passenger boarded.');
    }

    public function noShow(Request $request, $id)
    {
        $this->guard('dispatch');
        try {
            app(TravelCheckout::class)->noShow($this->tenant(), (int) $id, auth()->id());
        } catch (TravelException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'No-show recorded.');
    }

    public function counter()
    {
        $this->guard('counter');

        return view('travel.counter', $this->desk($this->tenant()));
    }

    public function sell(Request $request)
    {
        $this->guard('counter');
        try {
            $id = app(TravelCheckout::class)->sellCash(
                $this->tenant(),
                (int) $request->input('departure_id'),
                (int) $request->input('seat'),
                $request->input('name'),
                $request->input('phone'),
                auth()->id(),
                (int) $request->input('station_id')
            );
        } catch (TravelException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
        $reference = DB::table('travel_bookings')->where('id', $id)->value('reference');

        return back()->with('status', 'Ticket '.$reference.' issued.');
    }

    public function void(Request $request, $id)
    {
        $this->guard('finance');
        try {
            app(TravelCheckout::class)->voidCash($this->tenant(), (int) $id, auth()->id());
        } catch (TravelException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Cash ticket voided.');
    }

    public function parcels(Request $request)
    {
        $this->guard('parcels');
        $tenant = $this->tenant();
        $data = $this->desk($tenant);
        $data['desk'] = $request->query('desk') === 'collection' ? 'collection' : 'send';
        $data['tab'] = $request->query('tab') === 'collected' ? 'collected' : 'awaiting';
        $data['stationId'] = (int) $request->query('station');
        $data['names'] = DB::table('travel_stations')->where('cloud_tenant_id', $tenant)->pluck('name', 'id');
        $data['expectedDefault'] = Carbon::parse(TravelClock::today())->addDay()->toDateString();
        $data['rows'] = collect();
        $data['open'] = null;
        if ($data['desk'] === 'collection' && $data['stationId'] > 0) {
            $query = DB::table('travel_parcels')
                ->where('cloud_tenant_id', $tenant)
                ->where('destination_station_id', $data['stationId'])
                ->orderByDesc('id');
            if ($data['tab'] === 'collected') {
                $query->where('status', 'collected');
            } else {
                $query->whereIn('status', ['accepted', 'assigned', 'loaded', 'in_transit', 'received', 'ready']);
            }
            $data['rows'] = $query->limit(80)->get();
            $openId = (int) $request->query('open');
            if ($openId) {
                $data['open'] = $data['rows']->firstWhere('id', $openId);
            }
        }

        return view('travel.parcels', $data);
    }

    public function lookupPhone(Request $request)
    {
        $this->guard('parcels');
        try {
            $phone = TravelPhone::normalize($request->input('phone'));
        } catch (TravelException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        $holder = app(\App\Services\MobileMoneyHolderService::class)->lookup($phone, false, false);
        $name = isset($holder['name']) ? trim((string) $holder['name']) : '';

        return response()->json([
            'ok' => true,
            'phone' => $phone,
            'name' => $name !== '' ? $name : null,
            'source' => isset($holder['source']) ? $holder['source'] : null,
            'message' => $name !== ''
                ? 'Name on this Mobile Money number.'
                : 'No MoMo name on this number. Type the name the client confirms.',
        ]);
    }

    public function storeParcel(Request $request)
    {
        $this->guard('parcels');
        $channel = $request->input('channel') === 'momo' ? 'momo' : 'cash';
        try {
            $id = app(TravelParcels::class)->accept([
                'cloud_tenant_id' => $this->tenant(),
                'origin_station_id' => (int) $request->input('origin_station_id'),
                'destination_station_id' => (int) $request->input('destination_station_id'),
                'expected_on' => $request->input('expected_on'),
                'sender_name' => $request->input('sender_name'),
                'sender_phone' => $request->input('sender_phone'),
                'receiver_name' => $request->input('receiver_name'),
                'receiver_phone' => $request->input('receiver_phone'),
                'description' => $request->input('description'),
                'weight_grams' => $request->input('weight_grams'),
                'package_count' => $request->input('package_count'),
                'fee_xaf' => $request->input('fee_xaf'),
                'channel' => $channel,
                'user_id' => auth()->id(),
            ]);
        } catch (TravelException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
        if ($channel === 'momo') {
            return redirect()->route('travel.parcels.pay', $id);
        }
        $reference = DB::table('travel_parcels')->where('id', $id)->value('tracking_ref');

        return redirect()->route('travel.parcels')->with('status', 'Parcel '.$reference.' registered. The invoice is on the sender’s phone, and the receiver has the delivery notice.');
    }

    public function parcelPay($id)
    {
        $this->guard('parcels');
        $parcel = $this->parcelRow($id);
        $payment = DB::table('travel_payments')->where('parcel_id', $parcel->id)->orderByDesc('id')->first();

        return view('travel.parcel-pay', [
            'parcel' => $parcel,
            'payment' => $payment,
            'sandbox' => (bool) config('travel.sandbox'),
            'origin' => DB::table('travel_stations')->where('id', $parcel->origin_station_id)->value('name'),
            'destination' => DB::table('travel_stations')->where('id', $parcel->destination_station_id)->value('name'),
        ]);
    }

    public function parcelCharge(Request $request, $id)
    {
        $this->guard('parcels');
        $parcel = $this->parcelRow($id);
        $payment = DB::table('travel_payments')->where('parcel_id', $parcel->id)->where('status', 'pending')->first();
        if (! $payment) {
            return redirect()->route('travel.parcels')->with('status', 'This parcel is already paid.');
        }
        try {
            $phone = TravelPhone::normalize($request->input('payer_phone'));
        } catch (TravelException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
        $account = trim((string) $request->input('account_name'));
        if ($account === '') {
            return back()->with('error', 'Confirm the name on the Mobile Money number.')->withInput();
        }
        $payments = app(TravelPayments::class);
        if (! config('travel.sandbox')) {
            try {
                $payments->collectParcel($parcel->id, $phone);
            } catch (TravelException $e) {
                return back()->with('error', $e->getMessage());
            }

            return back()->with('status', 'Ask the client to approve the MoMo prompt. The invoice goes out when the payment is confirmed.');
        }
        $payments->finalize([
            'reference' => $payment->reference,
            'amount' => (int) $payment->amount_xaf,
            'currency' => 'XAF',
            'status' => 'SUCCESSFUL',
            'provider_reference' => 'sandbox',
            'account_name' => $account,
        ]);

        return redirect()->route('travel.parcels')->with('status', 'Payment confirmed for '.$parcel->tracking_ref.'. The sender has the invoice, and the receiver has the delivery notice.');
    }

    public function scan(Request $request, $id)
    {
        $this->guard('parcels');
        try {
            app(TravelParcels::class)->scan(
                $this->tenant(),
                (int) $id,
                $request->input('event'),
                (int) $request->input('station_id'),
                auth()->id()
            );
        } catch (TravelException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Scan recorded.');
    }

    public function collect(Request $request, $id)
    {
        $this->guard('parcels');
        try {
            app(TravelParcels::class)->collect(
                $this->tenant(),
                (int) $id,
                $request->input('handover', $request->input('method')),
                $request->input('proof'),
                $request->input('signature'),
                (int) $request->input('station_id'),
                auth()->id()
            );
        } catch (TravelException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Parcel handed over.');
    }

    public function reconcile()
    {
        $this->guard('finance');
        $tenant = $this->tenant();
        $tills = DB::table('travel_tills')->where('cloud_tenant_id', $tenant)->orderByDesc('id')->limit(20)->get();
        $unallocated = DB::table('travel_bookings')->where('cloud_tenant_id', $tenant)->where('payment_status', 'unallocated')->get();
        $refunds = DB::table('travel_bookings')->where('cloud_tenant_id', $tenant)->where('payment_status', 'refund_review')->get();

        return view('travel.reconcile', compact('tills', 'unallocated', 'refunds'));
    }

    public function closeTill(Request $request)
    {
        $user = auth()->user();
        if (! $user || (! $this->allows($user, 'counter') && ! $this->allows($user, 'finance'))) {
            abort(403);
        }
        try {
            app(TravelCheckout::class)->closeTill($this->tenant(), auth()->id(), (int) $request->input('declared'));
        } catch (TravelException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Till closed.');
    }

    protected function parcelRow($id)
    {
        $parcel = DB::table('travel_parcels')
            ->where('cloud_tenant_id', $this->tenant())
            ->where('id', (int) $id)
            ->first();
        if (! $parcel) {
            abort(404);
        }

        return $parcel;
    }

    protected function desk($tenant)
    {
        TravelCatalog::publishAgencyRoutes($tenant);

        return [
            'stations' => TravelCatalog::stations($tenant),
            'routes' => DB::table('travel_routes')->where('cloud_tenant_id', $tenant)->get(),
            'departures' => DB::table('travel_departures')
                ->where('cloud_tenant_id', $tenant)
                ->orderByDesc('scheduled_at')
                ->limit(40)
                ->get(),
            'today' => TravelClock::today(),
            'sandbox' => (bool) config('travel.sandbox'),
        ];
    }

    protected function guard($ability)
    {
        $user = auth()->user();
        if (! $user || ! $this->allows($user, $ability)) {
            abort(403);
        }
    }

    protected function allows($user, $ability)
    {
        if ((int) $user->role_id === 1) {
            return true;
        }
        $names = [
            'dispatch' => 'travel-dispatch',
            'counter' => 'travel-counter',
            'parcels' => 'travel-parcels',
            'finance' => 'travel-finance',
        ];
        try {
            $role = $user->role;
            if ($role && $role->hasPermissionTo('travel-admin')) {
                return true;
            }
            $name = isset($names[$ability]) ? $names[$ability] : null;

            return $role && $name && $role->hasPermissionTo($name);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function tenant()
    {
        $id = TravelContext::tenantId();
        if (! $id || ! TravelContext::enabled($id)) {
            abort(404);
        }

        return $id;
    }
}
