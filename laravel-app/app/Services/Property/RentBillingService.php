<?php

namespace App\Services\Property;

use App\Property\RentObligation;
use App\Property\RentPayment;
use App\Property\Tenancy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RentBillingService
{
    protected $log;

    public function __construct(PropertyActivityLogger $log)
    {
        $this->log = $log;
    }

    public function generate($asOf = null)
    {
        $day = $asOf ? Carbon::parse($asOf) : Carbon::today();
        $created = 0;
        $tenancies = Tenancy::where('status', 'ACTIVE')->get();
        foreach ($tenancies as $tenancy) {
            if ($tenancy->start_date && Carbon::parse($tenancy->start_date)->gt($day)) {
                continue;
            }
            if ($tenancy->end_date && Carbon::parse($tenancy->end_date)->lt($day)) {
                continue;
            }
            $period = $this->period($tenancy, $day);
            if (! $period) {
                continue;
            }
            $exists = RentObligation::where('tenancy_id', $tenancy->id)->where('period_key', $period['key'])->exists();
            if ($exists) {
                continue;
            }
            try {
                RentObligation::create([
                    'tenancy_id' => $tenancy->id,
                    'period_key' => $period['key'],
                    'period_start' => $period['start'],
                    'period_end' => $period['end'],
                    'due_date' => $period['due'],
                    'amount_due' => $tenancy->rent_amount,
                    'amount_paid' => 0,
                    'status' => $period['due'] < $day->toDateString() ? 'OVERDUE' : ($period['due'] === $day->toDateString() ? 'DUE' : 'UPCOMING'),
                ]);
                $created++;
            } catch (\Throwable $e) {
                continue;
            }
        }
        $this->log->write('rent_generated', 'rent', null, ['created' => $created, 'as_of' => $day->toDateString()]);

        return $created;
    }

    public function refresh(RentObligation $row, $today = null)
    {
        $today = $today ? Carbon::parse($today)->toDateString() : Carbon::today()->toDateString();
        if (in_array($row->status, ['PAID', 'WAIVED', 'CANCELLED'], true)) {
            return $row;
        }
        $balance = $row->balance();
        if ($balance <= 0) {
            $row->status = 'PAID';
        } elseif ((float) $row->amount_paid > 0) {
            $row->status = 'PARTIALLY_PAID';
        } elseif ($row->due_date < $today) {
            $row->status = 'OVERDUE';
        } elseif ($row->due_date === $today) {
            $row->status = 'DUE';
        } else {
            $row->status = 'UPCOMING';
        }
        $row->save();

        return $row;
    }

    public function balance(Tenancy $tenancy)
    {
        $rows = RentObligation::where('tenancy_id', $tenancy->id)->orderBy('due_date')->get();
        $open = 0.0;
        $next = null;
        foreach ($rows as $row) {
            $row = $this->refresh($row);
            if (in_array($row->status, ['WAIVED', 'CANCELLED', 'PAID'], true)) {
                continue;
            }
            $open += $row->balance();
            if ($next === null) {
                $next = $row;
            }
        }

        return [
            'amount' => round($open, 2),
            'currency' => (string) config('services.property.currency', 'XAF'),
            'next' => $next,
        ];
    }

    public function recordPayment(Tenancy $tenancy, $amount, $reference, $userId = null)
    {
        $amount = round((float) $amount, 2);
        if ($amount <= 0) {
            return ['ok' => false, 'error' => 'bad_amount'];
        }
        $payment = DB::transaction(function () use ($tenancy, $amount, $reference, $userId) {
            $left = $amount;
            $firstId = null;
            $rows = RentObligation::where('tenancy_id', $tenancy->id)->orderBy('due_date')->lockForUpdate()->get();
            foreach ($rows as $row) {
                if ($left <= 0) {
                    break;
                }
                $this->refresh($row);
                if (in_array($row->status, ['PAID', 'WAIVED', 'CANCELLED'], true)) {
                    continue;
                }
                $room = $row->balance();
                if ($room <= 0) {
                    continue;
                }
                $apply = min($room, $left);
                $row->amount_paid = round((float) $row->amount_paid + $apply, 2);
                $this->refresh($row);
                $left = round($left - $apply, 2);
                if ($firstId === null) {
                    $firstId = $row->id;
                }
            }
            $payment = RentPayment::create([
                'tenancy_id' => $tenancy->id,
                'rent_obligation_id' => $firstId,
                'amount' => $amount,
                'currency' => (string) config('services.property.currency', 'XAF'),
                'paid_on' => Carbon::today()->toDateString(),
                'source' => 'erp',
                'reference' => $reference,
                'recorded_by' => $userId,
            ]);

            return $payment;
        });
        $this->log->write('rent_payment_recorded', 'rent_payment', $payment->id, [
            'tenancy_id' => $tenancy->id,
            'amount' => $amount,
        ]);

        return ['ok' => true, 'payment' => $payment];
    }

    protected function period(Tenancy $tenancy, Carbon $day)
    {
        $frequency = strtolower((string) $tenancy->billing_frequency);
        $dueDay = max(1, min(28, (int) $tenancy->due_day));
        if ($frequency === 'yearly') {
            $start = $day->copy()->startOfYear();

            return [
                'key' => $start->format('Y'),
                'start' => $start->toDateString(),
                'end' => $start->copy()->endOfYear()->toDateString(),
                'due' => $start->copy()->month(1)->day($dueDay)->toDateString(),
            ];
        }
        if ($frequency !== 'monthly') {
            return null;
        }
        $start = $day->copy()->startOfMonth();

        return [
            'key' => $start->format('Y-m'),
            'start' => $start->toDateString(),
            'end' => $start->copy()->endOfMonth()->toDateString(),
            'due' => $start->copy()->day($dueDay)->toDateString(),
        ];
    }
}
