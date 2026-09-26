<?php

namespace App\Http\Controllers;

use App\Biller;
use App\Booking;
use App\CashRegister;
use App\Customer;
use App\CustomerGroup;
use App\Services\BeyondWasenderService;
use App\Support\WhatsAppMessage;
use App\User;
use App\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PublicRentalController extends Controller
{
    public function index()
    {
        return view('beyond.rentals.index', [
            'countries' => \App\Support\CountryDialCodes::list(),
            'countryCode' => old('country_code', '+237'),
        ]);
    }

    public function store(Request $request, BeyondWasenderService $whatsapp)
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'country_code' => 'required|string|max:10',
            'email' => 'nullable|email|max:255',
            'company_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'equipment_needed' => 'required|string|max:2000',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $phone = $this->combinePhone($data['country_code'], $data['phone']);
        $warehouse = Warehouse::where('is_active', true)->first();
        $biller = Biller::where('is_active', true)->first();
        if (! $warehouse || ! $biller) {
            return back()->withInput()->withErrors(['form' => 'Rentals are temporarily unavailable. Please contact us on WhatsApp.']);
        }

        $group = CustomerGroup::first();
        $customer = Customer::where('phone_number', $phone)->first();
        if (! $customer) {
            $customer = Customer::create([
                'customer_group_id' => $group ? $group->id : 1,
                'name' => $data['full_name'],
                'company_name' => $data['company_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone_number' => $phone,
                'address' => $data['address'] ?? 'N/A',
                'city' => 'N/A',
                'is_active' => true,
            ]);
        } else {
            $customer->name = $data['full_name'];
            if (! empty($data['email'])) {
                $customer->email = $data['email'];
            }
            if (! empty($data['company_name'])) {
                $customer->company_name = $data['company_name'];
            }
            if (! empty($data['address'])) {
                $customer->address = $data['address'];
            }
            $customer->save();
        }

        $user = User::where('is_active', true)->orderBy('id')->first();
        $register = CashRegister::where('status', true)->first();

        $note = "GUEST BOOKING REQUEST\n"
            ."Equipment: {$data['equipment_needed']}\n"
            ."Period: {$data['start_date']} → {$data['end_date']}\n"
            .(! empty($data['notes']) ? "Notes: {$data['notes']}\n" : '');

        $booking = Booking::create([
            'reference_no' => 'br-req-'.date('Ymd').'-'.date('His'),
            'user_id' => $user ? $user->id : 1,
            'customer_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'biller_id' => $biller->id,
            'cash_register_id' => $register ? $register->id : 0,
            'item' => 0,
            'total_qty' => 0,
            'total_discount' => 0,
            'total_tax' => 0,
            'total_price' => 0,
            'grand_total' => 0,
            'paid_amount' => 0,
            'booking_status' => 2,
            'payment_status' => 1,
            'is_frontend' => 1,
            'payment_method' => 'REQUEST',
            'booking_note' => $note,
            'staff_note' => 'Submitted from public Rentals page. Convert to a full booking after review.',
        ]);

        try {
            $msg = WhatsAppMessage::statusBlock('📦', 'Booking Request')
                .WhatsAppMessage::greeting($data['full_name'])
                ."Your equipment rental request has been received and is pending review.\n\n"
                .WhatsAppMessage::bullet('Reference', $booking->reference_no)
                .WhatsAppMessage::bullet('Period', $data['start_date'].' → '.$data['end_date'])
                ."\nOur team will contact you on WhatsApp shortly."
                .WhatsAppMessage::footer();
            $whatsapp->sendText($phone, $msg);
        } catch (\Throwable $e) {
            Log::warning('Rental request WhatsApp failed: '.$e->getMessage());
        }

        return redirect()->route('beyond.rentals.confirmation', $booking->reference_no);
    }

    public function confirmation($reference)
    {
        $booking = Booking::where('reference_no', $reference)->where('is_frontend', 1)->first();

        return view('beyond.rentals.confirmation', compact('booking', 'reference'));
    }

    protected function combinePhone($code, $number)
    {
        $digits = preg_replace('/\D/', '', (string) $number);
        $digits = ltrim($digits, '0');
        $code = trim((string) $code);
        if ($code !== '' && strpos($code, '+') !== 0) {
            $code = '+'.$code;
        }

        return $code.$digits;
    }
}
