<?php

namespace App\Http\Controllers\Wealth;

use App\Services\Wealth\WealthAllocationService;
use App\Services\Wealth\WealthFilter;
use App\Support\WealthMoney;
use App\WealthInvestment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WealthInvestmentController extends WealthBaseController
{
    public function index(Request $request, WealthAllocationService $alloc)
    {
        if (! $this->can('wealth.investments.view')) {
            return $this->deny();
        }
        $filter = WealthFilter::fromRequest($request);
        $q = WealthInvestment::query()->orderByDesc('id');
        if ($filter->billerId) {
            $q->where('biller_id', $filter->billerId);
        }
        if ($filter->programId) {
            $q->where('program_id', $filter->programId);
        }
        $rows = $q->get();
        $snap = $alloc->snapshot($filter);
        $inv = $alloc->rowForCode($snap, 'INVESTMENT');
        $actual = WealthMoney::of($rows->sum('amount_invested'));
        $value = WealthMoney::of($rows->sum(function ($r) {
            return $r->current_value !== null ? $r->current_value : $r->amount_invested;
        }));

        return view('wealth.investments', $this->lookups() + [
            'wmTab' => 'investments',
            'filter' => $filter,
            'rows' => $rows,
            'alloc' => $inv,
            'actual' => $actual,
            'value' => $value,
            'gain' => WealthMoney::sub($value, $actual),
        ]);
    }

    public function store(Request $request)
    {
        if (! $this->can('wealth.investments.manage')) {
            return $this->deny();
        }
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'type' => 'nullable|string|max:64',
            'amount_invested' => 'required|numeric|min:0',
            'invested_on' => 'nullable|date',
            'biller_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'program_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'current_value' => 'nullable|numeric',
            'expected_return' => 'nullable|numeric',
            'status' => 'required|in:planned,active,matured,sold,cancelled',
            'notes' => 'nullable|string',
        ]);
        $data['amount_invested'] = WealthMoney::of($data['amount_invested']);
        if (isset($data['current_value'])) {
            $data['current_value'] = WealthMoney::of($data['current_value']);
        }
        $data['created_by'] = Auth::id();
        $data['document'] = $this->storeWealthFile($request->file('document'), 'documents');
        WealthInvestment::create($data);

        return back()->with('message', 'Investment recorded.');
    }

    public function update(Request $request, $id)
    {
        if (! $this->can('wealth.investments.manage')) {
            return $this->deny();
        }
        $row = WealthInvestment::findOrFail($id);
        $data = $request->validate([
            'current_value' => 'nullable|numeric',
            'expected_return' => 'nullable|numeric',
            'status' => 'required|in:planned,active,matured,sold,cancelled',
            'notes' => 'nullable|string',
        ]);
        if (array_key_exists('current_value', $data) && $data['current_value'] !== null) {
            $data['current_value'] = WealthMoney::of($data['current_value']);
        }
        if (array_key_exists('expected_return', $data) && $data['expected_return'] !== null) {
            $data['expected_return'] = WealthMoney::of($data['expected_return']);
        }
        $data['updated_by'] = Auth::id();
        $row->update($data);

        return back()->with('message', 'Investment updated.');
    }
}
