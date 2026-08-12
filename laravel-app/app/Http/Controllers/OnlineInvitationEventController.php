<?php

namespace App\Http\Controllers;

use App\OnlineInvitationCategory;
use App\OnlineInvitationEvent;
use App\OnlineInvitationTemplate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Role;

class OnlineInvitationEventController extends Controller
{
    private $user;

    public function __construct() {


        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            $role = Role::find($this->user->role_id);
            $permissions = Role::findByName($role->name)->permissions;

            foreach ($permissions as $permission) {
                $all_permission[] = $permission->name;
            }
            View::share ( 'all_permission', $all_permission);

            return $next($request);
        });
    }

    private function ensureAccess()
    {
        try {
            $role = Role::find(Auth::user()->role_id);
            return $role && $role->hasPermissionTo('online_invitation_event');
        } catch (PermissionDoesNotExist $e) {
            return false;
        }
    }

    private function normalizeEventAt($value)
    {
        // HTML datetime-local sends "YYYY-MM-DDTHH:MM" (no seconds).
        if (is_string($value)) {
            $value = trim($value);
            try {
                if (preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}$/', $value)) {
                    return Carbon::createFromFormat('Y-m-d\\TH:i', $value)->format('Y-m-d H:i:s');
                }
                if (preg_match('/^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}$/', $value)) {
                    return Carbon::createFromFormat('Y-m-d\\TH:i:s', $value)->format('Y-m-d H:i:s');
                }
                if (preg_match('/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}$/', $value)) {
                    return Carbon::createFromFormat('Y-m-d H:i', $value)->format('Y-m-d H:i:s');
                }
            } catch (\Throwable $e) {
                // Fall through to parse().
            }
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    public function index(Request $request)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $query = OnlineInvitationEvent::with(['template', 'categories'])
            ->where('is_active', 1)
            ->orderBy('id', 'desc');

        $search = trim((string) $request->get('q', ''));
        if ($search !== '') {
            $query->where(function ($sub) use ($search) {
                $sub->where('name', 'like', '%' . $search . '%')
                    ->orWhere('location', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhereHas('template', function ($tq) use ($search) {
                        $tq->where('name', 'like', '%' . $search . '%');
                    })
                    ->orWhereHas('categories', function ($cq) use ($search) {
                        $cq->where('name', 'like', '%' . $search . '%');
                    });
            });
        }

        $data = $query->paginate(12);

        return view('online_invitation.event.index', compact('data'));
    }

    public function create()
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $templates = OnlineInvitationTemplate::where('is_active', 1)->orderBy('id', 'desc')->get();
        $categories = OnlineInvitationCategory::where('is_active', 1)->orderBy('name')->get();
        return view('online_invitation.event.create', compact('templates', 'categories'));
    }

    public function store(Request $request)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $this->validate($request, [
            'name' => 'required|max:255',
            'description' => 'nullable',
            'location' => 'nullable|max:255',
            'event_at' => 'required|date',
            'template_id' => 'nullable|integer|exists:online_invitation_templates,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:online_invitation_categories,id',
        ]);

        $event = OnlineInvitationEvent::create([
            'name' => $request->name,
            'description' => $request->description,
            'location' => $request->location,
            'event_at' => $this->normalizeEventAt($request->event_at),
            'template_id' => $request->template_id,
            'is_active' => 1,
            'created_by' => $this->user->id,
        ]);

        $event->categories()->sync($request->category_ids ?: []);

        return redirect()->route('online_invitation.events.index')->with('message', 'Event created successfully');
    }

    public function edit($id)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $data = OnlineInvitationEvent::with('categories')->findOrFail($id);
        $templates = OnlineInvitationTemplate::where('is_active', 1)->orderBy('id', 'desc')->get();
        $categories = OnlineInvitationCategory::where('is_active', 1)->orderBy('name')->get();

        return view('online_invitation.event.edit', compact('data', 'templates', 'categories'));
    }

    public function update(Request $request, $id)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $this->validate($request, [
            'name' => 'required|max:255',
            'description' => 'nullable',
            'location' => 'nullable|max:255',
            'event_at' => 'required|date',
            'template_id' => 'nullable|integer|exists:online_invitation_templates,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:online_invitation_categories,id',
        ]);

        $event = OnlineInvitationEvent::findOrFail($id);
        $event->update([
            'name' => $request->name,
            'description' => $request->description,
            'location' => $request->location,
            'event_at' => $this->normalizeEventAt($request->event_at),
            'template_id' => $request->template_id,
        ]);

        $event->categories()->sync($request->category_ids ?: []);

        return redirect()->route('online_invitation.events.index')->with('message', 'Event updated successfully');
    }

    public function destroy($id)
    {
        if (!$this->ensureAccess()) {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

        $event = OnlineInvitationEvent::findOrFail($id);
        $event->update(['is_active' => 0]);

        return redirect()->route('online_invitation.events.index')->with('message', 'Event deleted successfully');
    }
}
