<?php

namespace App\Http\Controllers;

use App\Asset;
use App\AssetExpense;
use App\ExpenseCategory;
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
            return view('expense.index', compact('lims_account_list', 'lims_expense_all', 'all_permission', 'start_date', 'end_date'));
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
        return redirect('expenses')->with('message', 'Data inserted successfully');
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
        return redirect('expenses')->with('message', 'Data updated successfully');
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
