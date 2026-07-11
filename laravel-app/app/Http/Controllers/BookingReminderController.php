<?php

namespace App\Http\Controllers;

use App\Booking;
use App\BookingReminder;
use App\Support\WhatsAppMessage;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class BookingReminderController extends Controller
{
    public function index()
    {
        $this->authorizeAccess();

        $reminders = BookingReminder::with(['booking.customer', 'booking.user', 'user'])
            ->orderByDesc('remind_at')
            ->get();

        $bookings = Booking::with('customer')
            ->where('is_frontend', false)
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('booking.reminders', compact('reminders', 'bookings'));
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'booking_id' => 'required|integer|exists:bookings,id',
            'remind_at' => 'required|date|after:now',
            'message' => 'nullable|string|max:2000',
        ]);

        BookingReminder::create([
            'booking_id' => $request->booking_id,
            'user_id' => Auth::id(),
            'remind_at' => Carbon::parse($request->remind_at)->format('Y-m-d H:i:s'),
            'message' => $request->message,
        ]);

        return back()->with('message', 'Booking reminder scheduled successfully.');
    }

    public function destroy($id)
    {
        $this->authorizeAccess();

        $reminder = BookingReminder::findOrFail($id);
        if ($reminder->sent_at) {
            return back()->with('not_permitted', 'Sent reminders cannot be deleted.');
        }

        $reminder->delete();

        return back()->with('message', 'Reminder cancelled.');
    }

    public static function sendDueReminders()
    {
        $controller = app(self::class);
        $due = BookingReminder::with(['booking.customer', 'booking.biller'])
            ->whereNull('sent_at')
            ->where('remind_at', '<=', now())
            ->get();

        foreach ($due as $reminder) {
            $booking = $reminder->booking;
            $customer = optional($booking)->customer;

            if (!$customer || empty(trim((string) $customer->phone_number))) {
                continue;
            }

            try {
                $msg = WhatsAppMessage::bookingScheduledReminder(
                    $customer->name,
                    $booking->reference_no,
                    $reminder->remind_at->format('d M Y, H:i'),
                    $reminder->message
                );
                $controller->sendWhatsAppToCustomer($customer, $msg);
                $reminder->update(['sent_at' => now()]);
            } catch (\Exception $e) {
                \Log::warning('Booking reminder send failed for reminder #' . $reminder->id . ': ' . $e->getMessage());
            }
        }
    }

    private function authorizeAccess()
    {
        $role = Role::find(Auth::user()->role_id);
        if (!$role || !$role->hasPermissionTo('booking_index')) {
            abort(403);
        }
    }
}
