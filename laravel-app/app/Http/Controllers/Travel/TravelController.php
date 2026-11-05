<?php

namespace App\Http\Controllers\Travel;

use App\Travel\TravelCatalog;
use App\Travel\TravelCheckout;
use App\Travel\TravelClock;
use App\Travel\TravelContext;
use App\Travel\TravelException;
use App\Travel\TravelOtp;
use App\Travel\TravelParcels;
use App\Travel\TravelPayments;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class TravelController extends Controller
{
    public function home(Request $request)
    {
        $tenant = $this->tenant();
        TravelCatalog::publishAgencyRoutes($tenant);
        $from = $request->query('from', 'Bamenda');
        $to = $request->query('to', 'Douala');
        $date = $request->query('date', TravelClock::today());
        $trips = ($from !== $to && $date) ? TravelCatalog::search($tenant, $from, $to, $date) : [];

        return view('travel.home', [
            'stations' => TravelCatalog::stations($tenant),
            'from' => $from,
            'to' => $to,
            'date' => $date,
            'trips' => $trips,
        ]);
    }

    public function lang($locale)
    {
        session(['travel_lang' => $locale === 'fr' ? 'fr' : 'en']);

        return redirect()->back();
    }

    public function departure($id)
    {
        $tenant = $this->tenant();
        $departure = $this->findDeparture($tenant, $id);
        if (! in_array($departure->status, ['scheduled', 'loading', 'boarding', 'delayed'], true)) {
            abort(404);
        }
        $seats = DB::table('travel_seats')->where('departure_id', $departure->id)->orderBy('seat_no')->get();
        foreach ($seats as $seat) {
            if ($seat->status === 'held' && $seat->hold_expires_at && Carbon::now()->greaterThan(Carbon::parse($seat->hold_expires_at))) {
                $seat->status = 'free';
            }
        }

        return view('travel.departure', [
            'departure' => $departure,
            'seats' => $seats,
            'origin' => DB::table('travel_stations')->where('id', $departure->origin_station_id)->value('name'),
            'destination' => DB::table('travel_stations')->where('id', $departure->destination_station_id)->value('name'),
        ]);
    }

    public function otp(Request $request)
    {
        $tenant = $this->tenant();
        $purpose = (string) $request->input('purpose');
        if (! preg_match('/^(ticket:\d+:\d+|my-trips|parcel:\d+)$/', $purpose)) {
            abort(422);
        }
        try {
            TravelOtp::issue($tenant, $request->input('phone'), $purpose);
        } catch (TravelException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Code sent on WhatsApp.');
    }

    public function checkout(Request $request)
    {
        $tenant = $this->tenant();
        try {
            $bookingId = app(TravelCheckout::class)->hold(
                $tenant,
                (int) $request->input('departure_id'),
                (int) $request->input('seat'),
                $request->input('name'),
                $request->input('phone'),
                $request->input('otp'),
                $request->input('payer_phone')
            );
        } catch (TravelException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }
        session(['travel_hold' => $bookingId]);

        return redirect()->route('travel.pay', $bookingId);
    }

    public function pay($id)
    {
        $booking = $this->heldBooking($id);

        return view('travel.pay', [
            'booking' => $booking,
            'sandbox' => (bool) config('travel.sandbox'),
        ]);
    }

    public function charge(Request $request, $id)
    {
        $booking = $this->heldBooking($id);
        $payments = app(TravelPayments::class);
        if (! config('travel.sandbox')) {
            try {
                $payments->collect($booking->id);
            } catch (TravelException $e) {
                return back()->with('error', $e->getMessage());
            }

            return back()->with('status', 'Approve the MoMo prompt. The ticket is issued after payment is confirmed.');
        }
        $result = $payments->finalize([
            'reference' => $booking->payment_reference,
            'amount' => (int) $booking->amount_xaf,
            'currency' => 'XAF',
            'status' => 'SUCCESSFUL',
            'provider_reference' => 'sandbox',
        ]);
        session(['travel_phone' => $booking->phone]);

        return redirect()->route('travel.trips')->with('status', $result['status'] === 'issued'
            ? 'Ticket issued.'
            : 'Payment received. The seat was no longer free, so the desk will contact you.');
    }

    public function callback(Request $request)
    {
        $secret = (string) config('travel.callback_secret');
        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        $given = (string) $request->header('X-Travel-Signature');
        if ($secret === '' || ! hash_equals($expected, $given)) {
            abort(403);
        }
        try {
            $result = app(TravelPayments::class)->finalize($request->json()->all() ?: $request->all());
        } catch (TravelException $e) {
            return response()->json(['ok' => false, 'error' => $e->error], 404);
        }

        return response()->json($result);
    }

    public function tripsForm()
    {
        $this->tenant();

        return view('travel.trips', ['bookings' => $this->myBookings()]);
    }

    public function trips(Request $request)
    {
        $tenant = $this->tenant();
        try {
            $ok = TravelOtp::consume($tenant, $request->input('phone'), 'my-trips', $request->input('otp'));
        } catch (TravelException $e) {
            return back()->with('error', $e->getMessage());
        }
        if (! $ok) {
            return back()->with('error', 'That code is wrong, expired, or already used.');
        }
        session(['travel_phone' => \App\Travel\TravelPhone::normalize($request->input('phone'))]);

        return redirect()->route('travel.trips');
    }

    public function track(Request $request)
    {
        $tenant = $this->tenant();
        $result = null;
        if ($request->filled('ref')) {
            $result = app(TravelParcels::class)->publicStatus($tenant, $request->query('ref'));
        }

        return view('travel.track', ['result' => $result, 'ref' => $request->query('ref')]);
    }

    protected function myBookings()
    {
        $phone = session('travel_phone');
        if (! $phone) {
            return [];
        }
        $tenant = $this->tenant();

        return DB::table('travel_bookings')
            ->where('cloud_tenant_id', $tenant)
            ->where('phone', $phone)
            ->orderByDesc('id')
            ->get();
    }

    protected function heldBooking($id)
    {
        $tenant = $this->tenant();
        if ((int) session('travel_hold') !== (int) $id) {
            abort(404);
        }
        $booking = DB::table('travel_bookings')->where('cloud_tenant_id', $tenant)->where('id', $id)->first();
        if (! $booking) {
            abort(404);
        }

        return $booking;
    }

    protected function findDeparture($tenant, $id)
    {
        $departure = DB::table('travel_departures')->where('cloud_tenant_id', $tenant)->where('id', $id)->first();
        if (! $departure) {
            abort(404);
        }

        return $departure;
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
