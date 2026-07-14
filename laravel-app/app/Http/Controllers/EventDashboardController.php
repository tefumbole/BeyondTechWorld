<?php

namespace App\Http\Controllers;

use App\Event;
use App\Services\EventService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class EventDashboardController extends Controller
{
    protected $user;
    protected $all_permission = [];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            $role = Role::find($this->user->role_id);
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission) {
                $this->all_permission[] = $permission->name;
            }
            View::share('all_permission', $this->all_permission);

            return $next($request);
        });
    }

    protected function authorizeEvents()
    {
        if (! in_array('events_module', $this->all_permission, true)
            && ! in_array('events.view', $this->all_permission, true)) {
            abort(403, 'You are not allowed to access Events.');
        }
    }

    public function index(EventService $eventService)
    {
        $this->authorizeEvents();

        $stats = $eventService->dashboardStats();
        $recentEvents = Event::with(['customer', 'publication'])
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();
        $upcoming = Event::with(['customer', 'publication'])
            ->whereNotNull('event_start_at')
            ->where('event_start_at', '>=', now())
            ->whereNotIn('internal_status', ['cancelled', 'completed'])
            ->orderBy('event_start_at')
            ->limit(8)
            ->get();

        return view('events.dashboard', compact('stats', 'recentEvents', 'upcoming'));
    }
}
