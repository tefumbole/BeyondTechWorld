<?php

namespace App\Http\Controllers;

use App\Event;
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

    public function index()
    {
        $this->authorizeEvents();

        $cards = [
            ['view' => 'upcoming', 'label' => 'Upcoming events', 'count' => Event::upcoming()->count(), 'note' => 'Starts after today', 'accent' => '#f59e0b'],
            ['view' => 'progress', 'label' => 'In progress', 'count' => Event::inProgressWindow()->count(), 'note' => 'Happening now', 'accent' => '#7b61ff'],
            ['view' => 'completed', 'label' => 'Completed events', 'count' => Event::finished()->count(), 'note' => 'Finished and past events', 'accent' => '#10b981'],
            ['view' => 'published', 'label' => 'Published events', 'count' => Event::onWebsite()->count(), 'note' => 'Live on the website', 'accent' => '#0b3f90'],
        ];
        $chart = [
            ['label' => 'Upcoming', 'count' => $cards[0]['count'], 'color' => '#f59e0b'],
            ['label' => 'In progress', 'count' => $cards[1]['count'], 'color' => '#7b61ff'],
            ['label' => 'Completed', 'count' => $cards[2]['count'], 'color' => '#10b981'],
            ['label' => 'Published', 'count' => $cards[3]['count'], 'color' => '#0b3f90'],
        ];
        $chartMax = max(1, max(array_column($chart, 'count')));
        foreach ($chart as $index => $bar) {
            $chart[$index]['height'] = max(10, (int) round($bar['count'] / $chartMax * 160));
            $chart[$index]['width'] = max(4, (int) round($bar['count'] / $chartMax * 100));
        }

        return view('events.dashboard', compact('cards', 'chart', 'chartMax'));
    }
}
