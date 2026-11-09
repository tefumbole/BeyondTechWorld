<?php

namespace App\Http\Controllers;

use App\Event;
use App\EventReminder;
use App\Services\EventReminderService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class EventReminderController extends Controller
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

    protected function can($perm)
    {
        if (! in_array($perm, $this->all_permission, true)) {
            abort(403);
        }
    }

    public function index()
    {
        $this->can('event_reminders.view');

        $reminders = EventReminder::with(['event', 'creator'])
            ->orderByDesc('remind_at')
            ->paginate(30);
        $events = Event::orderByDesc('event_start_at')->limit(200)->get(['id', 'name', 'reference_no']);

        return view('events.reminders.index', compact('reminders', 'events'));
    }

    public function schedule(Request $request, EventReminderService $service)
    {
        $this->can('event_reminders.create');

        $data = $this->reminderData($request);
        if (empty($data['event_id'])) {
            return back()->withErrors(['event_id' => 'Select an event.'])->withInput();
        }
        $event = Event::findOrFail($data['event_id']);
        unset($data['event_id']);
        $service->create($event, $data);

        return redirect()->route('events.reminders.index')
            ->with('message', 'Reminder scheduled for '.$event->name.'. It will not ask anyone to accept.');
    }

    public function storeForEvent(Request $request, $eventId, EventReminderService $service)
    {
        $this->can('event_reminders.create');

        $event = Event::findOrFail($eventId);
        $data = $this->reminderData($request);
        unset($data['event_id']);

        $service->create($event, $data);

        return redirect()->route('events.show', ['id' => $event->id, 'tab' => 'reminders'])
            ->with('message', 'Reminder scheduled.');
    }

    protected function reminderData(Request $request)
    {
        $data = $request->validate([
            'event_id' => 'nullable|string|max:64',
            'remind_at' => 'required|date|after:now',
            'message' => 'nullable|string|max:2000',
            'recipient_type' => 'nullable|in:all_workers,client,custom',
            'recipient_phone' => 'nullable|string|max:64',
        ]);
        $data['remind_at'] = Carbon::parse($data['remind_at'])->format('Y-m-d H:i:s');

        return $data;
    }

    public function destroy($id)
    {
        $this->can('event_reminders.create');

        $reminder = EventReminder::findOrFail($id);
        if ($reminder->sent_at) {
            return back()->withErrors(['reminder' => 'Sent reminders cannot be deleted.']);
        }
        $reminder->delete();

        return back()->with('message', 'Reminder cancelled.');
    }
}
