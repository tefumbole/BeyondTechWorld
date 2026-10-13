<?php

namespace App\Http\Controllers;

use App\Nbc\NbcAnnouncement;
use App\Nbc\NbcBylaw;
use App\Nbc\NbcEvent;
use App\Nbc\NbcJoinCatalog;
use App\Nbc\NbcMember;
use App\Services\Messaging\NotificationRouter;
use App\Services\MobileMoneyHolderService;
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
        $otp = session('nbc_join_otp');
        $verifiedPhone = is_array($otp) && ! empty($otp['verified']) ? $otp['phone'] : '';

        return view('nbc.join', [
            'bylaws' => NbcBylaw::current(),
            'verifiedPhone' => $verifiedPhone,
            'catalog' => [
                'titles' => NbcJoinCatalog::titles(),
                'education' => NbcJoinCatalog::education(),
                'wings' => NbcJoinCatalog::wings(),
                'instruments' => NbcJoinCatalog::instruments(),
                'vocals' => NbcJoinCatalog::vocals(),
                'genres' => NbcJoinCatalog::genres(),
                'levels' => NbcJoinCatalog::levels(),
            ],
        ]);
    }

    public function sendJoinOtp(Request $request)
    {
        $data = $request->validate([
            'dial' => 'required|string|max:6',
            'phone' => 'required|string|max:30',
            'channel' => 'required|in:whatsapp,sms',
        ]);
        $digits = $this->joinDigits($data['dial'].$data['phone']);
        if (strlen($digits) < 8) {
            return response()->json(['ok' => false, 'error' => 'Enter a phone number.'], 422);
        }
        if ($this->joinPhoneTaken($digits)) {
            return response()->json(['ok' => false, 'error' => 'That number is already on the praise team list.'], 422);
        }
        $code = \App\Support\MemorableOtp::code();
        $shown = \App\Support\MemorableOtp::display($code);
        $sent = $data['channel'] === 'sms'
            ? app(NotificationRouter::class)->sendSms($digits, 'Your Nkwen Baptist Church Praise Team code is '.$shown.'. The first pair is repeated. It expires in 10 minutes.')
            : app(NotificationRouter::class)->sendWhatsAppOtp($digits, $code, 'signup', 10);
        if (empty($sent['success'])) {
            $error = isset($sent['error']) ? $sent['error'] : 'The code could not be sent.';

            return response()->json(['ok' => false, 'error' => $error], 422);
        }
        session([
            'nbc_join_otp' => [
                'phone' => $digits,
                'hash' => Hash::make($code),
                'expires' => now()->addMinutes(10)->getTimestamp(),
                'attempts' => 0,
                'verified' => false,
            ],
        ]);

        return response()->json(['ok' => true]);
    }

    public function verifyJoinOtp(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:12',
        ]);
        $row = session('nbc_join_otp');
        if (! is_array($row) || empty($row['hash']) || empty($row['phone'])) {
            return response()->json(['ok' => false, 'error' => 'Request a new code.'], 422);
        }
        if ((int) $row['expires'] < now()->getTimestamp()) {
            session()->forget('nbc_join_otp');

            return response()->json(['ok' => false, 'error' => 'That code has expired. Request a new one.'], 422);
        }
        $attempts = isset($row['attempts']) ? (int) $row['attempts'] : 0;
        if ($attempts >= 5) {
            session()->forget('nbc_join_otp');

            return response()->json(['ok' => false, 'error' => 'That code is no longer valid. Request a new one.'], 422);
        }
        if (! Hash::check(preg_replace('/\D+/', '', (string) $data['code']), $row['hash'])) {
            $row['attempts'] = $attempts + 1;
            session(['nbc_join_otp' => $row]);

            return response()->json(['ok' => false, 'error' => 'That code does not match.'], 422);
        }
        $row['verified'] = true;
        session(['nbc_join_otp' => $row]);
        $holder = app(MobileMoneyHolderService::class)->lookup($row['phone']);
        $name = isset($holder['name']) ? trim((string) $holder['name']) : '';

        return response()->json(['ok' => true, 'phone' => $row['phone'], 'name' => $name]);
    }

    public function storeJoin(Request $request)
    {
        $bylaws = NbcBylaw::current();
        $levels = implode(',', array_keys(NbcJoinCatalog::levels()));
        $data = $request->validate([
            'phone' => 'required|string|max:40',
            'sex' => 'required|in:male,female',
            'marital_status' => 'required|in:single,married,widowed,divorced',
            'age' => 'required|integer|min:5|max:120',
            'quarter' => 'required|string|max:191',
            'immediate_contact' => 'required|string|max:40',
            'education' => 'required|in:'.implode(',', array_keys(NbcJoinCatalog::education())),
            'area_of_work' => 'required|string|max:191',
            'name' => 'required|string|max:191',
            'church_membership' => 'required|in:member,admit',
            'title' => 'required|in:'.implode(',', array_keys(NbcJoinCatalog::titles())),
            'title_other' => 'nullable|string|max:80',
            'baptised' => 'required|in:yes,no',
            'inquirer' => 'nullable',
            'salvation' => 'required|in:born_again,not_born_again,other,dont_know',
            'salvation_year' => 'nullable|integer|min:1900|max:2100',
            'salvation_date' => 'nullable|date',
            'salvation_testimony' => 'nullable|string|max:5000',
            'wing' => 'required|in:'.implode(',', array_keys(NbcJoinCatalog::wings())),
            'wing_other' => 'nullable|string|max:120',
            'reason' => 'required|string|max:2000',
            'instruments' => 'nullable|array',
            'instruments.*' => 'in:'.implode(',', array_keys(NbcJoinCatalog::instruments())),
            'instrument_level' => 'nullable|array',
            'instrument_other' => 'nullable|string|max:80',
            'vocals' => 'nullable|array',
            'vocals.*' => 'in:'.implode(',', array_keys(NbcJoinCatalog::vocals())),
            'vocal_level' => 'nullable|array',
            'genres' => 'nullable|array',
            'genres.*' => 'in:'.implode(',', array_keys(NbcJoinCatalog::genres())),
            'genre_level' => 'nullable|array',
            'email' => 'required|email|max:191',
            'password' => 'required|string|min:8|max:100',
            'agree' => 'accepted',
            'signature' => 'required|string|min:80',
        ]);
        $phone = $this->joinDigits($data['phone']);
        $verified = session('nbc_join_otp');
        if (! is_array($verified) || empty($verified['verified']) || (isset($verified['phone']) ? $verified['phone'] : '') !== $phone) {
            return back()->withInput()->with('nbc_error', 'Verify your phone number before you submit.');
        }
        if ($data['title'] === 'other' && trim((string) $request->input('title_other')) === '') {
            return back()->withInput()->with('nbc_error', 'Specify your title.');
        }
        if ($data['wing'] === 'other' && trim((string) $request->input('wing_other')) === '') {
            return back()->withInput()->with('nbc_error', 'Specify the church wing.');
        }
        $profile = $this->joinProfile($data, $request, $levels);
        if ($profile === null) {
            return back()->withInput()->with('nbc_error', 'Choose at least one gift, and a level for each one you select.');
        }
        if (NbcMember::where('email', $data['email'])->exists()) {
            return back()->withInput()->with('nbc_error', 'That email is already on the praise team list.');
        }
        if ($this->joinPhoneTaken($phone)) {
            return back()->withInput()->with('nbc_error', 'That number is already on the praise team list.');
        }
        $signature = $data['signature'];
        if (strpos($signature, 'data:image/png;base64,') !== 0 || strlen($signature) > 500000) {
            return back()->withInput()->with('nbc_error', 'Sign in the box before you continue.');
        }
        NbcMember::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $phone,
            'part' => NbcJoinCatalog::partSummary($profile),
            'about' => $data['reason'],
            'profile' => json_encode($profile),
            'password' => Hash::make($data['password']),
            'role' => 'member',
            'status' => 'pending',
            'bylaws_version' => $bylaws->version,
            'agreed_at' => now(),
            'signature' => $signature,
        ]);
        session()->forget('nbc_join_otp');

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

    protected function joinDigits($phone)
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($digits) === 9 && isset($digits[0]) && $digits[0] === '6') {
            $digits = '237'.$digits;
        }

        return $digits;
    }

    protected function joinPhoneTaken($digits)
    {
        $members = NbcMember::whereNotNull('phone')->get(['phone']);
        foreach ($members as $member) {
            if ($this->joinDigits($member->phone) === $digits) {
                return true;
            }
        }

        return false;
    }

    protected function joinProfile(array $data, Request $request, $levels)
    {
        $instruments = $this->joinRated(
            (array) $request->input('instruments', []),
            (array) $request->input('instrument_level', []),
            NbcJoinCatalog::instruments(),
            $levels
        );
        $vocals = $this->joinRated(
            (array) $request->input('vocals', []),
            (array) $request->input('vocal_level', []),
            NbcJoinCatalog::vocals(),
            $levels
        );
        $genres = $this->joinRated(
            (array) $request->input('genres', []),
            (array) $request->input('genre_level', []),
            NbcJoinCatalog::genres(),
            $levels
        );
        if ($instruments === null || $vocals === null || $genres === null) {
            return null;
        }
        if ($instruments === [] && $vocals === [] && $genres === []) {
            return null;
        }
        foreach ($instruments as $i => $row) {
            if ($row['key'] === 'other') {
                $other = trim((string) $request->input('instrument_other'));
                if ($other === '') {
                    return null;
                }
                $instruments[$i]['label'] = $other;
            }
        }
        $titles = NbcJoinCatalog::titles();
        $wings = NbcJoinCatalog::wings();

        return [
            'sex' => $data['sex'],
            'marital_status' => $data['marital_status'],
            'age' => (int) $data['age'],
            'quarter' => $data['quarter'],
            'immediate_contact' => $data['immediate_contact'],
            'education' => $data['education'],
            'area_of_work' => $data['area_of_work'],
            'calling_name' => $data['name'],
            'church_membership' => $data['church_membership'],
            'title' => $data['title'],
            'title_label' => $data['title'] === 'other' ? trim((string) $request->input('title_other')) : $titles[$data['title']],
            'baptised_immersion' => $data['baptised'] === 'yes',
            'inquirer_classes' => $data['baptised'] === 'no' && $request->input('inquirer') ? true : false,
            'salvation' => $data['salvation'],
            'salvation_year' => $data['salvation'] === 'born_again' && $request->input('salvation_year') !== null && $request->input('salvation_year') !== ''
                ? (int) $data['salvation_year'] : null,
            'salvation_date' => $data['salvation'] === 'born_again' ? ($data['salvation_date'] ?: null) : null,
            'salvation_testimony' => $data['salvation'] === 'born_again' ? trim((string) $request->input('salvation_testimony')) : '',
            'wing' => $data['wing'],
            'wing_label' => $data['wing'] === 'other' ? trim((string) $request->input('wing_other')) : $wings[$data['wing']],
            'reason' => $data['reason'],
            'instruments' => $instruments,
            'vocals' => $vocals,
            'genres' => $genres,
        ];
    }

    protected function joinRated(array $keys, array $levelsInput, array $labels, $allowed)
    {
        $allowed = array_flip(explode(',', $allowed));
        $rows = [];
        foreach ($keys as $key) {
            $key = (string) $key;
            if (! isset($labels[$key])) {
                return null;
            }
            $level = isset($levelsInput[$key]) ? (string) $levelsInput[$key] : '';
            if (! isset($allowed[$level])) {
                return null;
            }
            $rows[] = [
                'key' => $key,
                'label' => $labels[$key],
                'level' => $level,
            ];
        }

        return $rows;
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
