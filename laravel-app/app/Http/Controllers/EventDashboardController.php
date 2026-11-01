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
            ['view' => 'upcoming', 'label' => 'Upcoming events', 'count' => Event::upcoming()->count(), 'note' => 'Starts after today'],
            ['view' => 'progress', 'label' => 'In progress', 'count' => Event::inProgressWindow()->count(), 'note' => 'Happening now'],
            ['view' => 'completed', 'label' => 'Completed events', 'count' => Event::finished()->count(), 'note' => 'Finished and past events'],
            ['view' => 'published', 'label' => 'Published events', 'count' => Event::onWebsite()->count(), 'note' => 'Live on the website'],
        ];

        return view('events.dashboard', compact('cards'));
    }
}
