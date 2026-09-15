<?php

namespace App\Services\Wealth;

use App\Expense;
use App\Support\WealthMoney;
use App\WealthIncome;

class WealthReportService
{
    protected $income;
    protected $expenses;
    protected $alloc;
    protected $health;

    public function __construct(
        WealthIncomeAggregator $income,
        WealthExpenseAggregator $expenses,
        WealthAllocationService $alloc,
        FinancialHealthService $health
    ) {
        $this->income = $income;
        $this->expenses = $expenses;
        $this->alloc = $alloc;
        $this->health = $health;
    }

    public function summary(WealthFilter $filter)
    {
        $income = $this->income->total($filter);
        $expense = $this->expenses->total($filter);

        return [
            'income' => $income,
            'expenses' => $expense,
            'balance' => WealthMoney::sub($income, $expense),
            'allocation' => $this->alloc->snapshot($filter),
            'health' => $this->health->calculate($filter),
            'income_rows' => $this->income->query($filter)->with(['customer', 'category'])->orderByDesc('occurred_at')->limit(500)->get(),
            'expense_rows' => $this->expenses->query($filter)->with(['expenseCategory'])->orderByDesc('id')->limit(500)->get(),
            'monthly_income' => $this->income->monthly($filter),
            'monthly_expense' => $this->expenses->monthly($filter),
        ];
    }
}
