<?php

namespace App\Http\Controllers;

use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantType;
use App\Services\Cloud\CloudPortalService;

/**
 * Public company page. It does not sign the visitor in and does not switch their company.
 */
class CloudPublicPageController extends Controller
{
    public function show($slug)
    {
        $tenant = CloudTenant::where('slug', $slug)->where('type', CloudTenantType::CUSTOMER)->first();
        if (! $tenant) {
            abort(404);
        }
        $portal = app(CloudPortalService::class);

        return view('cloud.public.company', [
            'tenant' => $tenant,
            'summary' => $portal->setting($tenant, 'business_summary'),
            'services' => $portal->setting($tenant, 'services'),
            'logoUrl' => $portal->logoFile($tenant) ? route('cloud.logo', ['uuid' => $tenant->uuid]) : null,
        ]);
    }
}
