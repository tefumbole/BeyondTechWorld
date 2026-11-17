<?php

namespace App\Http\Controllers;

use App\Payroll\StaffPayRoles;
use App\Services\Payroll\CameroonPayrollService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class StaffPayRoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $this->guard();
        StaffPayRoles::ensure();
        $roles = DB::table('cm_pay_roles')->orderBy('name')->get();
        $categories = Schema::hasTable('cm_salary_categories')
            ? DB::table('cm_salary_categories')->where('is_active', 1)->orderBy('name')->get()
            : collect();

        return view('payroll.cm.pay_roles', compact('roles', 'categories'));
    }

    public function save(Request $request)
    {
        $this->guard();
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return back()->with('not_permitted', 'Enter the role name.');
        }
        $payType = $request->input('pay_type') === 'monthly' ? 'monthly' : 'daily';
        $payload = [
            'name' => $name,
            'pay_type' => $payType,
            'daily_rate' => max(0, (int) $request->input('daily_rate')),
            'monthly_salary' => max(0, (int) $request->input('monthly_salary')),
            'salary_category_code' => $request->input('salary_category_code') ?: null,
            'is_active' => $request->input('is_active') ? 1 : 0,
            'updated_at' => now(),
        ];
        $id = (int) $request->input('id');
        if ($id > 0) {
            DB::table('cm_pay_roles')->where('id', $id)->update($payload);
        } else {
            $payload['code'] = substr(preg_replace('/[^a-z0-9_]+/', '_', strtolower($name)), 0, 40) ?: 'role';
            $payload['created_at'] = now();
            if (DB::table('cm_pay_roles')->where('code', $payload['code'])->exists()) {
                $payload['code'] = substr($payload['code'], 0, 32).'_'.substr(uniqid(), -6);
            }
            DB::table('cm_pay_roles')->insert($payload);
        }

        return back()->with('message', 'Role and salary saved.');
    }

    public function importEvents()
    {
        $this->guard();
        $count = StaffPayRoles::importEvents();

        return back()->with('message', 'Imported '.$count.' event payment roles.');
    }

    protected function guard()
    {
        $role = Role::find(Auth::user()->role_id);
        if (! $role) {
            abort(403);
        }
        foreach (['employees-add', 'employees-index', 'payroll'] as $name) {
            try {
                if ($role->hasPermissionTo($name)) {
                    return;
                }
            } catch (\Throwable $e) {
            }
        }
        abort(403);
    }
}
