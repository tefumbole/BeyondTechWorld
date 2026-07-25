<?php

namespace App\Services;

use App\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ActivityLogService
{
    /** Paths / prefixes that must never be logged (noise or recursion). */
    protected static $skipPathContains = [
        'activity-logs',
        'activity_logs',
        '_debugbar',
        'telescope',
        'horizon',
        'livewire',
        'sanctum/csrf-cookie',
        'service-worker',
        'favicon',
        '.map',
        'public/css',
        'public/js',
        'public/vendor',
        'public/images',
        'public/fonts',
        'public/logo',
    ];

    public static function log(array $data, Request $request = null)
    {
        try {
            $action = isset($data['action']) ? substr((string) $data['action'], 0, 80) : null;
            if (! $action) {
                return null;
            }

            $entity = isset($data['entity']) ? substr((string) $data['entity'], 0, 120) : null;
            if ($entity === 'activity_logs') {
                return null;
            }

            $user = Auth::user();
            $roleName = null;
            if ($user) {
                try {
                    $role = \Spatie\Permission\Models\Role::find($user->role_id);
                    $roleName = $role ? $role->name : null;
                } catch (\Throwable $e) {
                    $roleName = null;
                }
            }

            $meta = isset($data['metadata']) ? $data['metadata'] : null;
            if (is_string($meta)) {
                $decoded = json_decode($meta, true);
                $meta = json_last_error() === JSON_ERROR_NONE ? $decoded : ['raw' => $meta];
            }

            return ActivityLog::create([
                'id' => (string) Str::uuid(),
                'user_id' => isset($data['user_id'])
                    ? (string) $data['user_id']
                    : ($user ? (string) $user->id : null),
                'user_name' => isset($data['user_name'])
                    ? $data['user_name']
                    : ($user ? ($user->name ?: $user->email) : null),
                'user_role' => isset($data['user_role']) ? $data['user_role'] : $roleName,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => isset($data['entity_id']) ? substr((string) $data['entity_id'], 0, 120) : null,
                'summary' => isset($data['summary']) ? substr((string) $data['summary'], 0, 500) : null,
                'metadata' => $meta,
                'ip_address' => isset($data['ip_address'])
                    ? $data['ip_address']
                    : ($request ? self::clientIp($request) : null),
                'method' => isset($data['method']) ? substr((string) $data['method'], 0, 10) : null,
                'path' => isset($data['path']) ? substr((string) $data['path'], 0, 500) : null,
                'status_code' => isset($data['status_code']) ? (int) $data['status_code'] : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('[activity-log] failed: '.$e->getMessage());

            return null;
        }
    }

    public static function shouldSkip(Request $request)
    {
        $path = ltrim($request->path(), '/');
        $full = $request->fullUrl();

        foreach (self::$skipPathContains as $needle) {
            if (stripos($path, $needle) !== false || stripos($full, $needle) !== false) {
                return true;
            }
        }

        // Skip obvious static file hits
        if (preg_match('/\.(css|js|png|jpe?g|gif|svg|ico|woff2?|ttf|map|webp)$/i', $path)) {
            return true;
        }

        // Skip HEAD / OPTIONS
        if (in_array(strtoupper($request->method()), ['HEAD', 'OPTIONS'], true)) {
            return true;
        }

        return false;
    }

    public static function actionFromRequest(Request $request)
    {
        $method = strtoupper($request->method());
        if ($method === 'GET') {
            return 'view';
        }
        if ($method === 'DELETE') {
            return 'delete';
        }
        if (in_array($method, ['PUT', 'PATCH'], true)) {
            return 'update';
        }
        // POST — treat destroy-like routes as delete
        $path = $request->path();
        if (preg_match('#/(delete|destroy|remove)(/|$)#i', $path) || $request->input('_method') === 'DELETE') {
            return 'delete';
        }
        if (preg_match('#/(update|edit|store-update)(/|$)#i', $path) || $request->input('_method') === 'PUT') {
            return 'update';
        }
        if (preg_match('#/(store|create|add)(/|$)#i', $path)) {
            return 'create';
        }

        return 'action';
    }

    public static function entityFromRequest(Request $request)
    {
        $path = trim($request->path(), '/');
        if ($path === '') {
            return 'home';
        }
        $parts = explode('/', $path);
        // Skip leading admin/
        if (isset($parts[0]) && in_array($parts[0], ['admin', 'api'], true) && isset($parts[1])) {
            return substr($parts[1], 0, 120);
        }

        return substr($parts[0], 0, 120);
    }

    public static function clientIp(Request $request)
    {
        $fwd = $request->header('X-Forwarded-For');
        if ($fwd) {
            return trim(explode(',', $fwd)[0]);
        }

        return $request->ip();
    }

    public static function summarizeRequest(Request $request, $action)
    {
        $route = $request->route();
        $name = $route ? $route->getName() : null;
        $path = '/'.ltrim($request->path(), '/');
        $label = $name ?: $path;

        if ($action === 'view') {
            return 'Opened '.$label;
        }

        return strtoupper($request->method()).' '.$label;
    }
}
