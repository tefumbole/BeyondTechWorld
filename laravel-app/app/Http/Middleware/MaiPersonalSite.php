<?php

namespace App\Http\Middleware;

use App\Support\MaiHost;
use Closure;

/**
 * A Was MAI login stays in the WhatsApp hub and its announcements.
 */
class MaiPersonalSite
{
    public function handle($request, Closure $next)
    {
        if (! MaiHost::is($request)) {
            return $next($request);
        }
        if ($this->allowed($request)) {
            return $next($request);
        }

        return redirect()->route($request->user() ? 'whatsapp.chats' : 'whatsapp.mai');
    }

    protected function allowed($request)
    {
        if ($request->is(
            'login',
            'logout',
            'whatsapp-mai',
            'whatsapp-mai/*',
            'cloud/register',
            'cloud/register/*',
            'cloud/login',
            'cloud/login/*',
            'admin/whatsapp/chats',
            'admin/whatsapp/chats/*',
            'admin/whatsapp/people',
            'admin/whatsapp/people/*',
            'admin/whatsapp/groups',
            'admin/whatsapp/groups/*',
            'css/*',
            'js/*',
            'branding/*',
            'public/*',
            'images/*',
            'fonts/*'
        )) {
            return true;
        }
        $name = (string) optional($request->route())->getName();
        if ($name === 'beyond.home' || $name === 'mai.customer.home') {
            return ! $request->user();
        }
        if ($name === '') {
            return $request->is('/') || $request->is('mai');
        }

        return strpos($name, 'whatsapp.chats') === 0
            || strpos($name, 'whatsapp.mai') === 0
            || strpos($name, 'whatsapp.people') === 0
            || strpos($name, 'whatsapp.groups') === 0
            || strpos($name, 'cloud.register') === 0
            || strpos($name, 'cloud.login') === 0;
    }
}
