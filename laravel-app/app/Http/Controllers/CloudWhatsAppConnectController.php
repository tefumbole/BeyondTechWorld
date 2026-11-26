<?php

namespace App\Http\Controllers;

use App\Cloud\CloudWhatsAppConnection;
use App\Services\Cloud\CloudWhatsAppConnectService;
use Illuminate\Http\Request;

/**
 * Owner-only WhatsApp connection for the signed-in company.
 * The posted company id is ignored.
 */
class CloudWhatsAppConnectController extends Controller
{
    public function connect(Request $request)
    {
        $tenant = $request->attributes->get('cloudTenant');
        try {
            $link = $request->input('link') === 'code' ? 'code' : 'qr';
            app(CloudWhatsAppConnectService::class)->begin($tenant, $request->user(), $link);
        } catch (\Exception $e) {
            return redirect()->route('cloud.messaging')->with('not_permitted', $e->getMessage());
        }

        $notice = $request->input('link') === 'code'
            ? 'Use the link code in WhatsApp under Linked devices. If no code is shown, scan the QR from Link a device.'
            : 'Scan the QR from WhatsApp, Linked devices. It is not connected until the number matches.';

        return redirect()->route('cloud.messaging')->with('message', $notice);
    }

    public function qr(Request $request, $connectionId)
    {
        if (! config('cloud.whatsapp.provisioning_enabled')) {
            abort(404);
        }
        $tenant = $request->attributes->get('cloudTenant');
        $connection = CloudWhatsAppConnection::where('cloud_tenant_id', $tenant->id)->find($connectionId);
        if (! $connection) {
            abort(404);
        }
        try {
            $qr = app(CloudWhatsAppConnectService::class)->qr($tenant, $connection, $request->user());
        } catch (\Exception $e) {
            abort(403);
        }
        if (! $qr) {
            abort(404);
        }

        if ($request->get('image') && strpos((string) $qr, 'stub-') !== 0 && class_exists(\SimpleSoftwareIO\QrCode\Generator::class)) {
            $svg = (new \SimpleSoftwareIO\QrCode\Generator())->format('svg')->size(280)->margin(1)->generate($qr);

            return response((string) $svg, 200, [
                'Content-Type' => 'image/svg+xml',
                'Cache-Control' => 'no-store, private',
            ]);
        }

        return response($qr, 200, [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function refresh(Request $request, $connectionId)
    {
        $tenant = $request->attributes->get('cloudTenant');
        $connection = CloudWhatsAppConnection::where('cloud_tenant_id', $tenant->id)->findOrFail($connectionId);
        try {
            app(CloudWhatsAppConnectService::class)->refresh($tenant, $connection, $request->user());
        } catch (\Exception $e) {
            return redirect()->route('cloud.messaging')->with('not_permitted', $e->getMessage());
        }

        return redirect()->route('cloud.messaging');
    }

    public function disconnect(Request $request, $connectionId)
    {
        $tenant = $request->attributes->get('cloudTenant');
        $connection = CloudWhatsAppConnection::where('cloud_tenant_id', $tenant->id)->findOrFail($connectionId);
        $request->validate(['confirm' => 'required|in:DISCONNECT']);
        try {
            app(CloudWhatsAppConnectService::class)->disconnect($tenant, $connection, $request->user());
        } catch (\Exception $e) {
            return redirect()->route('cloud.messaging')->with('not_permitted', $e->getMessage());
        }

        return redirect()->route('cloud.messaging')->with('message', 'WhatsApp was disconnected. Conversations were kept.');
    }
}
