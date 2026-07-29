<?php

namespace App\Http\Controllers;

use App\DigitalInvitation;
use App\InvitationEvent;
use App\InvitationGuest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DigitalInvitationController extends Controller
{
    private function permissions()
    {
        $role = Role::find(Auth::user()->role_id);
        $all = [];
        if ($role) {
            foreach ($role->permissions as $permission) {
                $all[] = $permission->name;
            }
        }

        return $all;
    }

    private function ensureModule()
    {
        $all = $this->permissions();
        if (! in_array('invitations_module', $all, true) && ! in_array('invitations.view', $all, true)) {
            abort(403, 'Digital Invitations module is not enabled for your role.');
        }
    }

    private function dataConfigured()
    {
        return (bool) config('database.connections.beyond_data.database')
            && (bool) config('database.connections.beyond_data.username');
    }

    public function index(Request $request)
    {
        $this->ensureModule();
        $all_permission = $this->permissions();

        if (! $this->dataConfigured()) {
            return view('invitations.misconfigured', compact('all_permission'));
        }

        try {
            $query = DigitalInvitation::with(['guest', 'event'])->orderByDesc('created_at');
            if ($request->filled('q')) {
                $q = '%'.$request->q.'%';
                $query->where(function ($w) use ($q) {
                    $w->where('guest_name', 'like', $q)
                        ->orWhere('guest_phone', 'like', $q)
                        ->orWhere('guest_email', 'like', $q)
                        ->orWhere('qr_code', 'like', $q)
                        ->orWhere('status', 'like', $q);
                });
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }
            if ($request->filled('event_id')) {
                $query->where('event_id', $request->event_id);
            }
            $invitations = $query->paginate(25)->appends($request->query());
            $events = InvitationEvent::orderByDesc('event_date')->limit(200)->get();
        } catch (\Throwable $e) {
            return view('invitations.misconfigured', [
                'all_permission' => $all_permission,
                'error' => $e->getMessage(),
            ]);
        }

        return view('invitations.index', compact('invitations', 'events', 'all_permission'));
    }

    public function create()
    {
        $this->ensureModule();
        $all_permission = $this->permissions();
        if (! in_array('invitations.create', $all_permission, true)) {
            abort(403);
        }

        try {
            $events = InvitationEvent::orderByDesc('event_date')->limit(200)->get();
        } catch (\Throwable $e) {
            return view('invitations.misconfigured', [
                'all_permission' => $all_permission,
                'error' => $e->getMessage(),
            ]);
        }

        return view('invitations.create', compact('events', 'all_permission'));
    }

    public function store(Request $request)
    {
        $this->ensureModule();
        $all_permission = $this->permissions();
        if (! in_array('invitations.create', $all_permission, true)) {
            abort(403);
        }

        $data = $request->validate([
            'event_id' => 'required|string',
            'guest_name' => 'required|string|max:255',
            'guest_phone' => 'required|string|max:50',
            'guest_email' => 'nullable|email|max:255',
            'invitation_type' => 'nullable|string|max:50',
        ]);

        $guestId = (string) Str::uuid();
        InvitationGuest::create([
            'id' => $guestId,
            'name' => $data['guest_name'],
            'phone' => $data['guest_phone'],
            'email' => $data['guest_email'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $code = 'EVENT-'.date('Y').'-'.random_int(10000, 99999);
        $invId = (string) Str::uuid();
        DigitalInvitation::create([
            'id' => $invId,
            'event_id' => $data['event_id'],
            'guest_id' => $guestId,
            'guest_name' => $data['guest_name'],
            'guest_phone' => $data['guest_phone'],
            'guest_email' => $data['guest_email'] ?? null,
            'qr_code' => $code,
            'status' => 'Pending',
            'checked_in' => 0,
            'invitation_type' => $data['invitation_type'] ?: 'Standard',
            'generated_at' => now()->toDateTimeString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('invitations.show', $invId)
            ->with('message', 'Invitation created: '.$code);
    }

    public function show($id)
    {
        $this->ensureModule();
        $all_permission = $this->permissions();
        $invitation = DigitalInvitation::with(['guest', 'event'])->findOrFail($id);

        return view('invitations.show', compact('invitation', 'all_permission'));
    }

    public function checkInForm()
    {
        $this->ensureModule();
        $all_permission = $this->permissions();
        if (! in_array('invitations.check_in', $all_permission, true) && ! in_array('invitations.edit', $all_permission, true)) {
            abort(403);
        }

        return view('invitations.check_in', compact('all_permission'));
    }

    public function checkIn(Request $request)
    {
        $this->ensureModule();
        $all_permission = $this->permissions();
        if (! in_array('invitations.check_in', $all_permission, true) && ! in_array('invitations.edit', $all_permission, true)) {
            abort(403);
        }

        $data = $request->validate([
            'code' => 'required|string|max:100',
        ]);

        $code = trim($data['code']);
        $invitation = DigitalInvitation::with(['guest', 'event'])
            ->where(function ($q) use ($code) {
                $q->where('qr_code', $code)->orWhere('id', $code);
            })
            ->first();

        if (! $invitation) {
            return back()->with('not_permitted', 'Invitation not found for that code.');
        }

        if ($invitation->checked_in) {
            return redirect()->route('invitations.show', $invitation->id)
                ->with('not_permitted', 'Already checked in'
                    .($invitation->checked_in_at ? ' at '.$invitation->checked_in_at : '').'.');
        }

        $invitation->checked_in = 1;
        $invitation->checked_in_at = now()->toDateTimeString();
        $invitation->status = 'Checked In';
        $invitation->save();

        return redirect()->route('invitations.show', $invitation->id)
            ->with('message', 'Checked in: '.$invitation->displayName());
    }

    public function destroy($id)
    {
        $this->ensureModule();
        $all_permission = $this->permissions();
        if (! in_array('invitations.delete', $all_permission, true)) {
            abort(403);
        }

        $invitation = DigitalInvitation::findOrFail($id);
        $invitation->delete();

        return redirect()->route('invitations.index')->with('message', 'Invitation deleted.');
    }
}
