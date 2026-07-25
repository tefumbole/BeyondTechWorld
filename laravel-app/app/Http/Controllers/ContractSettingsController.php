<?php

namespace App\Http\Controllers;

use App\ContractRateCategory;
use App\ContractSetting;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class ContractSettingsController extends Controller
{
    protected $all_permission = [];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (Auth::check()) {
                $role = Role::find(Auth::user()->role_id);
                if ($role) {
                    foreach (Role::findByName($role->name)->permissions as $permission) {
                        $this->all_permission[] = $permission->name;
                    }
                }
            }
            View::share('all_permission', $this->all_permission);

            return $next($request);
        });
    }

    protected function gate()
    {
        if (! in_array('contracts.settings', $this->all_permission, true)
            && ! in_array('contracts_module', $this->all_permission, true)) {
            abort(403);
        }
    }

    public function edit()
    {
        $this->gate();
        $keys = [
            'number_prefix', 'default_validity_days', 'company_legal_name', 'company_address',
            'default_jurisdiction', 'default_admin_signer_user_id', 'default_signature_workflow',
            'expiry_alert_days', 'expiry_alerts_enabled',
        ];
        $settings = [];
        foreach ($keys as $k) {
            $settings[$k] = ContractSetting::getValue($k);
        }

        return view('contracts.settings', [
            'settings' => $settings,
            'rates' => ContractRateCategory::orderBy('daily_rate', 'desc')->get(),
            'users' => User::where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })->orderBy('name')->limit(200)->get(['id', 'name', 'email']),
            'ctTab' => 'contracts.settings',
        ]);
    }

    public function update(Request $request)
    {
        $this->gate();
        $map = [
            'number_prefix' => $request->get('number_prefix', 'CNT'),
            'default_validity_days' => (int) $request->get('default_validity_days', 14),
            'company_legal_name' => $request->get('company_legal_name', 'Beyond Enterprise'),
            'company_address' => $request->get('company_address', ''),
            'default_jurisdiction' => $request->get('default_jurisdiction', 'Republic of Cameroon'),
            'default_admin_signer_user_id' => $request->get('default_admin_signer_user_id'),
            'default_signature_workflow' => $request->get('default_signature_workflow', 'hybrid'),
            'expiry_alert_days' => (int) $request->get('expiry_alert_days', 30),
            'expiry_alerts_enabled' => (bool) $request->get('expiry_alerts_enabled', 0),
        ];
        foreach ($map as $k => $v) {
            ContractSetting::setValue($k, $v);
        }

        if ($request->has('rates') && is_array($request->rates)) {
            foreach ($request->rates as $id => $rate) {
                ContractRateCategory::where('id', $id)->update(['daily_rate' => (int) $rate]);
            }
        }

        return back()->with('message', 'Contract settings saved.');
    }
}
