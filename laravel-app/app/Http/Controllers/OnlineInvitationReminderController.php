<?php

namespace App\Http\Controllers;

use App\OnlineInvitationEvent;
use App\OnlineInvitationReminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Role;

class OnlineInvitationReminderController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! Auth::check()) {
                return $next($request);
            }
            $role = Role::find(Auth::user()->role_id);
            $all_permission = [];
            if ($role) {
                foreach ($role->permissions as $permission) {
                    $all_permission[] = $permission->name;
                }
            }
            View::share('all_permission', $all_permission);

            return $next($request);
        });
    }

    protected function ensureAccess(): bool
    {
        if (! Auth::check()) {
            return false;
        }
        try {
            $role = Role::find(Auth::user()->role_id);
            if (! $role) {
                return false;
            }
            if ((int) Auth::user()->role_id <= 2) {
                return true;
            }

            return $role->hasPermissionTo('online_invitation_event')
                || $role->hasPermissionTo('online_invitation_send_invitation');
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }

    public function index()
    {
        if (! $this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Not allowed');
        }
        $reminders = OnlineInvitationReminder::with('event')->orderByDesc('id')->paginate(20);
        $events = OnlineInvitationEvent::where('is_active', 1)->orderByDesc('id')->get(['id', 'name']);

        return view('online_invitation.reminder.index', compact('reminders', 'events'));
    }

    public function store(Request $request)
    {
        if (! $this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Not allowed');
        }
        $data = $request->validate([
            'event_id' => 'required|integer|exists:online_invitation_events,id',
            'remind_at' => 'required|date',
            'message' => 'nullable|string|max:1000',
            'audience' => 'required|in:accepted,sent,all',
        ]);

        OnlineInvitationReminder::create([
            'event_id' => $data['event_id'],
            'remind_at' => $data['remind_at'],
            'message' => $data['message'] ?? null,
            'audience' => $data['audience'],
            'status' => 'scheduled',
            'created_by' => Auth::id(),
        ]);

        return redirect()->back()->with('message', 'Reminder scheduled.');
    }

    public function cancel($id)
    {
        if (! $this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Not allowed');
        }
        $reminder = OnlineInvitationReminder::findOrFail($id);
        if ($reminder->status === 'scheduled') {
            $reminder->status = 'cancelled';
            $reminder->save();
        }

        return redirect()->back()->with('message', 'Reminder cancelled.');
    }
}
