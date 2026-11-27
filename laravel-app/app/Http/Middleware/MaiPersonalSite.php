<?php

namespace App\Http\Middleware;

use App\Support\MaiHost;
use Closure;

/**
 * The opened WhatsApp on the Mai host is the hub without the accounts module.
 */
class MaiPersonalSite
{
    public function handle($request, Closure $next)
    {
        if (! MaiHost::is($request)) {
            return $next($request);
        }
        if ($request->is('accounts') || $request->is('accounts/*') || $request->is('money-transfers') || $request->is('money-transfers/*') || $request->is('report/JE')) {
            return redirect()->route('whatsapp.chats');
        }

        return $next($request);
    }
}
