<?php

namespace App\Services\Wealth;

use App\Support\WealthMoney;
use App\WealthIncome;

class WealthIncomeAggregator
{
    public function query(WealthFilter $filter)
    {
        $q = WealthIncome::query()->where('status', 'posted');

        return $filter->applyIncome($q);
    }

    public function total(WealthFilter $filter)
    {
        return WealthMoney::of($this->query($filter)->sum('amount'));
    }

    public function recent(WealthFilter $filter, $limit = 10)
    {
        return $this->query($filter)->with(['customer', 'user', 'category', 'program'])
            ->orderByDesc('occurred_at')->limit($limit)->get();
    }

    public function monthly(WealthFilter $filter)
    {
        $rows = $this->query($filter)
            ->selectRaw("DATE_FORMAT(occurred_at, '%Y-%m') as ym, SUM(amount) as total")
            ->groupBy('ym')->orderBy('ym')->get();
        $out = [];
        foreach ($rows as $row) {
            $out[$row->ym] = WealthMoney::of($row->total);
        }

        return $out;
    }

    public function byCategory(WealthFilter $filter)
    {
        return $this->query($filter)
            ->selectRaw('income_category_id, SUM(amount) as total')
            ->groupBy('income_category_id')
            ->with('category')
            ->get();
    }
}
