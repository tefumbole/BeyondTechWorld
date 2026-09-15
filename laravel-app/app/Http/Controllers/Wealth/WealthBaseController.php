<?php

namespace App\Http\Controllers\Wealth;

use App\Biller;
use App\Employee;
use App\Http\Controllers\Controller;
use App\User;
use App\WealthAllocationBucket;
use App\WealthExpenseSubcategory;
use App\WealthIncomeCategory;
use App\WealthProgram;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class WealthBaseController extends Controller
{
    protected function can($permission)
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }
        if ((int) $user->role_id <= 2) {
            return true;
        }
        $role = Role::find($user->role_id);
        if (! $role) {
            return false;
        }
        try {
            return $role->hasPermissionTo($permission)
                || $role->hasPermissionTo('wealth.manage')
                || ($permission !== 'wealth.settings.manage'
                    && (substr($permission, -5) === '.view' || $permission === 'wealth.view')
                    && $role->hasPermissionTo('wealth.view'));
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function canAny(array $permissions)
    {
        foreach ($permissions as $p) {
            if ($this->can($p)) {
                return true;
            }
        }

        return false;
    }

    protected function deny()
    {
        return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
    }

    protected function storeWealthFile($file, $subdir)
    {
        if (! $file) {
            return null;
        }
        $dir = public_path('wealth/'.$subdir);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $file->getClientOriginalName());
        $name = date('YmdHis').'_'.$safe;
        $file->move($dir, $name);

        return $name;
    }

    protected function lookups()
    {
        $programs = collect();
        $buckets = collect();
        $subcategories = collect();
        $incomeCategories = collect();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('wealth_programs')) {
                $programs = WealthProgram::orderBy('name')->get();
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('wealth_allocation_buckets')) {
                $buckets = WealthAllocationBucket::where('is_active', 1)->orderBy('sort_order')->get();
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('wealth_expense_subcategories')) {
                $subcategories = WealthExpenseSubcategory::where('is_active', 1)->orderBy('name')->get();
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('wealth_income_categories')) {
                $incomeCategories = WealthIncomeCategory::where('is_active', 1)->orderBy('name')->get();
            }
        } catch (\Throwable $e) {
        }

        return [
            'billers' => Biller::where('is_active', 1)->orderBy('name')->get(),
            'users' => User::where('is_active', 1)->orderBy('name')->get(),
            'employees' => class_exists(Employee::class) ? Employee::where('is_active', 1)->orderBy('name')->get() : collect(),
            'programs' => $programs,
            'buckets' => $buckets,
            'subcategories' => $subcategories,
            'incomeCategories' => $incomeCategories,
        ];
    }
}
