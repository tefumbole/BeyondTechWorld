<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Roles;
use App\User;
use Auth;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index()
    {
        $role = Role::find(Auth::user()->role_id);
        if($role->hasPermissionTo('one_time_otp')) {
            if (Auth::user()->otp_verify == 0) {
                return redirect()->route('check.otp');
            }
        }


        if(Auth::user()->role_id <= 2) {
            $lims_role_all = Roles::where('is_active', true)->get();
            return view('role.create', compact('lims_role_all'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }


    public function create()
    {

    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => [
                'max:255',
                    Rule::unique('roles')->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
        ]);

        $data = $request->all();
        Roles::create($data);
        return redirect('role')->with('message', 'Data inserted successfully');
    }

    public function edit($id)
    {
        if(Auth::user()->role_id <= 2) {
            $lims_role_data = Roles::find($id);
            return $lims_role_data;
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => [
                'max:255',
                Rule::unique('roles')->ignore($request->role_id)->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
        ]);

        $input = $request->all();
        $lims_role_data = Roles::where('id', $input['role_id'])->first();
        $lims_role_data->update($input);
        return redirect('role')->with('message', 'Data updated successfully');
    }

    public function permission($id)
    {
        if(Auth::user()->role_id <= 2) {
            $lims_role_data = Roles::find($id);
            $permissions = Role::findByName($lims_role_data->name)->permissions;
            foreach ($permissions as $permission)
                $all_permission[] = $permission->name;
            if(empty($all_permission))
                $all_permission[] = 'dummy text';
            return view('role.permission', compact('lims_role_data', 'all_permission'));
        }
        else
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    public function setPermission(Request $request)
    {
        if(!env('USER_VERIFIED'))
            return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');

        $role = Role::firstOrCreate(['id' => $request['role_id']]);

        if($request->has('products-index')){
            $permission = Permission::firstOrCreate(['name' => 'products-index']);
            if(!$role->hasPermissionTo('products-index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('products-index');

        if($request->has('products-add')){
            $permission = Permission::firstOrCreate(['name' => 'products-add']);
            if(!$role->hasPermissionTo('products-add')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('products-add');
        if($request->has('products-edit')){
            $permission = Permission::firstOrCreate(['name' => 'products-edit']);
            if(!$role->hasPermissionTo('products-edit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('products-edit');

        if($request->has('products-delete')){
            $permission = Permission::firstOrCreate(['name' => 'products-delete']);
            if(!$role->hasPermissionTo('products-delete')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('products-delete');


        if($request->has('announcement_index')){
            $permission = Permission::firstOrCreate(['name' => 'announcement_index']);
            if(!$role->hasPermissionTo('announcement_index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('announcement_index');

        if($request->has('announcement_add')){
            $permission = Permission::firstOrCreate(['name' => 'announcement_add']);
            if(!$role->hasPermissionTo('announcement_add')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('announcement_add');

        if($request->has('announcement_edit')){
            $permission = Permission::firstOrCreate(['name' => 'announcement_edit']);
            if(!$role->hasPermissionTo('announcement_edit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('announcement_edit');

        if($request->has('announcement_delete')){
            $permission = Permission::firstOrCreate(['name' => 'announcement_delete']);
            if(!$role->hasPermissionTo('announcement_delete')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('announcement_delete');


        if($request->has('orders-index')){
            $permission = Permission::firstOrCreate(['name' => 'orders-index']);
            if(!$role->hasPermissionTo('orders-index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('orders-index');

        if($request->has('orders-add')){
            $permission = Permission::firstOrCreate(['name' => 'orders-add']);
            if(!$role->hasPermissionTo('orders-add')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('orders-add');

        if($request->has('orders-edit')){
            $permission = Permission::firstOrCreate(['name' => 'orders-edit']);
            if(!$role->hasPermissionTo('orders-edit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('orders-edit');

        if($request->has('orders-delete')){
            $permission = Permission::firstOrCreate(['name' => 'orders-delete']);
            if(!$role->hasPermissionTo('orders-delete')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('orders-delete');

        if($request->has('shops-index')){
            $permission = Permission::firstOrCreate(['name' => 'shops-index']);
            if(!$role->hasPermissionTo('shops-index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('shops-index');

        if($request->has('shops-add')){
            $permission = Permission::firstOrCreate(['name' => 'shops-add']);
            if(!$role->hasPermissionTo('shops-add')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('shops-add');

        if($request->has('shops-edit')){
            $permission = Permission::firstOrCreate(['name' => 'shops-edit']);
            if(!$role->hasPermissionTo('shops-edit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('shops-edit');

        if($request->has('shops-delete')){
            $permission = Permission::firstOrCreate(['name' => 'shops-delete']);
            if(!$role->hasPermissionTo('shops-delete')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('shops-delete');

        if($request->has('donations-index')){
            $permission = Permission::firstOrCreate(['name' => 'donations-index']);
            if(!$role->hasPermissionTo('donations-index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('donations-index');

        if($request->has('donations-add')){
            $permission = Permission::firstOrCreate(['name' => 'donations-add']);
            if(!$role->hasPermissionTo('donations-add')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('donations-add');

        if($request->has('donations-edit')){
            $permission = Permission::firstOrCreate(['name' => 'donations-edit']);
            if(!$role->hasPermissionTo('donations-edit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('donations-edit');

        if($request->has('donations-delete')){
            $permission = Permission::firstOrCreate(['name' => 'donations-delete']);
            if(!$role->hasPermissionTo('donations-delete')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('donations-delete');

        if($request->has('services-index')){
            $permission = Permission::firstOrCreate(['name' => 'services-index']);
            if(!$role->hasPermissionTo('services-index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('services-index');

        if($request->has('services-add')){
            $permission = Permission::firstOrCreate(['name' => 'services-add']);
            if(!$role->hasPermissionTo('services-add')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('services-add');

        if($request->has('services-edit')){
            $permission = Permission::firstOrCreate(['name' => 'services-edit']);
            if(!$role->hasPermissionTo('services-edit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('services-edit');

        if($request->has('services-delete')){
            $permission = Permission::firstOrCreate(['name' => 'services-delete']);
            if(!$role->hasPermissionTo('services-delete')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('services-delete');

        if($request->has('payments-index')){
            $permission = Permission::firstOrCreate(['name' => 'payments-index']);
            if(!$role->hasPermissionTo('payments-index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('payments-index');

        if($request->has('payments-add')){
            $permission = Permission::firstOrCreate(['name' => 'payments-add']);
            if(!$role->hasPermissionTo('payments-add')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('payments-add');

        if($request->has('payments-edit')){
            $permission = Permission::firstOrCreate(['name' => 'payments-edit']);
            if(!$role->hasPermissionTo('payments-edit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('payments-edit');

        if($request->has('payments-delete')){
            $permission = Permission::firstOrCreate(['name' => 'payments-delete']);
            if(!$role->hasPermissionTo('payments-delete')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('payments-delete');


        if($request->has('purchases-index')){
            $permission = Permission::firstOrCreate(['name' => 'purchases-index']);
            if(!$role->hasPermissionTo('purchases-index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('purchases-index');

        if($request->has('purchases-add')){
            $permission = Permission::firstOrCreate(['name' => 'purchases-add']);
            if(!$role->hasPermissionTo('purchases-add')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('purchases-add');

        if($request->has('purchases-edit')){
            $permission = Permission::firstOrCreate(['name' => 'purchases-edit']);
            if(!$role->hasPermissionTo('purchases-edit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('purchases-edit');

        if($request->has('purchases-delete')){
            $permission = Permission::firstOrCreate(['name' => 'purchases-delete']);
            if(!$role->hasPermissionTo('purchases-delete')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('purchases-delete');

        if($request->has('sales-index')){
            $permission = Permission::firstOrCreate(['name' => 'sales-index']);
            if(!$role->hasPermissionTo('sales-index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('sales-index');

        if($request->has('sales-add')){
            $permission = Permission::firstOrCreate(['name' => 'sales-add']);
            if(!$role->hasPermissionTo('sales-add')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('sales-add');

        if($request->has('sales-edit')){
            $permission = Permission::firstOrCreate(['name' => 'sales-edit']);
            if(!$role->hasPermissionTo('sales-edit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('sales-edit');

        if($request->has('sales-delete')){
            $permission = Permission::firstOrCreate(['name' => 'sales-delete']);
            if(!$role->hasPermissionTo('sales-delete')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('sales-delete');

        if($request->has('expenses-index')){
            $permission = Permission::firstOrCreate(['name' => 'expenses-index']);
            if(!$role->hasPermissionTo('expenses-index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('expenses-index');

        if($request->has('expenses-add')){
            $permission = Permission::firstOrCreate(['name' => 'expenses-add']);
            if(!$role->hasPermissionTo('expenses-add')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('expenses-add');

        if($request->has('expenses-edit')){
            $permission = Permission::firstOrCreate(['name' => 'expenses-edit']);
            if(!$role->hasPermissionTo('expenses-edit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('expenses-edit');

        if($request->has('expenses-delete')){
            $permission = Permission::firstOrCreate(['name' => 'expenses-delete']);
            if(!$role->hasPermissionTo('expenses-delete')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('expenses-delete');

        if($request->has('quotes-index')){
            $permission = Permission::firstOrCreate(['name' => 'quotes-index']);
            if(!$role->hasPermissionTo('quotes-index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('quotes-index');

        if($request->has('quotes-add')){
            $permission = Permission::firstOrCreate(['name' => 'quotes-add']);
            if(!$role->hasPermissionTo('quotes-add')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('quotes-add');

        if($request->has('quotes-edit')){
            $permission = Permission::firstOrCreate(['name' => 'quotes-edit']);
            if(!$role->hasPermissionTo('quotes-edit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('quotes-edit');

        if($request->has('quotes-delete')){
            $permission = Permission::firstOrCreate(['name' => 'quotes-delete']);
            if(!$role->hasPermissionTo('quotes-delete')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('quotes-delete');

        if($request->has('transfers-index')){
            $permission = Permission::firstOrCreate(['name' => 'transfers-index']);
            if(!$role->hasPermissionTo('transfers-index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('transfers-index');

        if($request->has('transfers-add')){
            $permission = Permission::firstOrCreate(['name' => 'transfers-add']);
            if(!$role->hasPermissionTo('transfers-add')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('transfers-add');

        if($request->has('transfers-edit')){
            $permission = Permission::firstOrCreate(['name' => 'transfers-edit']);
            if(!$role->hasPermissionTo('transfers-edit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('transfers-edit');

        if($request->has('transfers-delete')){
            $permission = Permission::firstOrCreate(['name' => 'transfers-delete']);
            if(!$role->hasPermissionTo('transfers-delete')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('transfers-delete');

        if($request->has('returns-index')){
            $permission = Permission::firstOrCreate(['name' => 'returns-index']);
            if(!$role->hasPermissionTo('returns-index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('returns-index');

        if($request->has('returns-add')){
            $permission = Permission::firstOrCreate(['name' => 'returns-add']);
            if(!$role->hasPermissionTo('returns-add')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('returns-add');

        if($request->has('returns-edit')){
            $permission = Permission::firstOrCreate(['name' => 'returns-edit']);
            if(!$role->hasPermissionTo('returns-edit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('returns-edit');

        if($request->has('returns-delete')){
            $permission = Permission::firstOrCreate(['name' => 'returns-delete']);
            if(!$role->hasPermissionTo('returns-delete')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('returns-delete');

        if($request->has('purchase-return-index')){
            $permission = Permission::firstOrCreate(['name' => 'purchase-return-index']);
            if(!$role->hasPermissionTo('purchase-return-index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('purchase-return-index');

        if($request->has('purchase-return-add')){
            $permission = Permission::firstOrCreate(['name' => 'purchase-return-add']);
            if(!$role->hasPermissionTo('purchase-return-add')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('purchase-return-add');

        if($request->has('purchase-return-edit')){
            $permission = Permission::firstOrCreate(['name' => 'purchase-return-edit']);
            if(!$role->hasPermissionTo('purchase-return-edit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('purchase-return-edit');

        if($request->has('purchase-return-delete')){
            $permission = Permission::firstOrCreate(['name' => 'purchase-return-delete']);
            if(!$role->hasPermissionTo('purchase-return-delete')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('purchase-return-delete');

        if($request->has('account-index')){
            $permission = Permission::firstOrCreate(['name' => 'account-index']);
            if(!$role->hasPermissionTo('account-index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('account-index');

        if($request->has('money-transfer')){
            $permission = Permission::firstOrCreate(['name' => 'money-transfer']);
            if(!$role->hasPermissionTo('money-transfer')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('money-transfer');

        if($request->has('balance-sheet')){
            $permission = Permission::firstOrCreate(['name' => 'balance-sheet']);
            if(!$role->hasPermissionTo('balance-sheet')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('balance-sheet');

        if($request->has('account-statement')){
            $permission = Permission::firstOrCreate(['name' => 'account-statement']);
            if(!$role->hasPermissionTo('account-statement')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('account-statement');

        if($request->has('department')){
            $permission = Permission::firstOrCreate(['name' => 'department']);
            if(!$role->hasPermissionTo('department')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('department');

        if($request->has('attendance')){
            $permission = Permission::firstOrCreate(['name' => 'attendance']);
            if(!$role->hasPermissionTo('attendance')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('attendance');

        if($request->has('payroll')){
            $permission = Permission::firstOrCreate(['name' => 'payroll']);
            if(!$role->hasPermissionTo('payroll')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('payroll');

        if($request->has('employees-index')){
            $permission = Permission::firstOrCreate(['name' => 'employees-index']);
            if(!$role->hasPermissionTo('employees-index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('employees-index');

        if($request->has('employees-add')){
            $permission = Permission::firstOrCreate(['name' => 'employees-add']);
            if(!$role->hasPermissionTo('employees-add')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('employees-add');

        if($request->has('employees-edit')){
            $permission = Permission::firstOrCreate(['name' => 'employees-edit']);
            if(!$role->hasPermissionTo('employees-edit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('employees-edit');

        if($request->has('employees-delete')){
            $permission = Permission::firstOrCreate(['name' => 'employees-delete']);
            if(!$role->hasPermissionTo('employees-delete')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('employees-delete');

        if($request->has('users-index')){
            $permission = Permission::firstOrCreate(['name' => 'users-index']);
            if(!$role->hasPermissionTo('users-index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('users-index');

        if($request->has('users-add')){
            $permission = Permission::firstOrCreate(['name' => 'users-add']);
            if(!$role->hasPermissionTo('users-add')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('users-add');

        if($request->has('users-edit')){
            $permission = Permission::firstOrCreate(['name' => 'users-edit']);
            if(!$role->hasPermissionTo('users-edit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('users-edit');

        if($request->has('users-delete')){
            $permission = Permission::firstOrCreate(['name' => 'users-delete']);
            if(!$role->hasPermissionTo('users-delete')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('users-delete');

        if($request->has('customers-index')){
            $permission = Permission::firstOrCreate(['name' => 'customers-index']);
            if(!$role->hasPermissionTo('customers-index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('customers-index');

        if($request->has('customers-add')){
            $permission = Permission::firstOrCreate(['name' => 'customers-add']);
            if(!$role->hasPermissionTo('customers-add')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('customers-add');

        if($request->has('customers-edit')){
            $permission = Permission::firstOrCreate(['name' => 'customers-edit']);
            if(!$role->hasPermissionTo('customers-edit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('customers-edit');

        if($request->has('customers-delete')){
            $permission = Permission::firstOrCreate(['name' => 'customers-delete']);
            if(!$role->hasPermissionTo('customers-delete')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('customers-delete');

        if($request->has('billers-index')){
            $permission = Permission::firstOrCreate(['name' => 'billers-index']);
            if(!$role->hasPermissionTo('billers-index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('billers-index');

        if($request->has('billers-add')){
            $permission = Permission::firstOrCreate(['name' => 'billers-add']);
            if(!$role->hasPermissionTo('billers-add')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('billers-add');

        if($request->has('billers-edit')){
            $permission = Permission::firstOrCreate(['name' => 'billers-edit']);
            if(!$role->hasPermissionTo('billers-edit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('billers-edit');

        if($request->has('billers-delete')){
            $permission = Permission::firstOrCreate(['name' => 'billers-delete']);
            if(!$role->hasPermissionTo('billers-delete')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('billers-delete');

        if($request->has('suppliers-index')){
            $permission = Permission::firstOrCreate(['name' => 'suppliers-index']);
            if(!$role->hasPermissionTo('suppliers-index')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('suppliers-index');

        if($request->has('suppliers-add')){
            $permission = Permission::firstOrCreate(['name' => 'suppliers-add']);
            if(!$role->hasPermissionTo('suppliers-add')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('suppliers-add');

        if($request->has('suppliers-edit')){
            $permission = Permission::firstOrCreate(['name' => 'suppliers-edit']);
            if(!$role->hasPermissionTo('suppliers-edit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('suppliers-edit');

        if($request->has('suppliers-delete')){
            $permission = Permission::firstOrCreate(['name' => 'suppliers-delete']);
            if(!$role->hasPermissionTo('suppliers-delete')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('suppliers-delete');

        if($request->has('profit-loss')){
            $permission = Permission::firstOrCreate(['name' => 'profit-loss']);
            if(!$role->hasPermissionTo('profit-loss')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('profit-loss');

        if($request->has('best-seller')){
            $permission = Permission::firstOrCreate(['name' => 'best-seller']);
            if(!$role->hasPermissionTo('best-seller')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('best-seller');

        if($request->has('product-report')){
            $permission = Permission::firstOrCreate(['name' => 'product-report']);
            if(!$role->hasPermissionTo('product-report')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('product-report');

        if($request->has('daily-sale')){
            $permission = Permission::firstOrCreate(['name' => 'daily-sale']);
            if(!$role->hasPermissionTo('daily-sale')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('daily-sale');

        if($request->has('monthly-sale')){
            $permission = Permission::firstOrCreate(['name' => 'monthly-sale']);
            if(!$role->hasPermissionTo('monthly-sale')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('monthly-sale');

        if($request->has('daily-purchase')){
            $permission = Permission::firstOrCreate(['name' => 'daily-purchase']);
            if(!$role->hasPermissionTo('daily-purchase')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('daily-purchase');

        if($request->has('monthly-purchase')){
            $permission = Permission::firstOrCreate(['name' => 'monthly-purchase']);
            if(!$role->hasPermissionTo('monthly-purchase')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('monthly-purchase');

        if($request->has('sale-report')){
            $permission = Permission::firstOrCreate(['name' => 'sale-report']);
            if(!$role->hasPermissionTo('sale-report')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('sale-report');

        if($request->has('payment-report')){
            $permission = Permission::firstOrCreate(['name' => 'payment-report']);
            if(!$role->hasPermissionTo('payment-report')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('payment-report');

        if($request->has('purchase-report')){
            $permission = Permission::firstOrCreate(['name' => 'purchase-report']);
            if(!$role->hasPermissionTo('purchase-report')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('purchase-report');

        if($request->has('warehouse-report')){
            $permission = Permission::firstOrCreate(['name' => 'warehouse-report']);
            if(!$role->hasPermissionTo('warehouse-report')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('warehouse-report');

        if($request->has('warehouse-stock-report')){
            $permission = Permission::firstOrCreate(['name' => 'warehouse-stock-report']);
            if(!$role->hasPermissionTo('warehouse-stock-report')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('warehouse-stock-report');

        if($request->has('product-qty-alert')){
            $permission = Permission::firstOrCreate(['name' => 'product-qty-alert']);
            if(!$role->hasPermissionTo('product-qty-alert')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('product-qty-alert');

        if($request->has('user-report')){
            $permission = Permission::firstOrCreate(['name' => 'user-report']);
            if(!$role->hasPermissionTo('user-report')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('user-report');

        if($request->has('customer-report')){
            $permission = Permission::firstOrCreate(['name' => 'customer-report']);
            if(!$role->hasPermissionTo('customer-report')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('customer-report');

        if($request->has('supplier-report')){
            $permission = Permission::firstOrCreate(['name' => 'supplier-report']);
            if(!$role->hasPermissionTo('supplier-report')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('supplier-report');

        if($request->has('due-report')){
            $permission = Permission::firstOrCreate(['name' => 'due-report']);
            if(!$role->hasPermissionTo('due-report')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('due-report');

        if($request->has('backup_database')){
            $permission = Permission::firstOrCreate(['name' => 'backup_database']);
            if(!$role->hasPermissionTo('backup_database')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('backup_database');

        if($request->has('general_setting')){
            $permission = Permission::firstOrCreate(['name' => 'general_setting']);
            if(!$role->hasPermissionTo('general_setting')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('general_setting');

        if($request->has('mail_setting')){
            $permission = Permission::firstOrCreate(['name' => 'mail_setting']);
            if(!$role->hasPermissionTo('mail_setting')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('mail_setting');

        if($request->has('sms_setting')){
            $permission = Permission::firstOrCreate(['name' => 'sms_setting']);
            if(!$role->hasPermissionTo('sms_setting')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('sms_setting');

        if($request->has('create_sms')){
            $permission = Permission::firstOrCreate(['name' => 'create_sms']);
            if(!$role->hasPermissionTo('create_sms')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('create_sms');

        if($request->has('pos_setting')){
            $permission = Permission::firstOrCreate(['name' => 'pos_setting']);
            if(!$role->hasPermissionTo('pos_setting')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('pos_setting');

        if($request->has('hrm_setting')){
            $permission = Permission::firstOrCreate(['name' => 'hrm_setting']);
            if(!$role->hasPermissionTo('hrm_setting')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('hrm_setting');


        if($request->has('hrm')){
            $permission = Permission::firstOrCreate(['name' => 'hrm']);
            if(!$role->hasPermissionTo('hrm')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('hrm');


        if($request->has('dashboard')){
            $permission = Permission::firstOrCreate(['name' => 'dashboard']);
            if(!$role->hasPermissionTo('dashboard')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('dashboard');

        if($request->has('reward_point_setting')){
            $permission = Permission::firstOrCreate(['name' => 'reward_point_setting']);
            if(!$role->hasPermissionTo('reward_point_setting')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('reward_point_setting');

        if($request->has('stock_count')){
            $permission = Permission::firstOrCreate(['name' => 'stock_count']);
            if(!$role->hasPermissionTo('stock_count')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('stock_count');

        if($request->has('adjustment')){
            $permission = Permission::firstOrCreate(['name' => 'adjustment']);
            if(!$role->hasPermissionTo('adjustment')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('adjustment');

        if($request->has('print_barcode')){
            $permission = Permission::firstOrCreate(['name' => 'print_barcode']);
            if(!$role->hasPermissionTo('print_barcode')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('print_barcode');

        if($request->has('empty_database')){
            $permission = Permission::firstOrCreate(['name' => 'empty_database']);
            if(!$role->hasPermissionTo('empty_database')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('empty_database');

        if($request->has('send_notification')){
            $permission = Permission::firstOrCreate(['name' => 'send_notification']);
            if(!$role->hasPermissionTo('send_notification')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('send_notification');

        if($request->has('warehouse')){
            $permission = Permission::firstOrCreate(['name' => 'warehouse']);
            if(!$role->hasPermissionTo('warehouse')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('warehouse');

        if($request->has('customer_group')){
            $permission = Permission::firstOrCreate(['name' => 'customer_group']);
            if(!$role->hasPermissionTo('customer_group')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('customer_group');

        if($request->has('brand')){
            $permission = Permission::firstOrCreate(['name' => 'brand']);
            if(!$role->hasPermissionTo('brand')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('brand');

        if($request->has('unit')){
            $permission = Permission::firstOrCreate(['name' => 'unit']);
            if(!$role->hasPermissionTo('unit')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('unit');

        if($request->has('currency')){
            $permission = Permission::firstOrCreate(['name' => 'currency']);
            if(!$role->hasPermissionTo('currency')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('currency');

        if($request->has('tax')){
            $permission = Permission::firstOrCreate(['name' => 'tax']);
            if(!$role->hasPermissionTo('tax')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('tax');

        if($request->has('gift_card')){
            $permission = Permission::firstOrCreate(['name' => 'gift_card']);
            if(!$role->hasPermissionTo('gift_card')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('gift_card');

        if($request->has('coupon')){
            $permission = Permission::firstOrCreate(['name' => 'coupon']);
            if(!$role->hasPermissionTo('coupon')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('coupon');

        if($request->has('holiday')){
            $permission = Permission::firstOrCreate(['name' => 'holiday']);
            if(!$role->hasPermissionTo('holiday')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('holiday');

        if($request->has('category')){
            $permission = Permission::firstOrCreate(['name' => 'category']);
            if(!$role->hasPermissionTo('category')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('category');

        if($request->has('delivery')){
            $permission = Permission::firstOrCreate(['name' => 'delivery']);
            if(!$role->hasPermissionTo('delivery')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('delivery');

        if($request->has('today_sale')) {
            $permission = Permission::firstOrCreate(['name' => 'today_sale']);
            if(!$role->hasPermissionTo('today_sale')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('today_sale');

        if($request->has('today_profit')) {
            $permission = Permission::firstOrCreate(['name' => 'today_profit']);
            if(!$role->hasPermissionTo('today_profit')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('today_profit');

        if($request->has('price-change')) {
            $permission = Permission::firstOrCreate(['name' => 'price-change']);
            if(!$role->hasPermissionTo('price-change')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('price-change');

        if($request->has('JE-method')) {
            $permission = Permission::firstOrCreate(['name' => 'JE-method']);
            if(!$role->hasPermissionTo('JE-method')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('JE-method');

        if($request->has('multiple_batch')) {
            $permission = Permission::firstOrCreate(['name' => 'multiple_batch']);
            if(!$role->hasPermissionTo('multiple_batch')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('multiple_batch');


        if($request->has('zero_stock')) {
            $permission = Permission::firstOrCreate(['name' => 'zero_stock']);
            if(!$role->hasPermissionTo('zero_stock')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('zero_stock');


//        if($request->has('search_all_products')) {
//            $permission = Permission::firstOrCreate(['name' => 'search_all_products']);
//            if(!$role->hasPermissionTo('search_all_products')) {
//                $role->givePermissionTo($permission);
//            }
//        }
//        else
//            $role->revokePermissionTo('search_all_products');

        if($request->has('developed_by')) {
            $permission = Permission::firstOrCreate(['name' => 'developed_by']);
            if(!$role->hasPermissionTo('developed_by')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('developed_by');


        if($request->has('star_product')) {
            $permission = Permission::firstOrCreate(['name' => 'star_product']);
            if(!$role->hasPermissionTo('star_product')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('star_product');

        if($request->has('search_on_click')) {
            $permission = Permission::firstOrCreate(['name' => 'search_on_click']);
            if(!$role->hasPermissionTo('search_on_click')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('search_on_click');


        if($request->has('booking_module')) {
            $permission = Permission::firstOrCreate(['name' => 'booking_module']);
            if(!$role->hasPermissionTo('booking_module')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('booking_module');

        if($request->has('booking_create')) {
            $permission = Permission::firstOrCreate(['name' => 'booking_create']);
            if(!$role->hasPermissionTo('booking_create')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('booking_create');

        if($request->has('booking_edit')) {
            $permission = Permission::firstOrCreate(['name' => 'booking_edit']);
            if(!$role->hasPermissionTo('booking_edit')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('booking_edit');

        if($request->has('booking_delete')) {
            $permission = Permission::firstOrCreate(['name' => 'booking_delete']);
            if(!$role->hasPermissionTo('booking_delete')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('booking_delete');

        if($request->has('booking_index')) {
            $permission = Permission::firstOrCreate(['name' => 'booking_index']);
            if(!$role->hasPermissionTo('booking_index')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('booking_index');

        if($request->has('booking_return')) {
            $permission = Permission::firstOrCreate(['name' => 'booking_return']);
            if(!$role->hasPermissionTo('booking_return')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('booking_return');

        if($request->has('booking_report')) {
            $permission = Permission::firstOrCreate(['name' => 'booking_report']);
            if(!$role->hasPermissionTo('booking_report')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('booking_report');

        if($request->has('letter_module')) {
            $permission = Permission::firstOrCreate(['name' => 'letter_module']);
            if(!$role->hasPermissionTo('letter_module')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('letter_module');

        if($request->has('letter_category')) {
            $permission = Permission::firstOrCreate(['name' => 'letter_category']);
            if(!$role->hasPermissionTo('letter_category')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('letter_category');


        if($request->has('letter_index')) {
            $permission = Permission::firstOrCreate(['name' => 'letter_index']);
            if(!$role->hasPermissionTo('letter_index')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('letter_index');

        if($request->has('letter_create')) {
            $permission = Permission::firstOrCreate(['name' => 'letter_create']);
            if(!$role->hasPermissionTo('letter_create')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('letter_create');

        if($request->has('letter_edit')) {
            $permission = Permission::firstOrCreate(['name' => 'letter_edit']);
            if(!$role->hasPermissionTo('letter_edit')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('letter_edit');

        if($request->has('letter_delete')) {
            $permission = Permission::firstOrCreate(['name' => 'letter_delete']);
            if(!$role->hasPermissionTo('letter_delete')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('letter_delete');

        if($request->has('letter_approve')) {
            $permission = Permission::firstOrCreate(['name' => 'letter_approve']);
            if(!$role->hasPermissionTo('letter_approve')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('letter_approve');


        if($request->has('letter_approve_index')) {
            $permission = Permission::firstOrCreate(['name' => 'letter_approve_index']);
            if(!$role->hasPermissionTo('letter_approve_index')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('letter_approve_index');

        if($request->has('letter_template')) {
            $permission = Permission::firstOrCreate(['name' => 'letter_template']);
            if(!$role->hasPermissionTo('letter_template')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('letter_template');


        if($request->has('letter_rejected')) {
            $permission = Permission::firstOrCreate(['name' => 'letter_rejected']);
            if(!$role->hasPermissionTo('letter_rejected')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('letter_rejected');


        if($request->has('letter_awaiting_edit')) {
            $permission = Permission::firstOrCreate(['name' => 'letter_awaiting_edit']);
            if(!$role->hasPermissionTo('letter_awaiting_edit')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('letter_awaiting_edit');

        if($request->has('letter_edited_index')) {
            $permission = Permission::firstOrCreate(['name' => 'letter_edited_index']);
            if(!$role->hasPermissionTo('letter_edited_index')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('letter_edited_index');


        if($request->has('letter_send')) {
            $permission = Permission::firstOrCreate(['name' => 'letter_send']);
            if(!$role->hasPermissionTo('letter_send')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('letter_send');

        if($request->has('letter_send_index')) {
            $permission = Permission::firstOrCreate(['name' => 'letter_send_index']);
            if(!$role->hasPermissionTo('letter_send_index')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('letter_send_index');

        if($request->has('letter_sign')) {
            $permission = Permission::firstOrCreate(['name' => 'letter_sign']);
            if(!$role->hasPermissionTo('letter_sign')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('letter_sign');

        if($request->has('letter_sign_index')) {
            $permission = Permission::firstOrCreate(['name' => 'letter_sign_index']);
            if(!$role->hasPermissionTo('letter_sign_index')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('letter_sign_index');


        if($request->has('one_time_otp')) {
            $permission = Permission::firstOrCreate(['name' => 'one_time_otp']);
            if(!$role->hasPermissionTo('one_time_otp')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('one_time_otp');


        if($request->has('forward_letter')) {
            $permission = Permission::firstOrCreate(['name' => 'forward_letter']);
            if(!$role->hasPermissionTo('forward_letter')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('forward_letter');


//        fixed assets
        if($request->has('asset-index')) {
            $permission = Permission::firstOrCreate(['name' => 'asset-index']);
            if(!$role->hasPermissionTo('asset-index')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('asset-index');


        if($request->has('asset-add')) {
            $permission = Permission::firstOrCreate(['name' => 'asset-add']);
            if(!$role->hasPermissionTo('asset-add')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('asset-add');

        if($request->has('asset-edit')) {
            $permission = Permission::firstOrCreate(['name' => 'asset-edit']);
            if(!$role->hasPermissionTo('asset-edit')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('asset-edit');

        if($request->has('asset-delete')) {
            $permission = Permission::firstOrCreate(['name' => 'asset-delete']);
            if(!$role->hasPermissionTo('asset-delete')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('asset-delete');

        if($request->has('donor-index')) {
            $permission = Permission::firstOrCreate(['name' => 'donor-index']);
            if(!$role->hasPermissionTo('donor-index')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('donor-index');

        if($request->has('donor-add')) {
            $permission = Permission::firstOrCreate(['name' => 'donor-add']);
            if(!$role->hasPermissionTo('donor-add')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('donor-add');

        if($request->has('donor-edit')) {
            $permission = Permission::firstOrCreate(['name' => 'donor-edit']);
            if(!$role->hasPermissionTo('donor-edit')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('donor-edit');

        if($request->has('donor-delete')) {
            $permission = Permission::firstOrCreate(['name' => 'donor-delete']);
            if(!$role->hasPermissionTo('donor-delete')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('donor-delete');

        if($request->has('station-index')) {
            $permission = Permission::firstOrCreate(['name' => 'station-index']);
            if(!$role->hasPermissionTo('station-index')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('station-index');

        if($request->has('station-add')) {
            $permission = Permission::firstOrCreate(['name' => 'station-add']);
            if(!$role->hasPermissionTo('station-add')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('station-add');

        if($request->has('station-edit')) {
            $permission = Permission::firstOrCreate(['name' => 'station-edit']);
            if(!$role->hasPermissionTo('station-edit')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('station-edit');

        if($request->has('station-delete')) {
            $permission = Permission::firstOrCreate(['name' => 'station-delete']);
            if(!$role->hasPermissionTo('station-delete')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('station-delete');

        if($request->has('region-index')) {
            $permission = Permission::firstOrCreate(['name' => 'region-index']);
            if(!$role->hasPermissionTo('region-index')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('region-index');

        if($request->has('region-add')) {
            $permission = Permission::firstOrCreate(['name' => 'region-add']);
            if(!$role->hasPermissionTo('region-add')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('region-add');

        if($request->has('region-edit')) {
            $permission = Permission::firstOrCreate(['name' => 'region-edit']);
            if(!$role->hasPermissionTo('region-edit')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('region-edit');

        if($request->has('region-delete')) {
            $permission = Permission::firstOrCreate(['name' => 'region-delete']);
            if(!$role->hasPermissionTo('region-delete')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('region-delete');

        if($request->has('asset-type-index')) {
            $permission = Permission::firstOrCreate(['name' => 'asset-type-index']);
            if(!$role->hasPermissionTo('asset-type-index')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('asset-type-index');

        if($request->has('asset-type-add')) {
            $permission = Permission::firstOrCreate(['name' => 'asset-type-add']);
            if(!$role->hasPermissionTo('asset-type-add')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('asset-type-add');

        if($request->has('asset-type-edit')) {
            $permission = Permission::firstOrCreate(['name' => 'asset-type-edit']);
            if(!$role->hasPermissionTo('asset-type-edit')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('asset-type-edit');

        if($request->has('asset-type-delete')) {
            $permission = Permission::firstOrCreate(['name' => 'asset-type-delete']);
            if(!$role->hasPermissionTo('asset-type-delete')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('asset-type-delete');

        if($request->has('activity-index')) {
            $permission = Permission::firstOrCreate(['name' => 'activity-index']);
            if(!$role->hasPermissionTo('activity-index')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('activity-index');

        if($request->has('activity-add')) {
            $permission = Permission::firstOrCreate(['name' => 'activity-add']);
            if(!$role->hasPermissionTo('activity-add')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('activity-add');

        if($request->has('activity-edit')) {
            $permission = Permission::firstOrCreate(['name' => 'activity-edit']);
            if(!$role->hasPermissionTo('activity-edit')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('activity-edit');

        if($request->has('activity-delete')) {
            $permission = Permission::firstOrCreate(['name' => 'activity-delete']);
            if(!$role->hasPermissionTo('activity-delete')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('activity-delete');

        if($request->has('asset-expense-index')) {
            $permission = Permission::firstOrCreate(['name' => 'asset-expense-index']);
            if(!$role->hasPermissionTo('asset-expense-index')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('asset-expense-index');

        if($request->has('asset-expense-add')) {
            $permission = Permission::firstOrCreate(['name' => 'asset-expense-add']);
            if(!$role->hasPermissionTo('asset-expense-add')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('asset-expense-add');

        if($request->has('asset-expense-edit')) {
            $permission = Permission::firstOrCreate(['name' => 'asset-expense-edit']);
            if(!$role->hasPermissionTo('asset-expense-edit')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('asset-expense-edit');

        if($request->has('asset-expense-delete')) {
            $permission = Permission::firstOrCreate(['name' => 'asset-expense-delete']);
            if(!$role->hasPermissionTo('asset-expense-delete')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('asset-expense-delete');

        if($request->has('asset-sale')) {
            $permission = Permission::firstOrCreate(['name' => 'asset-sale']);
            if(!$role->hasPermissionTo('asset-sale')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('asset-sale');

        if($request->has('asset-transfer')) {
            $permission = Permission::firstOrCreate(['name' => 'asset-transfer']);
            if(!$role->hasPermissionTo('asset-transfer')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('asset-transfer');

        if($request->has('asset-disppose')) {
            $permission = Permission::firstOrCreate(['name' => 'asset-disppose']);
            if(!$role->hasPermissionTo('asset-disppose')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('asset-disppose');

//        last
        if($request->has('fixed_assets')) {
            $permission = Permission::firstOrCreate(['name' => 'fixed_assets']);
            if(!$role->hasPermissionTo('fixed_assets')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('fixed_assets');

        if($request->has('fixed_assets_report')) {
            $permission = Permission::firstOrCreate(['name' => 'fixed_assets_report']);
            if(!$role->hasPermissionTo('fixed_assets_report')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('fixed_assets_report');

//        fixed assets end

        if($request->has('average-report')) {
            $permission = Permission::firstOrCreate(['name' => 'average-report']);
            if(!$role->hasPermissionTo('average-report')) {
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('average-report');

        if($request->has('env_setting')){
            $permission = Permission::firstOrCreate(['name' => 'env_setting']);
            if(!$role->hasPermissionTo('env_setting')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('env_setting');

        if($request->has('booking_awaiting_signature')){
            $permission = Permission::firstOrCreate(['name' => 'booking_awaiting_signature']);
            if(!$role->hasPermissionTo('booking_awaiting_signature')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('booking_awaiting_signature');

        if($request->has('booking_pending_review')){
            $permission = Permission::firstOrCreate(['name' => 'booking_pending_review']);
            if(!$role->hasPermissionTo('booking_pending_review')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('booking_pending_review');

        if($request->has('booking_signed_contracts')){
            $permission = Permission::firstOrCreate(['name' => 'booking_signed_contracts']);
            if(!$role->hasPermissionTo('booking_signed_contracts')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('booking_signed_contracts');

        if($request->has('booking_goods_received')){
            $permission = Permission::firstOrCreate(['name' => 'booking_goods_received']);
            if(!$role->hasPermissionTo('booking_goods_received')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('booking_goods_received');

        if($request->has('booking_contract_approve')){
            $permission = Permission::firstOrCreate(['name' => 'booking_contract_approve']);
            if(!$role->hasPermissionTo('booking_contract_approve')){
                $role->givePermissionTo($permission);
            }
        }
        else
            $role->revokePermissionTo('booking_contract_approve');

        return redirect('role')->with('message', 'Permission updated successfully');
    }

    public function destroy($id)
    {
        if(!env('USER_VERIFIED'))
            return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');
        $lims_role_data = Roles::find($id);
        $lims_role_data->is_active = false;
        $lims_role_data->save();
        return redirect('role')->with('not_permitted', 'Data deleted successfully');
    }
}
