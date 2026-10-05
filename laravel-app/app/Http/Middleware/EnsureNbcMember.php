<?php

namespace App\Http\Middleware;

use App\Nbc\NbcMember;
use Closure;

class EnsureNbcMember
{
    public function handle($request, Closure $next)
    {
        $id = session('nbc_member_id');
        $member = $id ? NbcMember::where('id', $id)->where('status', 'active')->first() : null;
        if (! $member) {
            return redirect()->route('nbc.login');
        }
        $request->attributes->set('nbcMember', $member);
        view()->share('nbcMember', $member);

        return $next($request);
    }
}
