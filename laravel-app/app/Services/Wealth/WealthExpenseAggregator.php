<?php

namespace App\Services\Wealth;

use App\Expense;
use App\Support\WealthMoney;
use App\WealthAllocationBucket;

class WealthExpenseAggregator
{
    public function query(WealthFilter $filter)
    {
        $q = Expense::query();

        return $filter->applyExpense($q);
    }

    public function total(WealthFilter $filter)
    {
        return WealthMoney::of($this->query($filter)->sum('amount'));
    }

    public function totalForBucket(WealthFilter $filter, $bucketId)
    {
        if (! $bucketId) {
            return '0.00';
        }

        return WealthMoney::of($this->query($filter)->where('allocation_bucket_id', $bucketId)->sum('amount'));
    }

    public function unclassifiedCount()
    {
        return Expense::whereNull('allocation_bucket_id')->count();
    }

    public function byBucket(WealthFilter $filter)
    {
        $out = [];
        foreach (WealthAllocationBucket::where('is_active', 1)->orderBy('sort_order')->get() as $bucket) {
            $out[] = [
                'bucket' => $bucket,
                'total' => $this->totalForBucket($filter, $bucket->id),
            ];
        }

        return $out;
    }

    public function byCategory(WealthFilter $filter)
    {
        return $this->query($filter)
            ->selectRaw('expense_category_id, SUM(amount) as total')
            ->groupBy('expense_category_id')
            ->orderByDesc('total')
            ->limit(8)
            ->get();
    }

    public function recent(WealthFilter $filter, $limit = 10)
    {
        return $this->query($filter)->with(['expenseCategory'])->orderByDesc('id')->limit($limit)->get();
    }

    public function monthly(WealthFilter $filter)
    {
        $rows = $this->query($filter)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')->orderBy('ym')->get();
        $out = [];
        foreach ($rows as $row) {
            $out[$row->ym] = WealthMoney::of($row->total);
        }

        return $out;
    }
}
