<?php

namespace App\Http\Controllers;

use App\Jobs\SendOnlineInvitationJob;
use App\OnlineInvitation;
use App\OnlineInvitationCategory;
use App\OnlineInvitationEvent;
use App\OnlineInvitationRequestLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Role;

class OnlineInvitationRequestLinkController extends Controller
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

            return $role->hasPermissionTo('online_invitation_send_invitation')
                || $role->hasPermissionTo('online_invitation_event');
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }

    public function index()
    {
        if (! $this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Not allowed');
        }
        $links = OnlineInvitationRequestLink::with(['event', 'category'])
            ->where('is_active', 1)
            ->orderByDesc('id')
            ->paginate(20);

        return view('online_invitation.request_link.index', compact('links'));
    }

    public function create()
    {
        if (! $this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Not allowed');
        }
        $events = OnlineInvitationEvent::with('categories')->where('is_active', 1)->orderByDesc('id')->get();
        $categories = OnlineInvitationCategory::where('is_active', 1)->orderBy('name')->get();

        return view('online_invitation.request_link.create', compact('events', 'categories'));
    }

    public function store(Request $request)
    {
        if (! $this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Not allowed');
        }
        $data = $request->validate([
            'event_id' => 'required|integer|exists:online_invitation_events,id',
            'category_id' => 'required|integer|exists:online_invitation_categories,id',
            'max_uses' => 'nullable|integer|min:1',
        ]);
        $link = OnlineInvitationRequestLink::create([
            'event_id' => $data['event_id'],
            'category_id' => $data['category_id'],
            'token' => Str::random(40),
            'is_active' => 1,
            'max_uses' => $data['max_uses'] ?? null,
            'use_count' => 0,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('online_invitation.request_links.index')
            ->with('message', 'Request link created: '.route('online_invitation.request.show', $link->token));
    }

    public function destroy($id)
    {
        if (! $this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Not allowed');
        }
        $link = OnlineInvitationRequestLink::findOrFail($id);
        $link->is_active = 0;
        $link->save();

        return redirect()->back()->with('message', 'Request link deactivated.');
    }

    public function showPublic($token)
    {
        $link = OnlineInvitationRequestLink::with(['event', 'category'])
            ->where('token', $token)
            ->where('is_active', 1)
            ->firstOrFail();

        return view('online_invitation.request_link.public', compact('link'));
    }

    public function submitPublic(Request $request, $token)
    {
        $link = OnlineInvitationRequestLink::with(['event', 'category'])
            ->where('token', $token)
            ->where('is_active', 1)
            ->firstOrFail();

        if ($link->max_uses && (int) $link->use_count >= (int) $link->max_uses) {
            return back()->with('not_permitted', 'This invitation link has reached its limit.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
        ]);

        $phone = preg_replace('/\s+/', '', trim($data['phone']));
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        $exists = OnlineInvitation::where('is_active', 1)
            ->where('event_id', $link->event_id)
            ->where('category_id', $link->category_id)
            ->where('recipient_phone', $phone)
            ->whereIn('status', ['awaiting_sending', 'sent'])
            ->exists();
        if ($exists) {
            return back()->with('not_permitted', 'An invitation was already created for this phone and event type.');
        }

        $invitation = OnlineInvitation::create([
            'event_id' => $link->event_id,
            'category_id' => $link->category_id,
            'recipient_name' => $data['name'],
            'recipient_phone' => $phone,
            'recipient_email' => $data['email'] ?? null,
            'status' => 'awaiting_sending',
            'rsvp_status' => 'pending',
            'token' => Str::random(48),
            'border_color' => '#c8a75e',
            'font_color' => '#f3e7c1',
            'is_active' => 1,
            'created_by' => $link->created_by,
        ]);

        $link->use_count = (int) $link->use_count + 1;
        $link->save();

        $invId = $invitation->id;
        $runner = function () use ($invId) {
            static $done = false;
            if ($done) {
                return;
            }
            $done = true;
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            @set_time_limit(300);
            ignore_user_abort(true);
            try {
                (new SendOnlineInvitationJob($invId))->handle();
            } catch (\Throwable $e) {
                \Log::warning('Self-request invitation send failed', ['id' => $invId, 'error' => $e->getMessage()]);
            }
        };
        app()->terminating($runner);
        register_shutdown_function($runner);

        return back()->with('message', 'Your invitation is being sent to WhatsApp shortly.');
    }
}
