<?php

namespace App\Http\Controllers;

use App\Services\Cloud\CloudCompanySession;
use App\Services\Cloud\CloudTenantContext;
use App\Services\Cloud\CloudTenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CloudCompanyController extends Controller
{
    public function switchCompany(Request $request)
    {
        $user = Auth::user();
        $id = (int) $request->input('cloud_tenant_id');
        $membership = app(CloudTenantResolver::class)->activeMemberships($user)
            ->first(function ($row) use ($id) {
                return (int) $row->cloud_tenant_id === $id;
            });
        if (! $membership || ! $membership->cloudTenant) {
            return response('Forbidden', 403);
        }
        app(CloudCompanySession::class)->forgetTenantState();
        session([CloudTenantResolver::SESSION_KEY => $membership->cloudTenant->id]);
        app(CloudTenantContext::class)->set($membership->cloudTenant);

        return redirect()->back()->with('message', $membership->cloudTenant->name);
    }
}
