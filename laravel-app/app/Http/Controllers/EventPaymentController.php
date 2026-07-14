<?php

namespace App\Http\Controllers;

use App\Event;
use App\EventAssignment;
use App\EventWorkerPayment;
use App\Services\EventPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class EventPaymentController extends Controller
{
    protected $all_permission = [];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $role = Role::find(Auth::user()->role_id);
            foreach (Role::findByName($role->name)->permissions as $permission) {
                $this->all_permission[] = $permission->name;
            }
            View::share('all_permission', $this->all_permission);

            return $next($request);
        });
    }

    protected function can($perm)
    {
        if (! in_array($perm, $this->all_permission, true)) {
            abort(403);
        }
    }

    public function index(Request $request)
    {
        $this->can('event_payments.view');

        $query = EventWorkerPayment::with(['event', 'workerProfile.customer', 'assignment'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $payments = $query->paginate(25);

        return view('events.payments.index', compact('payments'));
    }

    public function createForAssignment(Request $request, $eventId, EventPaymentService $payments)
    {
        $this->can('event_payments.create');

        $event = Event::findOrFail($eventId);
        $data = $request->validate([
            'assignment_id' => 'required|exists:event_assignments,id',
            'amount' => 'nullable|integer|min:1',
            'payment_method' => 'nullable|string|max:32',
            'mobile_money_number' => 'nullable|string|max:64',
            'notes' => 'nullable|string',
        ]);

        $assignment = EventAssignment::where('event_id', $event->id)->findOrFail($data['assignment_id']);
        if (empty($data['amount'])) {
            $data['amount'] = $payments->calculateForAssignment($assignment);
        }

        try {
            $payment = $payments->createPayment($event, $assignment, $data);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()->route('events.show', ['id' => $event->id, 'tab' => 'payments'])
            ->with('message', 'Payment ' . $payment->reference_no . ' created.');
    }

    public function markPaid($paymentId, EventPaymentService $payments)
    {
        $this->can('event_payments.approve');

        $payment = EventWorkerPayment::with('assignment.workerProfile.customer')->findOrFail($paymentId);
        if ($payment->status === EventWorkerPayment::STATUS_PAID) {
            return back()->withErrors(['payment' => 'Already paid.']);
        }

        $payment = $payments->markPaid($payment);

        $profile = $payment->workerProfile;
        $phone = $payment->mobile_money_number ?: $profile->telephone ?? optional($profile->customer)->phone_number;
        if ($phone && $payment->receipt_path) {
            try {
                $msg = 'Beyond Enterprise: Labour payment of ' . number_format($payment->amount) . ' XAF for '
                    . $payment->event->name . ' (' . $payment->reference_no . ') has been processed.';
                $this->sendWhatsAppToPhone($phone, $msg);
                $this->sendWhatsAppDocumentToPhone(
                    $phone,
                    public_path($payment->receipt_path),
                    $payment->reference_no . '_receipt.pdf',
                    url($payment->receipt_path)
                );
            } catch (\Exception $e) {
                Log::warning('Payment receipt WhatsApp failed: ' . $e->getMessage());
            }
        }

        return back()->with('message', 'Payment marked paid and receipt generated.');
    }
}
