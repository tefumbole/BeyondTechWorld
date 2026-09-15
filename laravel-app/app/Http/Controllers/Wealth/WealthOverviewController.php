<?php

namespace App\Http\Controllers\Wealth;

use App\Services\Wealth\FinancialHealthService;
use App\Services\Wealth\WealthAllocationService;
use App\Services\Wealth\WealthExpenseAggregator;
use App\Services\Wealth\WealthFilter;
use App\Services\Wealth\WealthIncomeAggregator;
use App\Services\Wealth\WealthProgramFinanceService;
use App\Support\WealthMoney;
use App\WealthProgram;
use Illuminate\Http\Request;

class WealthOverviewController extends WealthBaseController
{
    public function index(
        Request $request,
        WealthIncomeAggregator $income,
        WealthExpenseAggregator $expenses,
        WealthAllocationService $alloc,
        FinancialHealthService $health,
        WealthProgramFinanceService $programs
    ) {
        if (! $this->canAny(['wealth.view', 'expenses-index'])) {
            return $this->deny();
        }
        $filter = WealthFilter::fromRequest($request);
        $lookups = $this->lookups();
        $incomeTotal = $income->total($filter);
        $expenseTotal = $expenses->total($filter);
        $snapshot = $alloc->snapshot($filter);
        $healthData = $health->calculate($filter);
        $programCards = [];
        foreach (WealthProgram::whereIn('status', ['planning', 'active'])->orderBy('name')->limit(8)->get() as $program) {
            $programCards[] = ['program' => $program, 'finance' => $programs->forProgram($program, $filter)];
        }

        return view('wealth.overview', $lookups + [
            'wmTab' => 'overview',
            'filter' => $filter,
            'incomeTotal' => $incomeTotal,
            'expenseTotal' => $expenseTotal,
            'balance' => WealthMoney::sub($incomeTotal, $expenseTotal),
            'snapshot' => $snapshot,
            'ops' => $alloc->rowForCode($snapshot, 'OPERATIONS'),
            'inv' => $alloc->rowForCode($snapshot, 'INVESTMENT'),
            'cha' => $alloc->rowForCode($snapshot, 'CHARITY'),
            'health' => $healthData,
            'recentIncome' => $income->recent($filter, 8),
            'recentExpenses' => $expenses->recent($filter, 8),
            'topCategories' => $expenses->byCategory($filter),
            'monthlyIncome' => $income->monthly($filter),
            'monthlyExpense' => $expenses->monthly($filter),
            'programCards' => $programCards,
            'unclassified' => $expenses->unclassifiedCount(),
        ]);
    }

    public function apiOverview(
        Request $request,
        WealthIncomeAggregator $income,
        WealthExpenseAggregator $expenses,
        WealthAllocationService $alloc,
        FinancialHealthService $health
    ) {
        if (! $this->canAny(['wealth.view', 'expenses-index'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $filter = WealthFilter::fromRequest($request);
        $in = $income->total($filter);
        $ex = $expenses->total($filter);

        return response()->json([
            'income' => $in,
            'expenses' => $ex,
            'balance' => WealthMoney::sub($in, $ex),
            'allocation' => $alloc->snapshot($filter),
            'health' => $health->calculate($filter),
            'monthly_income' => $income->monthly($filter),
            'monthly_expense' => $expenses->monthly($filter),
        ]);
    }

    public function apiHealth(Request $request, FinancialHealthService $health)
    {
        if (! $this->canAny(['wealth.view', 'wealth.reports.view'])) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json($health->calculate(WealthFilter::fromRequest($request)));
    }

    public function syncIncome(\App\Services\Wealth\WealthIncomeSync $sync)
    {
        if (! $this->can('wealth.settings.manage')) {
            return $this->deny();
        }
        $n = $sync->syncAllPaidSales();

        return back()->with('message', 'Synced '.$n.' paid sales into Wealth income.');
    }
}
