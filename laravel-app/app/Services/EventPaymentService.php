<?php

namespace App\Services;

use App\Event;
use App\EventAssignment;
use App\EventWorkerPayment;
use App\GeneralSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use PDF;

class EventPaymentService
{
    public function createPayment(Event $event, EventAssignment $assignment, array $data)
    {
        $amount = (int) ($data['amount'] ?? $assignment->expected_total ?? 0);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $profile = $assignment->workerProfile;

        return EventWorkerPayment::create([
            'event_id' => $event->id,
            'assignment_id' => $assignment->id,
            'worker_profile_id' => $assignment->worker_profile_id,
            'reference_no' => $this->nextReferenceNo(),
            'amount' => $amount,
            'payment_method' => $data['payment_method'] ?? 'mobile_money',
            'mobile_money_number' => $data['mobile_money_number'] ?? $profile->telephone ?? optional($profile->customer)->phone_number,
            'status' => EventWorkerPayment::STATUS_PENDING,
            'notes' => $data['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);
    }

    public function markPaid(EventWorkerPayment $payment)
    {
        $payment->update([
            'status' => EventWorkerPayment::STATUS_PAID,
            'paid_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        $receiptPath = $this->generateReceipt($payment);
        $payment->update(['receipt_path' => $receiptPath]);
        $payment->assignment->update(['payment_status' => 'paid']);

        return $payment->fresh();
    }

    public function generateReceipt(EventWorkerPayment $payment)
    {
        $payment->load(['event', 'assignment', 'workerProfile.customer']);
        $gs = GeneralSetting::first();

        $directory = public_path('event_payments/receipts');
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $pdf = PDF::loadView('pdf.event_payment_receipt', [
            'payment' => $payment,
            'general_setting' => $gs,
        ])->setPaper('a4');

        $filename = 'event_payments/receipts/' . $payment->reference_no . '.pdf';
        $pdf->save(public_path($filename));

        return $filename;
    }

    public function calculateForAssignment(EventAssignment $assignment)
    {
        $timesheet = $assignment->timesheets()->where('status', 'approved')->latest()->first();
        if ($timesheet && $timesheet->total_hours > 0 && $assignment->hourly_rate) {
            return (int) round($timesheet->total_hours * $assignment->hourly_rate);
        }
        if ($timesheet && $timesheet->total_days > 0) {
            $rate = $assignment->event_daily_rate ?: $assignment->default_daily_rate ?: 0;

            return (int) ($timesheet->total_days * $rate);
        }

        return (int) ($assignment->expected_total ?: 0);
    }

    public function nextReferenceNo()
    {
        $year = date('Y');
        $prefix = 'EP/' . $year . '/';
        $last = EventWorkerPayment::where('reference_no', 'like', $prefix . '%')
            ->orderBy('reference_no', 'desc')
            ->value('reference_no');
        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
