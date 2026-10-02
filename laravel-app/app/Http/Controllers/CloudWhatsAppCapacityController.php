<?php

namespace App\Http\Controllers;

use App\Cloud\CloudTenant;
use App\Cloud\CloudWhatsAppConnection;
use App\Services\Cloud\WhatsAppCapacityService;
use Illuminate\Http\Request;

/**
 * Platform view of WhatsApp session slots. Session keys are not loaded.
 */
class CloudWhatsAppCapacityController extends Controller
{
    public function index(Request $request)
    {
        $denied = $this->platformDenied();
        if ($denied) {
            return $denied;
        }
        $connections = CloudWhatsAppConnection::orderBy('id', 'desc')->limit(200)->get();
        $tenants = CloudTenant::whereIn('id', $connections->pluck('cloud_tenant_id')->all() ?: [0])->get()->keyBy('id');
        $capacity = app(WhatsAppCapacityService::class);

        return view('cloud.admin.whatsapp-capacity', [
            'snapshot' => $capacity->snapshot(),
            'connections' => $connections,
            'tenants' => $tenants,
            'capacity' => $capacity,
        ]);
    }

    protected function platformDenied()
    {
        $user = auth()->user();
        if (! $user || ! in_array((int) $user->role_id, [1, 2], true)) {
            return response('Forbidden', 403);
        }

        return null;
    }
}
