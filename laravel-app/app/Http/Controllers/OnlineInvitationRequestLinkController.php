<?php

namespace App\Http\Controllers;

use App\Customer;
use App\Jobs\SendOnlineInvitationJob;
use App\OnlineInvitation;
use App\OnlineInvitationCategory;
use App\OnlineInvitationEvent;
use App\OnlineInvitationRequestLink;
use App\Services\BeyondWasenderService;
use App\Support\WhatsAppPhone;
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
            'phone' => 'required|string|max:50',
        ]);

        try {
            $phone = WhatsAppPhone::normalize($data['phone']);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['phone' => $e->getMessage()]);
        }

        $exists = OnlineInvitation::where('is_active', 1)
            ->where('event_id', $link->event_id)
            ->where('category_id', $link->category_id)
            ->where(function ($q) use ($phone) {
                $q->where('recipient_phone', $phone)
                    ->orWhere('recipient_phone', '+'.$phone)
                    ->orWhere('recipient_phone', 'like', '%'.substr($phone, -9));
            })
            ->whereIn('status', ['awaiting_sending', 'sent'])
            ->exists();
        if ($exists) {
            return back()->with('not_permitted', 'An invitation was already created for this phone and event type.');
        }

        // Prefer Wasender contact name (same as voting), then CRM customer, then "Guest".
        $name = $this->resolveGuestNameFromPhone($phone);
        $email = null;
        $customer = Customer::where('is_active', 1)
            ->where(function ($q) use ($phone) {
                $q->where('phone_number', $phone)
                    ->orWhere('phone_number', '+'.$phone)
                    ->orWhere('phone_number', 'like', '%'.substr($phone, -9));
            })
            ->orderByDesc('id')
            ->first();
        if ($customer) {
            $email = $customer->email ?: null;
            if ($name === 'Guest' && trim((string) $customer->name) !== '') {
                $name = $customer->name;
            }
        }

        $invitation = OnlineInvitation::create([
            'event_id' => $link->event_id,
            'category_id' => $link->category_id,
            'customer_id' => $customer ? $customer->id : null,
            'recipient_name' => $name,
            'recipient_phone' => $phone,
            'recipient_email' => $email,
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

        return back()->with('message', 'Your invitation is being prepared for '.$name.' and will arrive on WhatsApp shortly.');
    }

    /**
     * Resolve guest name via Wasender contacts API (voting-style), then fall back.
     */
    protected function resolveGuestNameFromPhone(string $phone): string
    {
        try {
            $fromWasender = app(BeyondWasenderService::class)->getContactName($phone);
            if ($fromWasender) {
                return $fromWasender;
            }
        } catch (\Throwable $e) {
            \Log::info('Guest invite contact resolve failed', ['error' => $e->getMessage()]);
        }

        return 'Guest';
    }
}
