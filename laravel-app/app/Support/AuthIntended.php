<?php

namespace App\Support;

use App\User;
use Illuminate\Http\Request;

/**
 * Remember the page a visitor tried to open (WhatsApp grade link, etc.)
 * and send them there after login / OTP instead of a generic home screen.
 */
class AuthIntended
{
    public static function rememberFromRequest(Request $request)
    {
        $candidate = $request->input('redirect')
            ?: $request->query('redirect')
            ?: $request->session()->get('beyond_intended')
            ?: $request->session()->get('url.intended');
        $path = self::safePath($candidate);
        if ($path) {
            $request->session()->put('beyond_intended', $path);
        }
    }

    public static function loginUrl(Request $request)
    {
        $path = self::safePath($request->getRequestUri());
        if (! $path) {
            return url('/login');
        }

        return url('/login').'?redirect='.rawurlencode($path);
    }

    /**
     * Destination after a successful staff login. Deep links win over the
     * intern / supervisor home screens.
     *
     * @return string
     */
    public static function afterLogin(User $user, $default = '/admin')
    {
        $intended = self::pull();
        if ($intended) {
            return $intended;
        }

        $intern = InternCompliance::postLoginRedirect($user);
        if ($intern) {
            return $intern;
        }

        $supervisor = InternCompliance::supervisorPostLoginRedirect($user);
        if ($supervisor) {
            return $supervisor;
        }

        return $default;
    }

    public static function pull()
    {
        $session = session();
        $candidate = $session->pull('beyond_intended') ?: $session->pull('url.intended');

        return self::safePath($candidate);
    }

    public static function safePath($url)
    {
        if (! is_string($url) || $url === '') {
            return null;
        }
        $url = trim($url);
        $path = $url;
        if (preg_match('#^https?://#i', $url) || strpos($url, '//') === 0) {
            $parts = parse_url($url);
            $host = isset($parts['host']) ? strtolower($parts['host']) : '';
            $appHost = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
            if ($host !== '' && $appHost !== '' && $host !== $appHost) {
                return null;
            }
            $path = isset($parts['path']) ? $parts['path'] : '/';
            if (! empty($parts['query'])) {
                $path .= '?'.$parts['query'];
            }
        }
        if (strpos($path, '/') !== 0 || strpos($path, '//') === 0) {
            return null;
        }
        $bare = strtok($path, '?') ?: $path;
        if (preg_match('#^/(login|logout|otp|otp-verification|staff-otp-login|staff-set-password|forgot-password|beyond/login)(/|$)#', $bare)) {
            return null;
        }

        return $path;
    }
}
