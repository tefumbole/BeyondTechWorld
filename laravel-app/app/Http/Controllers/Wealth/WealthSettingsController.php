<?php

namespace App\Http\Controllers\Wealth;

use App\WealthAllocationBucket;
use App\WealthAllocationRule;
use App\WealthAllocationRuleLine;
use App\WealthExpenseSubcategory;
use App\WealthHealthSetting;
use App\WealthIncomeCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WealthSettingsController extends WealthBaseController
{
    public function index()
    {
        if (! $this->can('wealth.settings.manage')) {
            return $this->deny();
        }
        $rule = WealthAllocationRule::with('lines.bucket')->whereNull('biller_id')->where('is_active', 1)->first();
        $health = WealthHealthSetting::whereNull('biller_id')->first();

        return view('wealth.settings', $this->lookups() + [
            'wmTab' => 'settings',
            'rule' => $rule,
            'health' => $health,
        ]);
    }

    public function saveRules(Request $request)
    {
        if (! $this->can('wealth.settings.manage')) {
            return $this->deny();
        }
        $lines = $request->get('percent', []);
        $sum = 0;
        foreach ($lines as $pct) {
            $sum += (float) $pct;
        }
        if (abs($sum - 100) > 0.05) {
            return back()->with('not_permitted', 'Active allocation percentages must total 100%. Currently '.$sum.'%.');
        }
        $rule = WealthAllocationRule::whereNull('biller_id')->where('is_active', 1)->first();
        if (! $rule) {
            $rule = WealthAllocationRule::create(['basis' => $request->get('basis', 'gross'), 'is_active' => 1]);
        } else {
            $rule->basis = $request->get('basis', 'gross');
            $rule->save();
        }
        foreach ($lines as $bucketId => $pct) {
            WealthAllocationRuleLine::updateOrCreate(
                ['rule_id' => $rule->id, 'bucket_id' => $bucketId],
                ['percent' => number_format((float) $pct, 2, '.', '')]
            );
        }

        return back()->with('message', 'Allocation rules saved.');
    }

    public function saveHealth(Request $request)
    {
        if (! $this->can('wealth.settings.manage')) {
            return $this->deny();
        }
        $row = WealthHealthSetting::whereNull('biller_id')->first() ?: new WealthHealthSetting();
        $row->fill($request->only([
            'critical_max', 'attention_max', 'fair_max', 'very_good_max',
            'weight_income_expense', 'weight_cash', 'weight_operations', 'weight_investment',
            'weight_charity', 'weight_savings', 'weight_budget',
            'ops_over_warn_pct', 'budget_warn_pct', 'cash_low_pct',
        ]));
        $row->save();

        return back()->with('message', 'Financial Health settings saved.');
    }

    public function saveCategory(Request $request)
    {
        if (! $this->can('wealth.settings.manage')) {
            return $this->deny();
        }
        $kind = $request->get('kind');
        if ($kind === 'income') {
            WealthIncomeCategory::create([
                'code' => strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $request->get('name'))),
                'name' => $request->get('name'),
                'is_active' => 1,
            ]);
        } else {
            WealthExpenseSubcategory::create([
                'bucket_id' => $request->get('bucket_id'),
                'code' => strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $request->get('name'))),
                'name' => $request->get('name'),
                'is_active' => 1,
            ]);
        }

        return back()->with('message', 'Category saved.');
    }
}
