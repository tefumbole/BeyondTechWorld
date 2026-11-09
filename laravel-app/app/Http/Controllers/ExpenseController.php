<?php

namespace App\Http\Controllers;

use App\Asset;
use App\AssetExpense;
use App\Category;
use App\Event;
use App\EventAssignment;
use App\ExpenseCategory;
use App\Payment;
use App\Quotation;
use App\Warehouse;
use Illuminate\Http\Request;
use App\Expense;
use App\Account;
use App\CashRegister;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Auth;
use DB;

class ExpenseController extends Controller
{
    public function board(Request $request)
    {
        $this->guardExpenses();
        $eventId = trim((string) $request->query('event_id', ''));
        $quotationId = (int) $request->query('quotation_id', 0);
        $categories = ExpenseCategory::where('is_active', true)->orderBy('name')->get();
        $spent = Expense::selectRaw('expense_category_id, SUM(amount) as spent')
            ->groupBy('expense_category_id')
            ->pluck('spent', 'expense_category_id');
        $events = Event::orderByDesc('event_start_at')->limit(80)->get(['id', 'name', 'reference_no', 'event_start_at', 'customer_id']);
        $quotations = Quotation::orderByDesc('id')->limit(80)->get(['id', 'reference_no', 'customer_id', 'grand_total']);
        $payments = Payment::orderByDesc('id')->limit(40)->get(['id', 'payment_reference', 'amount', 'paying_method']);
        $expenses = Expense::with('expenseCategory', 'event', 'quotation', 'payment')->orderByDesc('id')->limit(40)->get();
        $engineers = $this->engineerCosts($eventId, $quotationId);

        return view('expense.board', compact(
            'categories', 'spent', 'events', 'quotations', 'payments', 'expenses', 'engineers', 'eventId', 'quotationId'
        ));
    }

    public function storeBoard(Request $request)
    {
        $this->guardExpenses();
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'note' => 'nullable|string|max:2000',
            'expense_category_id' => 'nullable|integer',
            'new_category' => 'nullable|string|max:120',
            'budget' => 'nullable|numeric|min:0',
            'event_id' => 'nullable|string|max:64',
            'quotation_id' => 'nullable|integer',
            'payment_id' => 'nullable|integer',
        ]);
        $categoryId = ! empty($data['expense_category_id']) ? (int) $data['expense_category_id'] : 0;
        $newName = trim((string) (isset($data['new_category']) ? $data['new_category'] : ''));
        if ($newName !== '') {
            $category = ExpenseCategory::create([
                'code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $newName), 0, 8)).'-'.date('His'),
                'name' => $newName,
                'is_active' => 1,
                'budget' => isset($data['budget']) && $data['budget'] !== '' ? $data['budget'] : null,
            ]);
            $categoryId = $category->id;
        }
        if ($categoryId < 1) {
            $category = ExpenseCategory::where('is_active', true)->orderBy('id')->first();
            if (! $category) {
                $category = ExpenseCategory::create([
                    'code' => 'GEN',
                    'name' => 'General',
                    'is_active' => 1,
                ]);
            }
            $categoryId = $category->id;
        } elseif (isset($data['budget']) && $data['budget'] !== '') {
            ExpenseCategory::where('id', $categoryId)->update(['budget' => $data['budget']]);
        }
        $warehouseId = Auth::user()->warehouse_id ?: Warehouse::where('is_active', true)->value('id');
        $productCategoryId = Category::where('is_active', true)->value('id');
        Expense::create([
            'reference_no' => 'er-'.date('Ymd').'-'.date('his'),
            'expense_category_id' => $categoryId,
            'category_id' => $productCategoryId,
            'warehouse_id' => $warehouseId,
            'user_id' => Auth::id(),
            'amount' => $data['amount'],
            'note' => isset($data['note']) ? $data['note'] : null,
            'event_id' => ! empty($data['event_id']) ? $data['event_id'] : null,
            'quotation_id' => ! empty($data['quotation_id']) ? (int) $data['quotation_id'] : null,
            'payment_id' => ! empty($data['payment_id']) ? (int) $data['payment_id'] : null,
        ]);

        return redirect()->route('expenses.board', [
            'event_id' => ! empty($data['event_id']) ? $data['event_id'] : null,
            'quotation_id' => ! empty($data['quotation_id']) ? (int) $data['quotation_id'] : null,
        ])->with('message', 'Expense saved.');
    }

    public function storeBoardCategory(Request $request)
    {
        $this->guardExpenses();
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'budget' => 'required|numeric|min:0',
        ]);
        ExpenseCategory::create([
            'code' => strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $data['name']), 0, 8)).'-'.date('His'),
            'name' => $data['name'],
            'is_active' => 1,
            'budget' => $data['budget'],
        ]);

        return redirect()->route('expenses.board')->with('message', 'Category saved with a budget of '.number_format($data['budget']).' XAF.');
    }

    protected function guardExpenses()
    {
        if ((int) Auth::user()->role_id <= 2) {
            return;
        }
        $role = Role::find(Auth::user()->role_id);
        if (! $role || ! $role->hasPermissionTo('expenses-index')) {
            abort(403);
        }
    }

    protected function engineerCosts($eventId, $quotationId)
    {
        $rows = [];
        $eventIds = [];
        if ($eventId !== '') {
            $eventIds[] = $eventId;
        }
        if ($quotationId > 0) {
            $quotation = Quotation::find($quotationId);
            if ($quotation && $quotation->customer_id) {
                $linked = Event::where('customer_id', $quotation->customer_id)->orderByDesc('event_start_at')->limit(8)->pluck('id');
                foreach ($linked as $id) {
                    $eventIds[] = (string) $id;
                }
            }
        }
        $eventIds = array_values(array_unique($eventIds));
        if (! count($eventIds)) {
            return $rows;
        }
        $assignments = EventAssignment::with('workerProfile', 'event')
            ->whereIn('event_id', $eventIds)
            ->get();
        foreach ($assignments as $assignment) {
            if ($assignment->acceptance_status === 'rejected') {
                continue;
            }
            $rows[] = [
                'event' => $assignment->event ? $assignment->event->name : 'Event',
                'name' => $assignment->workerProfile ? $assignment->workerProfile->displayName() : 'Engineer',
                'role' => $assignment->assignment_role ?: 'Crew',
                'amount' => (int) $assignment->expected_total,
            ];
        }

        return $rows;
    }

    public function index(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('expenses-index')){
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission)
                $all_permission[] = $permission->name;
            if(empty($all_permission))
                $all_permission[] = 'dummy text';
            $lims_account_list = Account::where('is_active', true)->get();

            if($request->start_date) {
                $start_date = $request->start_date;
                $end_date = $request->end_date;
            }
            else {
                $start_date = date('Y-m-01', strtotime('-1 year', strtotime(date('Y-m-d'))));
                $end_date = date("Y-m-d");
            }

            if(Auth::user()->role_id > 2 && config('staff_access') == 'own')
                $lims_expense_all = Expense::where('user_id', Auth::id())->whereDate('created_at', '>=', $start_date)->whereDate('created_at', '<=', $end_date)->orderBy('id', 'desc')->get();
            else
                $lims_expense_all = Expense::whereDate('created_at', '>=', $start_date)->whereDate('created_at', '<=', $end_date)->orderBy('id', 'desc')->get();
            return redirect()->route('wealth.expenses');
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    private function role(){
        return Role::find(Auth::user()->role_id);
    }

    public function asset()
    {
        $permissions = Role::findByName($this->role()->name)->permissions;
        foreach ($permissions as $permission)
            $all_permission[] = $permission->name;

        $lims_expense_all = AssetExpense::orderBy('id', 'desc')->where('type', 'expense')->get();

        return view('expense.asset', compact('lims_expense_all', 'all_permission'));
    }

    public function assetActivity()
    {
            $permissions = Role::findByName($this->role()->name)->permissions;
            foreach ($permissions as $permission)
                $all_permission[] = $permission->name;

        $lims_expense_all = AssetExpense::orderBy('id', 'desc')->where('type', 'activity')->get();

        return view('fixed_asset.asset.activity', compact('lims_expense_all', 'all_permission'));
    }
    public function assetActivityRepair()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('fixed_assets')) {
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission)
                $all_permission[] = $permission->name;
        }
        $lims_expense_all = AssetExpense::orderBy('id', 'desc')->where('type', 'activity')->where('activity_type', 'Repair')->get();

        return view('fixed_asset.asset.activity_repair', compact('lims_expense_all', 'all_permission'));
    }

    public function assetStore(Request $request)
    {
        $data = $request->all();
        $data['reference_no'] = 'er-' . date("Ymd") . '-'. date("his");
        $data['user_id'] = Auth::id();
        if($data['type'] == 'activity' && $data['activity_type'] == 'milage' && $data['total_km'] == null) {
            $data['total_km'] = $data['end_km'] - $data['start_km'];
        }
        AssetExpense::create($data);
        return back()->with('message', 'Data inserted successfully');
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $data['reference_no'] = 'er-' . date("Ymd") . '-'. date("his");
        $data['user_id'] = Auth::id();
        $cash_register_data = CashRegister::where([
            ['user_id', $data['user_id']],
            ['warehouse_id', $data['warehouse_id']],
            ['status', true]
        ])->first();
        if($cash_register_data)
            $data['cash_register_id'] = $cash_register_data->id;
        Expense::create($data);
        return redirect()->route('wealth.expenses')->with('message', 'Data inserted successfully');
    }

    public function show($id)
    {
        //
    }

    public function edit($id)
    {
        $role = Role::firstOrCreate(['id' => Auth::user()->role_id]);
        if ($role->hasPermissionTo('expenses-edit')) {
            $lims_expense_data = Expense::find($id);
            return $lims_expense_data;
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function editAsset($id)
    {
        $role = Role::firstOrCreate(['id' => Auth::user()->role_id]);
        if ($role->hasPermissionTo('fixed_assets')) {
            $lims_expense_data = AssetExpense::find($id);
            if($lims_expense_data['asset_id'] != null) {
                $lims_expense_data['asset_name'] = Asset::find($lims_expense_data['asset_id'])->name;
            }

            return $lims_expense_data;
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function showAsset($id)
    {
        $role = Role::firstOrCreate(['id' => Auth::user()->role_id]);
        if ($role->hasPermissionTo('fixed_assets')) {
            $lims_expense_data = AssetExpense::find($id)->toArray();
            $lims_expense_data['asset_name'] = '';
            $lims_expense_data['account_name'] = '';
            $lims_expense_data['category_name'] = '';

            if($lims_expense_data['asset_id'] != null) {
                $lims_expense_data['asset_name'] = Asset::find($lims_expense_data['asset_id'])->name;
            }
            if($lims_expense_data['account_id'] != null) {
                $account = Account::find($lims_expense_data['account_id']);
                $lims_expense_data['account_name'] = $account->name . '|' . $account->account_no;
            }
            if($lims_expense_data['expense_category_id'] != null) {
                $lims_expense_data['category_name'] = ExpenseCategory::find($lims_expense_data['expense_category_id'])->name;
            }

            return $lims_expense_data;
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function update(Request $request, $id)
    {
        $data = $request->all();
        $lims_expense_data = Expense::find($data['expense_id']);
        $lims_expense_data->update($data);
        return redirect()->route('wealth.expenses')->with('message', 'Data updated successfully');
    }

    public function updateAsset(Request $request, $id)
    {
        $data = $request->all();
        if(!isset($data['approved'])){
            $data['approved'] = null;
        }
        $lims_expense_data = AssetExpense::find($data['id']);
        $lims_expense_data->update($data);
        return back()->with('message', 'Data updated successfully');
    }

    public function deleteBySelection(Request $request)
    {
        $expense_id = $request['expenseIdArray'];
        foreach ($expense_id as $id) {
            $lims_expense_data = Expense::find($id);
            $lims_expense_data->delete();
        }
        return 'Expense deleted successfully!';
    }

    public function destroy($id)
    {
        $lims_expense_data = Expense::find($id);
        $lims_expense_data->delete();
        return back()->with('not_permitted', 'Data deleted successfully');
    }

    public function destroyAsset($id)
    {
        $lims_expense_data = AssetExpense::find($id);
        $lims_expense_data->delete();
        return back()->with('not_permitted', 'Data deleted successfully');
    }
}
