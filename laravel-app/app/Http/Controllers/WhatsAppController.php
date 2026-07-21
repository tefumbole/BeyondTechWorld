<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\WhatsAppService;
use Auth;
use Spatie\Permission\Models\Role;

class WhatsAppController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService = null)
    {
        // Resolve via container so TwilioWhatsAppService is injected (fixes artisan route:list / DI).
        $this->whatsappService = $whatsappService ?: app(WhatsAppService::class);
    }

    public function send()
    {
        if (!Auth::check()) {
            abort(404);
        }

        $role = Role::find(Auth::user()->role_id);
        if (!$role || !in_array((int) Auth::user()->role_id, [1, 2], true)) {
            abort(403, 'Unauthorized');
        }

        if (app()->environment('production')) {
            abort(404);
        }

        $to = "+923410060960";
        $message = "test message";
        $response = $this->whatsappService->sendMessage($to, $message);

        return response()->json(['message' => $response]);
    }
}
