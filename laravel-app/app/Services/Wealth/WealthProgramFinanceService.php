<?php

namespace App\Services\Wealth;

use App\Expense;
use App\Support\WealthMoney;
use App\WealthIncome;
use App\WealthProgram;
use App\WealthProgramBudgetLine;

class WealthProgramFinanceService
{
    public function forProgram(WealthProgram $program, WealthFilter $filter = null)
    {
        $incomeQ = WealthIncome::where('program_id', $program->id)->where('status', 'posted');
        $expQ = Expense::where('program_id', $program->id);
        if ($filter) {
            $incomeQ->whereDate('occurred_at', '>=', $filter->startDate)->whereDate('occurred_at', '<=', $filter->endDate);
            $expQ->whereDate('created_at', '>=', $filter->startDate)->whereDate('created_at', '<=', $filter->endDate);
        }
        $income = WealthMoney::of($incomeQ->sum('amount'));
        $expenses = WealthMoney::of($expQ->sum('amount'));
        $budget = WealthMoney::of($program->proposed_budget);
        $balance = WealthMoney::sub($income, $expenses);
        $spentPct = WealthMoney::ratio($expenses, $budget);
        $remainPct = max(0, 100 - $spentPct);

        $lines = [];
        foreach ($program->budgetLines as $line) {
            $actual = $this->lineActual($program->id, $line, $filter);
            $usedPct = WealthMoney::ratio($actual, $line->budget_amount);
            $status = 'normal';
            if ($usedPct >= (float) $line->critical_percent) {
                $status = 'over';
            } elseif ($usedPct >= (float) $line->warn_percent) {
                $status = 'approaching';
            }
            $lines[] = [
                'line' => $line,
                'actual' => $actual,
                'variance' => WealthMoney::sub($line->budget_amount, $actual),
                'used_pct' => $usedPct,
                'status' => $status,
            ];
        }

        return [
            'income' => $income,
            'expenses' => $expenses,
            'balance' => $balance,
            'budget' => $budget,
            'budget_used' => $expenses,
            'budget_remaining' => WealthMoney::sub($budget, $expenses),
            'spent_pct' => $spentPct,
            'remain_pct' => $remainPct,
            'profit' => $balance,
            'lines' => $lines,
        ];
    }

    protected function lineActual($programId, WealthProgramBudgetLine $line, WealthFilter $filter = null)
    {
        $q = Expense::where('program_id', $programId);
        if ($line->subcategory_id) {
            $q->where('wealth_subcategory_id', $line->subcategory_id);
        } else {
            $q->where(function ($w) use ($line) {
                $w->where('title', 'like', '%'.$line->name.'%')
                    ->orWhere('note', 'like', '%'.$line->name.'%');
            });
        }
        if ($filter) {
            $q->whereDate('created_at', '>=', $filter->startDate)->whereDate('created_at', '<=', $filter->endDate);
        }

        return WealthMoney::of($q->sum('amount'));
    }
}
