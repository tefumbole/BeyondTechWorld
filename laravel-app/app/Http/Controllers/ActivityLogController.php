<?php

namespace App\Http\Controllers;

use App\ActivityLog;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    protected function authorizeLogs()
    {
        if (! Auth::check()) {
            abort(403);
        }
        $roleId = (int) Auth::user()->role_id;
        if (in_array($roleId, [1, 2], true)) {
            return;
        }

        $perm = DB::table('permissions')->where('name', 'general_setting')->first();
        if ($perm) {
            $active = DB::table('role_has_permissions')->where([
                ['permission_id', $perm->id],
                ['role_id', $roleId],
            ])->first();
            if ($active) {
                return;
            }
        }

        abort(403, 'You are not allowed to view activity logs.');
    }

    public function index(Request $request)
    {
        $this->authorizeLogs();

        $tab = $request->get('tab', 'all');
        $q = trim((string) $request->get('q', ''));
        $action = trim((string) $request->get('action', ''));

        $query = ActivityLog::query()->orderByDesc('created_at');

        if ($tab === 'navigation') {
            $query->whereIn('action', ['view', 'navigate']);
        } elseif ($tab === 'actions') {
            $query->whereIn('action', ['create', 'update', 'delete', 'action']);
        } elseif ($tab === 'clicks') {
            $query->where('action', 'click');
        } elseif ($tab === 'auth') {
            $query->whereIn('action', ['login', 'logout', 'failed_login']);
        }

        if ($action !== '' && $action !== 'all') {
            $query->where('action', $action);
        }

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($w) use ($like) {
                $w->where('user_name', 'like', $like)
                    ->orWhere('summary', 'like', $like)
                    ->orWhere('entity', 'like', $like)
                    ->orWhere('path', 'like', $like)
                    ->orWhere('ip_address', 'like', $like);
            });
        }

        $items = $query->paginate(50)->appends($request->query());

        $counts = [
            'all' => ActivityLog::count(),
            'navigation' => ActivityLog::whereIn('action', ['view', 'navigate'])->count(),
            'actions' => ActivityLog::whereIn('action', ['create', 'update', 'delete', 'action'])->count(),
            'clicks' => ActivityLog::where('action', 'click')->count(),
            'auth' => ActivityLog::whereIn('action', ['login', 'logout', 'failed_login'])->count(),
        ];

        return view('activity_logs.index', [
            'items' => $items,
            'tab' => $tab,
            'q' => $q,
            'action' => $action,
            'counts' => $counts,
        ]);
    }

    public function storeClicks(Request $request)
    {
        if (! Auth::check()) {
            return response()->json(['ok' => false], 401);
        }

        $clicks = $request->input('clicks', []);
        if (! is_array($clicks)) {
            return response()->json(['ok' => false, 'error' => 'Invalid payload'], 422);
        }

        $clicks = array_slice($clicks, 0, 40);
        $saved = 0;
        foreach ($clicks as $click) {
            if (! is_array($click)) {
                continue;
            }
            $label = isset($click['label']) ? trim((string) $click['label']) : '';
            $href = isset($click['href']) ? trim((string) $click['href']) : '';
            $tag = isset($click['tag']) ? trim((string) $click['tag']) : 'element';
            if ($label === '' && $href === '') {
                continue;
            }
            // Skip logging clicks that are clearly on the logs page itself
            if ($href && stripos($href, 'activity-logs') !== false) {
                continue;
            }

            ActivityLogService::log([
                'action' => 'click',
                'entity' => 'ui',
                'summary' => 'Clicked '.($label !== '' ? $label : $tag).($href ? ' → '.$href : ''),
                'method' => 'CLICK',
                'path' => $href ? substr($href, 0, 500) : null,
                'metadata' => [
                    'tag' => $tag,
                    'page' => isset($click['page']) ? $click['page'] : null,
                    'x' => isset($click['x']) ? (int) $click['x'] : null,
                    'y' => isset($click['y']) ? (int) $click['y'] : null,
                ],
            ], $request);
            $saved++;
        }

        return response()->json(['ok' => true, 'saved' => $saved]);
    }

    public function destroy(Request $request)
    {
        $this->authorizeLogs();

        if ($request->input('all') === '1' || $request->input('all') === true) {
            ActivityLog::query()->delete();

            return redirect()->route('activity-logs.index')->with('message', 'All activity logs cleared.');
        }

        $ids = (array) $request->input('ids', []);
        $ids = array_values(array_filter(array_map('strval', $ids)));
        if (! $ids) {
            return redirect()->route('activity-logs.index')->with('not_permitted', 'No logs selected.');
        }

        ActivityLog::whereIn('id', $ids)->delete();

        return redirect()->route('activity-logs.index')->with('message', count($ids).' log(s) deleted.');
    }
}
