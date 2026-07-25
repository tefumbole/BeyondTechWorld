<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogService;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Logs authenticated admin/system activity after the response is sent.
 * Page views (GET) and mutations (POST/PUT/PATCH/DELETE) are recorded.
 */
class LogActivity
{
    public function handle($request, Closure $next)
    {
        return $next($request);
    }

    public function terminate($request, $response)
    {
        try {
            if (ActivityLogService::shouldSkip($request)) {
                return;
            }

            // Only log signed-in users (system usage). Login failures are logged elsewhere.
            if (! Auth::check()) {
                return;
            }

            $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null;
            // Skip pure redirects that bounce immediately? Still useful for navigation — keep them.
            if ($status === 404 || $status === 419) {
                return;
            }

            $action = ActivityLogService::actionFromRequest($request);
            $entity = ActivityLogService::entityFromRequest($request);

            ActivityLogService::log([
                'action' => $action,
                'entity' => $entity,
                'summary' => ActivityLogService::summarizeRequest($request, $action),
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'status_code' => $status,
                'metadata' => [
                    'route' => optional($request->route())->getName(),
                    'query' => $request->query(),
                ],
            ], $request);
        } catch (\Throwable $e) {
            // never break the response pipeline
        }
    }
}
