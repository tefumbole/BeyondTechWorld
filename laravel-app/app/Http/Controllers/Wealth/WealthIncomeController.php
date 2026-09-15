<?php

namespace App\Http\Controllers\Wealth;

use App\Customer;
use App\Services\Wealth\WealthFilter;
use App\Services\Wealth\WealthIncomeAggregator;
use App\Support\WealthMoney;
use App\WealthIncome;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WealthIncomeController extends WealthBaseController
{
    public function index(Request $request, WealthIncomeAggregator $agg)
    {
        if (! $this->can('wealth.income.view')) {
            return $this->deny();
        }
        $filter = WealthFilter::fromRequest($request);
        $rows = $agg->query($filter)->with(['customer', 'user', 'category', 'program', 'biller'])
            ->orderByDesc('occurred_at')->paginate(40)->appends($request->query());

        return view('wealth.income', $this->lookups() + [
            'wmTab' => 'income',
            'filter' => $filter,
            'rows' => $rows,
            'customers' => Customer::where('is_active', 1)->orderBy('name')->get(),
            'total' => $agg->total($filter),
        ]);
    }

    public function store(Request $request)
    {
        if (! $this->can('wealth.income.create')) {
            return $this->deny();
        }
        $data = $request->validate([
            'title' => 'required|string|max:190',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:8',
            'occurred_at' => 'required|date',
            'biller_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'employee_id' => 'nullable|integer',
            'customer_id' => 'nullable|integer',
            'income_category_id' => 'nullable|integer',
            'program_id' => 'nullable|integer',
            'payment_method' => 'nullable|string|max:64',
            'reference' => 'nullable|string|max:128',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);
        $data['amount'] = WealthMoney::of($data['amount']);
        $data['source_type'] = $request->get('program_id') ? 'program' : 'manual';
        $data['status'] = 'posted';
        $data['created_by'] = Auth::id();
        $data['currency'] = $data['currency'] ?: 'XAF';
        $data['attachment'] = $this->storeWealthFile($request->file('attachment'), 'documents');
        WealthIncome::create($data);

        return redirect()->route('wealth.income')->with('message', 'Income recorded.');
    }

    public function show($id)
    {
        if (! $this->can('wealth.income.view')) {
            return $this->deny();
        }
        $row = WealthIncome::with(['customer', 'user', 'category', 'program', 'biller'])->findOrFail($id);

        return view('wealth.income_show', $this->lookups() + [
            'wmTab' => 'income',
            'row' => $row,
        ]);
    }

    public function update(Request $request, $id)
    {
        if (! $this->can('wealth.income.edit')) {
            return $this->deny();
        }
        $row = WealthIncome::findOrFail($id);
        if ($row->isAutomatic()) {
            return back()->with('not_permitted', 'Automatic income cannot be edited. Open the source transaction instead.');
        }
        $data = $request->validate([
            'title' => 'required|string|max:190',
            'amount' => 'required|numeric|min:0',
            'occurred_at' => 'required|date',
            'biller_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'customer_id' => 'nullable|integer',
            'income_category_id' => 'nullable|integer',
            'program_id' => 'nullable|integer',
            'payment_method' => 'nullable|string|max:64',
            'reference' => 'nullable|string|max:128',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);
        $data['amount'] = WealthMoney::of($data['amount']);
        $data['updated_by'] = Auth::id();
        $row->update($data);

        return redirect()->route('wealth.income.show', $row->id)->with('message', 'Income updated.');
    }
}
