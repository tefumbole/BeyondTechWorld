<?php

namespace App\Http\Controllers;

use App\CampayPayout;
use App\Deposit;
use App\Payment;
use App\PublicDonation;
use App\Sale;
use Illuminate\Support\Facades\Schema;

class PaymentTransactionController extends Controller
{
    public function index()
    {
        $rows = $this->rows();

        return view('payment.transactions', compact('rows'));
    }

    protected function rows()
    {
        $rows = [];
        $seen = [];

        if (Schema::hasTable('deposits')) {
            $hasReference = Schema::hasColumn('deposits', 'campay_reference');
            foreach (Deposit::with('customer')->orderByDesc('id')->get() as $deposit) {
                $ref = $hasReference ? trim((string) $deposit->campay_reference) : '';
                if ($ref !== '') {
                    $seen[$ref] = true;
                }
                $paid = (int) $deposit->status === 1;
                $waiting = (int) $deposit->status === 0;
                $rows[] = $this->line(
                    $deposit->created_at,
                    'Deposit',
                    'in',
                    optional($deposit->customer)->name ?: 'Deposit',
                    (int) round((float) $deposit->amount),
                    $paid ? 'Paid' : ($waiting ? 'Pending' : 'Not paid'),
                    $paid ? 'ok' : ($waiting ? 'wait' : 'no'),
                    trim((string) $deposit->note)
                );
            }
        }

        if (Schema::hasTable('public_donations')) {
            foreach (PublicDonation::where('status', 'paid')->orderByDesc('id')->get() as $donation) {
                $ref = trim((string) $donation->campay_reference);
                if ($ref !== '' && isset($seen[$ref])) {
                    continue;
                }
                if ($ref !== '') {
                    $seen[$ref] = true;
                }
                $name = trim((string) $donation->person_name);
                $rows[] = $this->line(
                    $donation->created_at,
                    'Deposit',
                    'in',
                    $name !== '' ? $name : 'Donation',
                    (int) round((float) $donation->amount),
                    'Paid',
                    'ok',
                    trim((string) $donation->note)
                );
            }
        }

        if (Schema::hasTable('payments')) {
            $payments = Payment::query()->orderByDesc('id')->get();
            $sales = Sale::with('customer')->whereIn('id', $payments->pluck('sale_id')->filter()->unique()->all())->get()->keyBy('id');
            $hasProvider = Schema::hasColumn('payments', 'provider_reference');
            foreach ($payments as $payment) {
                $ref = $hasProvider ? trim((string) $payment->provider_reference) : '';
                if ($ref === '' && preg_match('/Campay\s+(\S+)/', (string) $payment->payment_note, $match)) {
                    $ref = $match[1];
                }
                if ($ref !== '' && isset($seen[$ref])) {
                    continue;
                }
                $sale = $sales->get($payment->sale_id);
                $person = ($sale && $sale->customer) ? $sale->customer->name : 'Sale';
                $note = $sale ? 'Sale '.$sale->reference_no : 'Sale payment';
                $method = trim((string) $payment->paying_method);
                if ($method !== '') {
                    $note .= ' · '.$method;
                }
                $rows[] = $this->line(
                    $payment->created_at,
                    'Payment',
                    'in',
                    $person,
                    (int) round((float) $payment->amount),
                    'Paid',
                    'ok',
                    $note
                );
            }
        }

        if (Schema::hasTable('campay_payouts')) {
            foreach (CampayPayout::orderByDesc('id')->get() as $payout) {
                $paid = $payout->status === 'paid';
                $waiting = $payout->status === 'pending';
                $name = trim((string) $payout->momo_name);
                if ($name === '') {
                    $name = trim((string) $payout->person_name);
                }
                $rows[] = $this->line(
                    $payout->created_at,
                    'Withdrawal',
                    'out',
                    $name !== '' ? $name : 'Withdrawal',
                    (int) $payout->amount,
                    $paid ? 'Paid' : ($waiting ? 'Pending' : 'Not paid'),
                    $paid ? 'ok' : ($waiting ? 'wait' : 'no'),
                    trim((string) $payout->note)
                );
            }
        }

        usort($rows, function ($a, $b) {
            if ($a['sort'] === $b['sort']) {
                return 0;
            }

            return $a['sort'] < $b['sort'] ? 1 : -1;
        });

        return $rows;
    }

    protected function line($at, $type, $direction, $person, $amount, $status, $tone, $note)
    {
        return [
            'sort' => $at ? $at->timestamp : 0,
            'when' => $at ? $at->format('M j, Y H:i') : '',
            'type' => $type,
            'direction' => $direction,
            'person' => trim((string) $person) !== '' ? $person : '—',
            'amount' => (int) $amount,
            'status' => $status,
            'tone' => $tone,
            'note' => $note,
        ];
    }
}
