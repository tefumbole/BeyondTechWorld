<?php

namespace App\Http\Controllers\Wealth;

use App\Account;
use App\CashRegister;
use App\Expense;
use App\ExpenseCategory;
use App\Services\Wealth\WealthExpenseAggregator;
use App\Services\Wealth\WealthFilter;
use App\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WealthExpensePageController extends WealthBaseController
{
    public function index(Request $request, WealthExpenseAggregator $agg)
    {
        if (! $this->canAny(['wealth.expenses.view', 'expenses-index'])) {
            return $this->deny();
        }
        $filter = WealthFilter::fromRequest($request);
        $q = $agg->query($filter)->with(['expenseCategory', 'warehouse']);
        if ($request->get('unclassified')) {
            $q->whereNull('allocation_bucket_id');
        }
        $rows = $q->orderByDesc('id')->paginate(40)->appends($request->query());

        return view('wealth.expenses', $this->lookups() + [
            'wmTab' => 'expenses',
            'filter' => $filter,
            'rows' => $rows,
            'total' => $agg->total($filter),
            'unclassified' => $agg->unclassifiedCount(),
            'categories' => ExpenseCategory::where('is_active', 1)->orderBy('name')->get(),
            'warehouses' => Warehouse::where('is_active', 1)->get(),
            'accounts' => Account::where('is_active', 1)->get(),
        ]);
    }

    public function store(Request $request)
    {
        if (! $this->canAny(['wealth.expenses.create', 'expenses-add'])) {
            return $this->deny();
        }
        $data = $request->validate([
            'title' => 'nullable|string|max:190',
            'amount' => 'required|numeric|min:0',
            'expense_category_id' => 'required|integer',
            'warehouse_id' => 'required|integer',
            'account_id' => 'nullable|integer',
            'allocation_bucket_id' => 'nullable|integer',
            'wealth_subcategory_id' => 'nullable|integer',
            'program_id' => 'nullable|integer',
            'biller_id' => 'nullable|integer',
            'employee_id' => 'nullable|integer',
            'entity_type' => 'nullable|string|max:24',
            'payee' => 'nullable|string|max:190',
            'payment_method' => 'nullable|string|max:64',
            'note' => 'nullable|string',
            'category_id' => 'nullable|integer',
        ]);
        $data['reference_no'] = 'er-'.date('Ymd').'-'.date('his');
        $data['user_id'] = Auth::id();
        $data['created_by'] = Auth::id();
        if (empty($data['allocation_bucket_id'])) {
            $cat = ExpenseCategory::find($data['expense_category_id']);
            if ($cat && $cat->allocation_bucket_id) {
                $data['allocation_bucket_id'] = $cat->allocation_bucket_id;
                if (empty($data['wealth_subcategory_id'])) {
                    $data['wealth_subcategory_id'] = $cat->wealth_subcategory_id;
                }
            }
        }
        $reg = CashRegister::where('user_id', Auth::id())
            ->where('warehouse_id', $data['warehouse_id'])
            ->where('status', true)->first();
        if ($reg) {
            $data['cash_register_id'] = $reg->id;
        }
        $data['document'] = $this->storeWealthFile($request->file('document'), 'documents');
        $expense = Expense::create($data);
        $this->maybeRegister($expense, $request);

        return redirect()->route('wealth.expenses')->with('message', 'Expense recorded.');
    }

    public function classify(Request $request)
    {
        if (! $this->canAny(['wealth.expenses.edit', 'wealth.settings.manage', 'expenses-edit'])) {
            return $this->deny();
        }
        $ids = $request->get('expense_ids', []);
        $bucket = $request->get('allocation_bucket_id');
        if (! $ids || ! $bucket) {
            return back()->with('not_permitted', 'Select expenses and an allocation category.');
        }
        Expense::whereIn('id', $ids)->update([
            'allocation_bucket_id' => $bucket,
            'updated_by' => Auth::id(),
        ]);

        return back()->with('message', count($ids).' expenses classified.');
    }

    protected function maybeRegister(Expense $expense, Request $request)
    {
        $bucket = \App\WealthAllocationBucket::find($expense->allocation_bucket_id);
        if (! $bucket) {
            return;
        }
        if ($bucket->code === 'INVESTMENT' && $request->boolean('create_investment')) {
            \App\WealthInvestment::create([
                'name' => $expense->title ?: ($expense->note ?: 'Investment'),
                'type' => optional(\App\WealthExpenseSubcategory::find($expense->wealth_subcategory_id))->name,
                'amount_invested' => $expense->amount,
                'invested_on' => $expense->created_at ? $expense->created_at->toDateString() : date('Y-m-d'),
                'biller_id' => $expense->biller_id,
                'user_id' => $expense->user_id,
                'program_id' => $expense->program_id,
                'expense_id' => $expense->id,
                'status' => 'active',
                'created_by' => Auth::id(),
            ]);
        }
        if ($bucket->code === 'CHARITY' && $request->boolean('create_charity')) {
            \App\WealthCharityTransaction::create([
                'beneficiary' => $expense->payee ?: 'Beneficiary',
                'subcategory_id' => $expense->wealth_subcategory_id,
                'amount' => $expense->amount,
                'given_on' => $expense->created_at ? $expense->created_at->toDateString() : date('Y-m-d'),
                'biller_id' => $expense->biller_id,
                'user_id' => $expense->user_id,
                'program_id' => $expense->program_id,
                'expense_id' => $expense->id,
                'payment_method' => $expense->payment_method,
                'created_by' => Auth::id(),
            ]);
        }
    }
}
