<?php

namespace App\Http\Controllers;

use App\Nbc\NbcAnnouncement;
use App\Nbc\NbcBylaw;
use App\Nbc\NbcEvent;
use App\Nbc\NbcMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class NbcPublicController extends Controller
{
    public function home()
    {
        $events = $this->upcoming();
        $announcements = NbcAnnouncement::whereNotNull('published_at')->orderByDesc('published_at')->limit(4)->get();

        return view('nbc.home', compact('events', 'announcements'));
    }

    public function program()
    {
        $items = NbcEvent::where('published', true)->where('kind', 'program')->orderBy('sort')->orderBy('starts_at')->get();

        return view('nbc.program', compact('items'));
    }

    public function events()
    {
        $events = $this->upcoming();

        return view('nbc.events', compact('events'));
    }

    public function announcements()
    {
        $announcements = NbcAnnouncement::whereNotNull('published_at')->orderByDesc('published_at')->get();

        return view('nbc.announcements', compact('announcements'));
    }

    public function announcement($id)
    {
        $announcement = NbcAnnouncement::whereNotNull('published_at')->findOrFail($id);

        return view('nbc.announcement', compact('announcement'));
    }

    public function join()
    {
        return view('nbc.join', ['bylaws' => NbcBylaw::current()]);
    }

    public function storeJoin(Request $request)
    {
        $bylaws = NbcBylaw::current();
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191',
            'phone' => 'nullable|string|max:40',
            'part' => 'nullable|string|max:120',
            'about' => 'nullable|string|max:2000',
            'password' => 'required|string|min:8|max:100',
            'agree' => 'accepted',
            'signature' => 'required|string|min:80',
        ]);
        if (NbcMember::where('email', $data['email'])->exists()) {
            return back()->withInput()->with('nbc_error', 'That email is already on the praise team list.');
        }
        $signature = $data['signature'];
        if (strpos($signature, 'data:image/png;base64,') !== 0 || strlen($signature) > 500000) {
            return back()->withInput()->with('nbc_error', 'Sign in the box before you continue.');
        }
        NbcMember::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => isset($data['phone']) ? $data['phone'] : null,
            'part' => isset($data['part']) ? $data['part'] : null,
            'about' => isset($data['about']) ? $data['about'] : null,
            'password' => Hash::make($data['password']),
            'role' => 'member',
            'status' => 'pending',
            'bylaws_version' => $bylaws->version,
            'agreed_at' => now(),
            'signature' => $signature,
        ]);

        return redirect()->route('nbc.join')->with('nbc_message', 'Your application is in. A leader will approve it before you can sign in.');
    }

    public function showLogin()
    {
        return view('nbc.login', ['needsOwner' => NbcMember::count() === 0]);
    }

    public function login(Request $request)
    {
        if (NbcMember::count() === 0) {
            $data = $request->validate([
                'name' => 'required|string|max:191',
                'email' => 'required|email|max:191',
                'password' => 'required|string|min:8|max:100',
            ]);
            $member = NbcMember::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'owner',
                'status' => 'active',
                'agreed_at' => now(),
                'bylaws_version' => NbcBylaw::current()->version,
            ]);
            session(['nbc_member_id' => $member->id]);

            return redirect()->route('nbc.portal');
        }
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        $member = NbcMember::where('email', $data['email'])->first();
        if (! $member || ! Hash::check($data['password'], $member->password)) {
            return back()->withInput()->with('nbc_error', 'Those details do not match a praise team account.');
        }
        if ($member->status !== 'active') {
            return back()->withInput()->with('nbc_error', 'Your application is still waiting for approval.');
        }
        session(['nbc_member_id' => $member->id]);

        return redirect()->route('nbc.portal');
    }

    public function logout()
    {
        session()->forget('nbc_member_id');

        return redirect()->route('nbc.home');
    }

    protected function upcoming()
    {
        return NbcEvent::where('published', true)
            ->where('kind', '!=', 'program')
            ->where(function ($query) {
                $query->whereNull('starts_at')->orWhere('starts_at', '>=', now()->copy()->startOfDay());
            })
            ->orderBy('starts_at')
            ->orderBy('sort')
            ->get();
    }
}
