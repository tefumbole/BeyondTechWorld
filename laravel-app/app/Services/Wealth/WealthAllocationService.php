<?php

namespace App\Services\Wealth;

use App\Support\WealthMoney;
use App\WealthAllocation;
use App\WealthAllocationBucket;
use App\WealthAllocationRule;

class WealthAllocationService
{
    protected $income;
    protected $expenses;

    public function __construct(WealthIncomeAggregator $income, WealthExpenseAggregator $expenses)
    {
        $this->income = $income;
        $this->expenses = $expenses;
    }

    public function activeRule($billerId = null)
    {
        $q = WealthAllocationRule::with('lines.bucket')->where('is_active', 1);
        if ($billerId) {
            $specific = (clone $q)->where('biller_id', $billerId)->first();
            if ($specific) {
                return $specific;
            }
        }

        return $q->whereNull('biller_id')->first();
    }

    public function qualifyingIncome(WealthFilter $filter, WealthAllocationRule $rule = null)
    {
        $gross = $this->income->total($filter);
        $rule = $rule ?: $this->activeRule($filter->billerId);
        if (! $rule || $rule->basis !== 'net') {
            return $gross;
        }
        $opsId = WealthAllocationBucket::where('code', 'OPERATIONS')->value('id');
        $opsSpend = $this->expenses->totalForBucket($filter, $opsId);

        return WealthMoney::cmp($gross, $opsSpend) === 1 ? WealthMoney::sub($gross, $opsSpend) : '0.00';
    }

    public function snapshot(WealthFilter $filter)
    {
        $rule = $this->activeRule($filter->billerId);
        $income = $this->qualifyingIncome($filter, $rule);
        $rows = [];
        if (! $rule) {
            return ['income' => $income, 'basis' => 'gross', 'rows' => $rows];
        }
        foreach ($rule->lines as $line) {
            $expected = WealthMoney::percentOf($income, $line->percent);
            $used = $this->expenses->totalForBucket($filter, $line->bucket_id);
            $remaining = WealthMoney::sub($expected, $used);
            $rows[] = [
                'bucket' => $line->bucket,
                'percent' => $line->percent,
                'expected' => $expected,
                'used' => $used,
                'remaining' => $remaining,
                'used_pct' => WealthMoney::ratio($used, $expected),
            ];
            WealthAllocation::updateOrCreate(
                [
                    'period_key' => $filter->periodKey(),
                    'entity_type' => $filter->entityType ?: 'all',
                    'entity_id' => $filter->billerId ?: $filter->userId ?: $filter->programId ?: 0,
                    'bucket_id' => $line->bucket_id,
                ],
                [
                    'expected_amount' => $expected,
                    'used_amount' => $used,
                    'remaining_amount' => $remaining,
                ]
            );
        }

        return ['income' => $income, 'basis' => $rule->basis, 'rows' => $rows, 'rule' => $rule];
    }

    public function bucketByCode($code)
    {
        return WealthAllocationBucket::where('code', $code)->first();
    }

    public function rowForCode(array $snapshot, $code)
    {
        foreach ($snapshot['rows'] as $row) {
            if ($row['bucket'] && $row['bucket']->code === $code) {
                return $row;
            }
        }

        return [
            'bucket' => $this->bucketByCode($code),
            'percent' => 0,
            'expected' => '0.00',
            'used' => '0.00',
            'remaining' => '0.00',
            'used_pct' => 0,
        ];
    }
}
