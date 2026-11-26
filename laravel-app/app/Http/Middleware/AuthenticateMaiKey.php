<?php

namespace App\Http\Middleware;

use App\Mai\MaiApiKey;
use App\User;
use Closure;

class AuthenticateMaiKey
{
    public function handle($request, Closure $next, $scope = null)
    {
        if (! config('cloud.mai_customer.enabled')) {
            return response()->json(['ok' => false, 'error' => 'Whats MAI is not open.'], 404);
        }
        $header = $this->bearer($request);
        if (strpos($header, 'mai_') !== 0) {
            return response()->json(['ok' => false, 'error' => 'Sign in with an API key.'], 401);
        }
        $key = MaiApiKey::where('key_hash', hash('sha256', $header))->whereNull('revoked_at')->first();
        $user = $key ? User::find($key->user_id) : null;
        if (! $key || ! $user) {
            return response()->json(['ok' => false, 'error' => 'That API key is not active.'], 401);
        }
        if ($scope && ! $key->allows($scope)) {
            return response()->json(['ok' => false, 'error' => 'This key cannot do that.'], 403);
        }
        $key->last_used_at = now();
        $key->save();
        $request->attributes->set('mai_user', $user);
        $request->attributes->set('mai_key', $key);

        return $next($request);
    }

    protected function bearer($request)
    {
        $header = (string) $request->header('Authorization', '');
        if (stripos($header, 'Bearer ') !== 0) {
            return '';
        }

        return trim(substr($header, 7));
    }
}
