<?php

namespace App\Http\Controllers\Property;

use App\Http\Controllers\Controller;
use App\Property\BillPaymentRequest;
use App\Property\MaintenanceRequest;
use App\Property\Property;
use App\Property\PropertyUnit;
use App\Property\Tenancy;
use App\Services\Property\MaintenanceService;
use App\Services\Property\RentBillingService;
use App\Services\Property\TenancyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class PropertyController extends Controller
{
    public function index()
    {
        if ($deny = $this->denyUnless(['properties.view', 'properties.manage', 'tenancies.view'])) {
            return $deny;
        }

        return view('property.index', [
            'properties' => Property::orderBy('name')->get(),
            'units' => PropertyUnit::orderBy('code')->get(),
            'tenancies' => Tenancy::orderByDesc('id')->limit(100)->get(),
        ]);
    }

    public function storeProperty(Request $request, TenancyService $tenancies)
    {
        if ($deny = $this->denyUnless(['properties.manage'])) {
            return $deny;
        }
        $tenancies->createProperty([
            'name' => $request->input('name'),
            'code' => $request->input('code'),
            'address' => $request->input('address'),
            'city' => $request->input('city'),
            'country' => $request->input('country'),
            'created_by' => Auth::id(),
        ]);

        return back()->with('message', 'Property saved.');
    }

    public function storeUnit(Request $request, TenancyService $tenancies)
    {
        if ($deny = $this->denyUnless(['properties.manage'])) {
            return $deny;
        }
        $result = $tenancies->createUnit($request->only(['property_id', 'code', 'name', 'unit_type', 'rent_amount', 'billing_frequency']));
        if (empty($result['ok'])) {
            return back()->with('not_permitted', 'That unit could not be saved.');
        }

        return back()->with('message', 'Unit saved.');
    }

    public function storeTenancy(Request $request, TenancyService $tenancies)
    {
        if ($deny = $this->denyUnless(['tenancies.manage'])) {
            return $deny;
        }
        $result = $tenancies->open($request->only([
            'customer_id', 'unit_id', 'start_date', 'end_date', 'rent_amount',
            'billing_frequency', 'security_deposit', 'due_day', 'status',
        ]) + ['created_by' => Auth::id()]);
        if (empty($result['ok'])) {
            return back()->with('not_permitted', 'That tenancy could not be opened.');
        }

        return back()->with('message', 'Tenancy saved.');
    }

    public function storeRentPayment(Request $request, RentBillingService $billing)
    {
        if ($deny = $this->denyUnless(['rent.manage'])) {
            return $deny;
        }
        $tenancy = Tenancy::find($request->input('tenancy_id'));
        if (! $tenancy) {
            return back()->with('not_permitted', 'Tenancy not found.');
        }
        $billing->recordPayment($tenancy, $request->input('amount'), $request->input('reference'), Auth::id());

        return back()->with('message', 'Rent payment recorded.');
    }

    public function maintenance()
    {
        if ($deny = $this->denyUnless(['maintenance.view', 'maintenance.manage'])) {
            return $deny;
        }

        return view('property.maintenance', [
            'requests' => MaintenanceRequest::orderByDesc('id')->limit(100)->get(),
        ]);
    }

    public function assignMaintenance(Request $request, MaintenanceService $maintenance, $id)
    {
        if ($deny = $this->denyUnless(['maintenance.manage'])) {
            return $deny;
        }
        $row = MaintenanceRequest::findOrFail($id);
        $maintenance->assign($row, $request->input('employee_id'));

        return back()->with('message', 'Maintenance assignment saved.');
    }

    public function maintenanceStatus(Request $request, MaintenanceService $maintenance, $id)
    {
        if ($deny = $this->denyUnless(['maintenance.manage'])) {
            return $deny;
        }
        $row = MaintenanceRequest::findOrFail($id);
        $maintenance->setStatus($row, $request->input('status'));

        return back()->with('message', 'Maintenance status saved.');
    }

    public function bills()
    {
        if ($deny = $this->denyUnless(['billpayments.view', 'billpayments.manage'])) {
            return $deny;
        }

        return view('property.bills', [
            'requests' => BillPaymentRequest::orderByDesc('id')->limit(100)->get(),
        ]);
    }

    protected function denyUnless(array $names)
    {
        $user = Auth::user();
        $role = $user ? Role::find($user->role_id) : null;
        if ($role) {
            foreach ($names as $name) {
                try {
                    if ($role->hasPermissionTo($name)) {
                        return null;
                    }
                } catch (\Exception $e) {
                }
            }
        }

        return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this.');
    }
}
