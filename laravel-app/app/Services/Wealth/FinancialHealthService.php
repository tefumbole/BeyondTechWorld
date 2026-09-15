<?php

namespace App\Services\Wealth;

use App\Support\WealthMoney;
use App\WealthHealthSetting;
use App\WealthProgram;

class FinancialHealthService
{
    protected $income;
    protected $expenses;
    protected $alloc;
    protected $programs;

    public function __construct(
        WealthIncomeAggregator $income,
        WealthExpenseAggregator $expenses,
        WealthAllocationService $alloc,
        WealthProgramFinanceService $programs
    ) {
        $this->income = $income;
        $this->expenses = $expenses;
        $this->alloc = $alloc;
        $this->programs = $programs;
    }

    public function calculate(WealthFilter $filter)
    {
        $settings = $this->settings($filter->billerId);
        $income = $this->income->total($filter);
        $expense = $this->expenses->total($filter);
        $balance = WealthMoney::sub($income, $expense);
        $snap = $this->alloc->snapshot($filter);
        $ops = $this->alloc->rowForCode($snap, 'OPERATIONS');
        $inv = $this->alloc->rowForCode($snap, 'INVESTMENT');
        $cha = $this->alloc->rowForCode($snap, 'CHARITY');

        $expenseRatio = WealthMoney::ratio($expense, $income);
        $cashRatio = WealthMoney::ratio($balance, $income);
        $incomePts = $this->incomeExpensePoints($expenseRatio, $income, $settings);
        $cashPts = $this->cashPoints($balance, $settings);
        $opsPts = $this->allocationSpendPoints($ops, $settings->weight_operations);
        $invPts = $this->fulfillmentPoints($inv['used_pct'], $settings->weight_investment);
        $chaPts = $this->fulfillmentPoints($cha['used_pct'], $settings->weight_charity);
        $savPts = $this->savingsPoints($cashRatio, $settings);
        $budget = $this->budgetHealth($filter, $settings);

        $score = (int) round($incomePts + $cashPts + $opsPts + $invPts + $chaPts + $savPts + $budget['points']);
        $score = max(0, min(100, $score));
        $status = $this->statusFor($score, $settings);

        $prev = $this->calculateScoreOnly($filter->previousPeriod());
        $change = $score - $prev;
        $trend = 'STABLE';
        if ($change >= 3) {
            $trend = 'IMPROVING';
        } elseif ($change <= -3) {
            $trend = 'DECLINING';
        }

        $payload = [
            'overall_score' => $score,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'trend' => $trend,
            'previous_score' => $prev,
            'score_change' => $change,
            'income' => ['total' => $income],
            'expenses' => ['total' => $expense, 'ratio' => $expenseRatio],
            'cash' => ['balance' => $balance, 'ratio' => $cashRatio],
            'operations' => $ops + ['fulfillment_percentage' => $ops['used_pct']],
            'investment' => $inv + ['fulfillment_percentage' => $inv['used_pct']],
            'charity' => $cha + ['fulfillment_percentage' => $cha['used_pct']],
            'savings' => ['balance' => $balance, 'ratio' => $cashRatio],
            'budget_health' => $budget,
            'points' => compact('incomePts', 'cashPts', 'opsPts', 'invPts', 'chaPts', 'savPts') + ['budgetPts' => $budget['points']],
        ];
        $payload['warnings'] = $this->generateWarnings($payload, $settings);
        $payload['recommendations'] = $this->generateRecommendations($payload);

        return $payload;
    }

    public function history(WealthFilter $filter)
    {
        $points = [];
        $end = strtotime(date('Y-m-01', strtotime($filter->endDate)));
        $cursor = strtotime('-11 months', $end);
        $i = 0;
        while ($cursor <= $end && $i < 12) {
            $monthFilter = clone $filter;
            $monthFilter->startDate = date('Y-m-01', $cursor);
            $monthFilter->endDate = date('Y-m-t', $cursor);
            $monthFilter->month = (int) date('n', $cursor);
            $monthFilter->year = (int) date('Y', $cursor);
            $points[] = [
                'label' => date('M Y', $cursor),
                'score' => $this->calculateScoreOnly($monthFilter),
            ];
            $cursor = strtotime('+1 month', $cursor);
            $i++;
        }

        return $points;
    }

    protected function calculateScoreOnly(WealthFilter $filter)
    {
        $settings = $this->settings($filter->billerId);
        $income = $this->income->total($filter);
        $expense = $this->expenses->total($filter);
        $balance = WealthMoney::sub($income, $expense);
        $snap = $this->alloc->snapshot($filter);
        $ops = $this->alloc->rowForCode($snap, 'OPERATIONS');
        $inv = $this->alloc->rowForCode($snap, 'INVESTMENT');
        $cha = $this->alloc->rowForCode($snap, 'CHARITY');
        $expenseRatio = WealthMoney::ratio($expense, $income);
        $cashRatio = WealthMoney::ratio($balance, $income);
        $score = $this->incomeExpensePoints($expenseRatio, $income, $settings)
            + $this->cashPoints($balance, $settings)
            + $this->allocationSpendPoints($ops, $settings->weight_operations)
            + $this->fulfillmentPoints($inv['used_pct'], $settings->weight_investment)
            + $this->fulfillmentPoints($cha['used_pct'], $settings->weight_charity)
            + $this->savingsPoints($cashRatio, $settings)
            + $this->budgetHealth($filter, $settings)['points'];

        return max(0, min(100, (int) round($score)));
    }

    protected function settings($billerId)
    {
        if ($billerId) {
            $row = WealthHealthSetting::where('biller_id', $billerId)->first();
            if ($row) {
                return $row;
            }
        }

        return WealthHealthSetting::whereNull('biller_id')->first() ?: new WealthHealthSetting();
    }

    protected function incomeExpensePoints($ratio, $income, $settings)
    {
        $max = $settings->weight_income_expense ?: 25;
        if ((float) $income == 0.0) {
            return $max * 0.4;
        }
        if ($ratio > 100) {
            return 0;
        }
        if ($ratio <= 60) {
            return $max;
        }
        if ($ratio <= 80) {
            return $max * 0.7;
        }
        if ($ratio <= 95) {
            return $max * 0.4;
        }

        return $max * 0.15;
    }

    protected function cashPoints($balance, $settings)
    {
        $max = $settings->weight_cash ?: 20;
        if (WealthMoney::cmp($balance, '0') === 1) {
            return $max;
        }
        if (WealthMoney::cmp($balance, '0') === 0) {
            return $max * 0.4;
        }

        return 0;
    }

    protected function allocationSpendPoints(array $row, $max)
    {
        $pct = (float) $row['used_pct'];
        if ((float) $row['expected'] == 0.0) {
            return $max * 0.5;
        }
        if ($pct > 110) {
            return $max * 0.2;
        }
        if ($pct > 100) {
            return $max * 0.5;
        }
        if ($pct >= 50) {
            return $max;
        }
        if ($pct >= 25) {
            return $max * 0.7;
        }

        return $max * 0.45;
    }

    protected function fulfillmentPoints($pct, $max)
    {
        $pct = (float) $pct;
        if ($pct >= 100) {
            return $max;
        }
        if ($pct >= 75) {
            return $max * 0.85;
        }
        if ($pct >= 50) {
            return $max * 0.6;
        }
        if ($pct >= 25) {
            return $max * 0.35;
        }

        return $max * 0.1;
    }

    protected function savingsPoints($cashRatio, $settings)
    {
        $max = $settings->weight_savings ?: 10;
        if ($cashRatio >= 20) {
            return $max;
        }
        if ($cashRatio >= 10) {
            return $max * 0.7;
        }
        if ($cashRatio > 0) {
            return $max * 0.35;
        }

        return 0;
    }

    protected function budgetHealth(WealthFilter $filter, $settings)
    {
        $max = $settings->weight_budget ?: 5;
        $q = WealthProgram::query();
        if ($filter->programId) {
            $q->where('id', $filter->programId);
        } elseif ($filter->entityType === 'program') {
            $q->where('status', 'active');
        } else {
            $q->where('status', 'active');
        }
        $programs = $q->with('budgetLines')->limit(20)->get();
        if ($programs->isEmpty()) {
            return ['points' => $max * 0.6, 'used_pct' => 0, 'over' => false, 'items' => []];
        }
        $items = [];
        $worst = 0;
        $over = false;
        foreach ($programs as $program) {
            $fin = $this->programs->forProgram($program, $filter);
            $items[] = ['program' => $program, 'finance' => $fin];
            $worst = max($worst, (float) $fin['spent_pct']);
            if ((float) $fin['spent_pct'] > 100) {
                $over = true;
            }
        }
        $pts = $max;
        if ($over) {
            $pts = 0;
        } elseif ($worst >= 90) {
            $pts = $max * 0.4;
        } elseif ($worst >= 75) {
            $pts = $max * 0.7;
        }

        return ['points' => $pts, 'used_pct' => $worst, 'over' => $over, 'items' => $items];
    }

    protected function statusFor($score, $settings)
    {
        if ($score <= ($settings->critical_max ?: 39)) {
            return 'CRITICAL';
        }
        if ($score <= ($settings->attention_max ?: 59)) {
            return 'NEEDS_ATTENTION';
        }
        if ($score <= ($settings->fair_max ?: 74)) {
            return 'FAIR';
        }
        if ($score <= ($settings->very_good_max ?: 89)) {
            return 'VERY_GOOD';
        }

        return 'EXCELLENT';
    }

    protected function statusLabel($status)
    {
        $map = [
            'CRITICAL' => 'Critical',
            'NEEDS_ATTENTION' => 'Needs Attention',
            'FAIR' => 'Fair',
            'VERY_GOOD' => 'Very Good',
            'EXCELLENT' => 'Excellent',
        ];

        return $map[$status] ?? $status;
    }

    protected function generateWarnings(array $p, $settings)
    {
        $w = [];
        if ((float) $p['expenses']['ratio'] >= 90 && (float) $p['income']['total'] > 0) {
            $w[] = ['severity' => (float) $p['expenses']['ratio'] > 100 ? 'CRITICAL' : 'WARNING', 'message' => 'Your expenses have reached '.$p['expenses']['ratio'].'% of your income this period.'];
        }
        if (WealthMoney::cmp($p['cash']['balance'], '0') === -1) {
            $w[] = ['severity' => 'CRITICAL', 'message' => 'Your expenses are currently higher than your income.'];
        }
        if ((float) $p['operations']['used_pct'] > 100) {
            $over = WealthMoney::sub($p['operations']['used'], $p['operations']['expected']);
            $w[] = ['severity' => 'WARNING', 'message' => 'Your Operations allocation has been exceeded by '.$over.' FCFA.'];
        }
        if ((float) $p['investment']['fulfillment_percentage'] < 50) {
            $w[] = ['severity' => 'WARNING', 'message' => 'You have only fulfilled '.$p['investment']['fulfillment_percentage'].'% of your Investment target.'];
        }
        if ((float) $p['charity']['remaining'] > 0 && (float) $p['charity']['fulfillment_percentage'] < 100) {
            $w[] = ['severity' => 'INFO', 'message' => 'Charity allocation has '.$p['charity']['remaining'].' FCFA remaining.'];
        }
        if ((float) $p['cash']['ratio'] < ($settings->cash_low_pct ?: 10) && (float) $p['income']['total'] > 0) {
            $w[] = ['severity' => 'WARNING', 'message' => 'Your available cash balance has fallen below '.($settings->cash_low_pct ?: 10).'% of income.'];
        }
        foreach ($p['budget_health']['items'] as $item) {
            $fin = $item['finance'];
            $name = $item['program']->name;
            if ((float) $fin['spent_pct'] >= 90) {
                $w[] = ['severity' => (float) $fin['spent_pct'] > 100 ? 'CRITICAL' : 'WARNING', 'message' => $name.' has used '.$fin['spent_pct'].'% of its approved budget.'];
            }
        }

        return $w;
    }

    protected function generateRecommendations(array $p)
    {
        $r = [];
        if ((float) $p['investment']['fulfillment_percentage'] < 50 && (float) $p['investment']['remaining'] > 0) {
            $r[] = 'You have invested only '.$p['investment']['fulfillment_percentage'].'% of your expected investment allocation. Consider directing another '.$p['investment']['remaining'].' FCFA toward investments.';
        }
        if ((float) $p['operations']['used_pct'] >= 95) {
            $r[] = 'You have used '.$p['operations']['used_pct'].'% of your Operations allocation. Reduce non-essential spending until new income is received.';
        }
        if ((float) $p['charity']['remaining'] > 0 && (float) $p['charity']['fulfillment_percentage'] < 100) {
            $r[] = 'You have '.$p['charity']['remaining'].' FCFA remaining in your Charity allocation.';
        }
        if (WealthMoney::cmp($p['cash']['balance'], '0') === -1) {
            $r[] = 'Current expenses exceed recorded income. Review operating expenses and outstanding receivables.';
        }

        return $r;
    }
}
