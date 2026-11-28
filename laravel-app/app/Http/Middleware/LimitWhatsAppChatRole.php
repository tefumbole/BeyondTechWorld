<?php

namespace App\Http\Middleware;

use App\Services\WhatsApp\WhatsAppChatAccess;
use Closure;

/**
 * A scanned WhatsApp login stays inside chats.
 */
class LimitWhatsAppChatRole
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();
        $access = app(WhatsAppChatAccess::class);
        if (! $user || (! $access->holds($user) && ! $access->sessionLimited())) {
            return $next($request);
        }
        if ($this->allowed(trim($request->path(), '/'))) {
            return $next($request);
        }

        return redirect()->route('whatsapp.chats');
    }

    protected function allowed($path)
    {
        if ($path === 'logout' || $path === 'login') {
            return true;
        }
        if (strpos($path, 'admin/whatsapp/chats') === 0) {
            return true;
        }
        if (strpos($path, 'whatsapp-mai') === 0) {
            return true;
        }

        return false;
    }
}
