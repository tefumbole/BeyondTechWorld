<?php

namespace App\Http\Controllers;

use App\Event;
use App\Services\EventPublicationService;
use App\Services\EventService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class EventPublicationController extends Controller
{
    protected $all_permission = [];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $role = Role::find(Auth::user()->role_id);
            foreach (Role::findByName($role->name)->permissions as $permission) {
                $this->all_permission[] = $permission->name;
            }
            View::share('all_permission', $this->all_permission);

            return $next($request);
        });
    }

    protected function can($perm, $fallback = null)
    {
        if (in_array($perm, $this->all_permission, true)) {
            return;
        }
        if ($fallback && in_array($fallback, $this->all_permission, true)) {
            return;
        }
        abort(403);
    }

    public function update(Request $request, $eventId, EventPublicationService $pubService)
    {
        $this->can('events.manage_publication');

        $event = Event::with('publication')->findOrFail($eventId);
        $data = $request->validate([
            'publish_on_website' => 'nullable|boolean',
            'public_title' => 'nullable|string|max:255',
            'public_summary' => 'nullable|string|max:500',
            'public_description' => 'nullable|string',
            'public_venue' => 'nullable|string|max:255',
            'public_location' => 'nullable|string|max:255',
            'public_contact_name' => 'nullable|string|max:255',
            'public_contact_phone' => 'nullable|string|max:64',
            'public_contact_email' => 'nullable|email|max:255',
            'registration_url' => 'nullable|url|max:2048',
            'ticket_url' => 'nullable|url|max:2048',
            'external_url' => 'nullable|url|max:2048',
            'visibility_at' => 'nullable|date',
            'unpublish_at' => 'nullable|date',
            'is_featured' => 'nullable|boolean',
            'display_order' => 'nullable|integer|min:0',
            'show_event_time' => 'nullable|boolean',
            'show_setup_info' => 'nullable|boolean',
            'show_countdown' => 'nullable|boolean',
            'countdown_target_type' => 'nullable|string|max:64',
            'countdown_custom_at' => 'nullable|date',
            'countdown_visible_from' => 'nullable|date',
            'countdown_completion_message' => 'nullable|string|max:255',
            'hide_countdown_after_completion' => 'nullable|boolean',
            'public_status_override' => 'nullable|string|max:64',
            'public_announcement' => 'nullable|string',
            'public_slug' => 'nullable|string|max:191',
        ]);

        $data['publish_on_website'] = $request->boolean('publish_on_website');
        $data['is_featured'] = $request->boolean('is_featured');
        $data['show_event_time'] = $request->boolean('show_event_time', true);
        $data['show_setup_info'] = $request->boolean('show_setup_info');
        $data['show_countdown'] = $request->boolean('show_countdown');
        $data['hide_countdown_after_completion'] = $request->boolean('hide_countdown_after_completion', true);

        if (! empty($data['public_description'])) {
            $data['public_description'] = $pubService->sanitizePublicHtml($data['public_description']);
        }

        $file = $request->file('public_flyer');
        $pubService->savePublication($event, $data, $file);

        return redirect()->route('events.show', ['id' => $event->id, 'tab' => 'publication'])
            ->with('message', 'Public website settings saved.');
    }

    public function publish($eventId, EventPublicationService $pubService)
    {
        $this->can('events.publish', 'events.manage_publication');

        $event = Event::with('publication')->findOrFail($eventId);
        try {
            $pubService->publish($event);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['publish' => $e->getMessage()]);
        }

        return back()->with('message', 'Event published on the website.');
    }

    public function unpublish($eventId, EventPublicationService $pubService)
    {
        $this->can('events.unpublish', 'events.manage_publication');

        $event = Event::findOrFail($eventId);
        $pubService->unpublish($event);

        return back()->with('message', 'Event removed from public website.');
    }
}
