<?php

namespace App\Http\Controllers;

use App\Gbhs\GbhsApplication;
use App\Gbhs\GbhsBatch;
use App\Gbhs\GbhsBylawVersion;
use App\Gbhs\GbhsBylaws;
use App\Gbhs\GbhsIdReader;
use App\Gbhs\GbhsCatalog;
use App\Gbhs\GbhsContribution;
use App\Gbhs\GbhsDonate;
use App\Gbhs\GbhsEvent;
use App\Gbhs\GbhsLeader;
use App\Gbhs\GbhsMember;
use App\Gbhs\GbhsMembership;
use App\Gbhs\GbhsMessage;
use App\Gbhs\GbhsPayment;
use App\Gbhs\GbhsProject;
use App\Gbhs\GbhsRules;
use App\Gbhs\GbhsSection;
use App\Gbhs\GbhsSetting;
use App\Gbhs\GbhsTestimonial;
use App\Gbhs\GbhsTestimonialWriter;
use App\Gbhs\GbhsTenant;
use App\Services\BeyondAuthService;
use App\Support\CountryCities;
use App\Support\CountryDialCodes;
use App\Support\CountryRegions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GbhsPublicController extends Controller
{
    public function home()
    {
        return $this->page('home');
    }

    public function page($slug)
    {
        if (! GbhsTenant::ready()) {
            return response('GBHS Kumbo is not installed yet.', 503);
        }
        GbhsCatalog::ensure();
        $setting = GbhsSetting::query()->first();
        $section = null;
        $leaders = collect();
        $testimonials = collect();
        $batches = collect();
        $news = collect();
        $documents = collect();
        $events = collect();
        $projects = collect();
        $raised = [];
        $totals = ['verified' => 0];
        $donors = collect();
        if (! in_array($slug, ['home', 'news', 'documents', 'leaders', 'contact'], true)) {
            $section = GbhsSection::where('slug', $slug)->where('published', 1)->first();
        }
        if ($slug === 'leaders' && Schema::hasTable('gbhs_testimonials')) {
            $testimonials = GbhsTestimonial::orderBy('id')->get();
        } elseif ($slug === 'batches') {
            $batches = GbhsBatch::orderBy('year')->get();
        } elseif ($slug === 'news') {
            $news = GbhsSection::where('kind', 'news')->where('published', 1)->orderBy('id', 'desc')->get();
        } elseif ($slug === 'documents' && Schema::hasTable('gbhs_documents')) {
            $documents = \App\Gbhs\GbhsDocument::where('published', 1)->orderBy('sort')->get();
        } elseif ($slug === 'jubilee') {
            $events = GbhsEvent::where('published', 1)->orderBy('starts_at')->get();
        } elseif ($slug === 'projects') {
            $projects = GbhsProject::where('published', 1)->orderBy('id', 'desc')->get();
            if ($projects->count()) {
                $sums = GbhsContribution::whereIn('project_id', $projects->pluck('id'))
                    ->where('verified', 1)
                    ->whereNotNull('payment_id')
                    ->whereNotIn('classification', ['reversal', 'in_kind'])
                    ->selectRaw('project_id, SUM(amount) as total')
                    ->groupBy('project_id')
                    ->pluck('total', 'project_id');
                foreach ($projects as $project) {
                    $raised[$project->id] = (int) (isset($sums[$project->id]) ? $sums[$project->id] : 0);
                }
            }
        } elseif ($slug === 'contributions') {
            $totals = $this->publicTotals($setting);
            $donors = GbhsContribution::where('verified', 1)
                ->where('anonymous', 0)
                ->whereNotIn('classification', ['reversal', 'in_kind'])
                ->orderBy('id', 'desc')
                ->limit(30)
                ->get();
        }

        return view('gbhs.public.page', compact(
            'slug', 'section', 'setting', 'leaders', 'testimonials', 'batches', 'news', 'documents',
            'events', 'projects', 'raised', 'totals', 'donors'
        ));
    }

    public function membership()
    {
        if (! GbhsTenant::ready()) {
            return response('GBHS Kumbo is not installed yet.', 503);
        }
        GbhsCatalog::ensure();
        $setting = GbhsSetting::query()->first();
        $section = GbhsSection::where('slug', 'membership')->first();
        $batches = GbhsBatch::where('year', '>', 0)->orderBy('year')->get();
        $bylaw = GbhsBylawVersion::where('status', 'published')->orderBy('id', 'desc')->first();
        $application = null;
        if (session('gbhs_application_id')) {
            $application = GbhsApplication::find(session('gbhs_application_id'));
        }
        if (in_array(request('home'), ['cameroon', 'diaspora'], true)) {
            session(['gbhs_join' => 1, 'gbhs_home' => request('home')]);

            return redirect()->route('gbhs.membership', ['step' => 'details']);
        }
        $step = $this->membershipStep($application);
        $home = session('gbhs_home') === 'diaspora' ? 'diaspora' : 'cameroon';
        $countries = [];
        foreach (CountryDialCodes::picker() as $row) {
            if ($home === 'diaspora' && $row['name'] === 'Cameroon') {
                continue;
            }
            $countries[] = $row['name'];
        }
        $regions = CountryRegions::map();
        $cities = CountryCities::map();
        $divisionLabels = CountryRegions::labels();
        $articles = GbhsBylaws::articles();
        if (! $application) {
            $application = new GbhsApplication();
        }

        return view('gbhs.public.membership', compact(
            'setting', 'section', 'batches', 'bylaw', 'application', 'step', 'countries', 'regions', 'cities', 'articles', 'home', 'divisionLabels'
        ));
    }

    protected function membershipStep($application)
    {
        if (request()->filled('choose')) {
            return 'intro';
        }
        if ($application && ($application->signature_path || $application->status === 'submitted' || $application->status === 'approved')) {
            return 'done';
        }
        $ready = session('gbhs_phone_verified') && $application && $application->phone_verified;
        $asked = request('step');
        if ($asked === 'details' && ($ready || session('gbhs_join'))) {
            return 'details';
        }
        if (! $ready) {
            return session('gbhs_join') ? 'details' : 'intro';
        }
        $detailsDone = $application->full_name && $application->batch_id && $application->employment_status && $application->country;
        if (! $detailsDone) {
            return 'details';
        }
        if ($asked === 'id' || ! $application->id_step) {
            return 'id';
        }
        if ($asked === 'portrait' || ! $application->photo_step) {
            return 'portrait';
        }

        return 'sign';
    }

    public function startMembership(Request $request)
    {
        $this->boot();
        $request->validate([
            'phone' => 'required|string|min:8|max:24',
            'country_code' => 'nullable|string|max:8',
            'full_name' => 'nullable|string|max:160',
        ]);
        $digits = preg_replace('/\D/', '', \App\Support\CountryDialCodes::combine(
            $request->input('country_code', '+237'),
            $request->phone
        ));
        $hit = app(\App\Services\MobileMoneyHolderService::class)->lookup($digits);
        $name = is_array($hit) && ! empty($hit['name']) ? $hit['name'] : '';
        $typed = trim((string) $request->input('full_name'));
        $application = GbhsMembership::openDraft($digits);
        if (! $application) {
            return back()->with('not_permitted', 'The phone number could not be saved.')->withInput();
        }
        if ($typed !== '') {
            $application->full_name = $typed;
        } elseif ($name !== '') {
            $application->full_name = $name;
        }
        $application->phone_verified = 1;
        $application->save();
        session([
            'gbhs_phone' => $digits,
            'gbhs_phone_verified' => true,
            'gbhs_application_id' => $application->id,
            'gbhs_name_found' => $name !== '',
        ]);

        return redirect()->route('gbhs.membership');
    }

    public function storeTestimonial(Request $request)
    {
        $this->boot();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'country_code' => 'required|string|max:10',
            'phone' => 'required|string|max:40',
            'body' => 'required|string|min:20|max:20000',
            'signature' => 'required|string|max:2500000',
            'selfie' => 'nullable|file|max:5120',
        ], [
            'body.min' => 'Please write a little more for the testimony.',
            'signature.required' => 'Please sign the testimony before submitting.',
        ]);
        try {
            $phone = \App\Support\WhatsAppPhone::combine($data['country_code'], $data['phone']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => 'Enter a valid phone number.'], 422);
        }
        try {
            app(GbhsTestimonialWriter::class)->create([
                'name' => trim($data['name']),
                'phone' => $phone,
                'body' => $data['body'],
                'signature' => $data['signature'],
                'selfie' => $request->file('selfie'),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Could not save this testimony. Try again.'], 500);
        }

        return response()->json(['ok' => true]);
    }

    public function lookupPhone(Request $request)
    {
        $this->boot();
        $raw = preg_replace('/\D/', '', (string) $request->query('phone'));
        if (strlen($raw) < 8) {
            return response()->json(['ok' => true, 'found' => false, 'name' => '']);
        }
        $digits = preg_replace('/\D/', '', \App\Support\CountryDialCodes::combine(
            $request->query('country_code', '+237'),
            $raw
        ));
        $hit = app(\App\Services\MobileMoneyHolderService::class)->lookup($digits);
        $name = is_array($hit) && ! empty($hit['name']) ? $hit['name'] : '';

        return response()->json(['ok' => true, 'found' => $name !== '', 'name' => $name]);
    }

    public function sendMembershipCode(Request $request)
    {
        $this->boot();
        $request->validate(['phone' => 'required|string|min:8|max:20']);
        $otp = app(BeyondAuthService::class)->createOtp($request->phone, 'gbhs');
        session(['gbhs_phone' => $otp['phone'], 'gbhs_phone_verified' => false]);
        try {
            app(\App\Services\BeyondWasenderService::class)->sendOtp($otp['phone'], $otp['code'], 'GBHS Kumbo membership');
        } catch (\Throwable $e) {
            return back()->with('not_permitted', 'The verification code could not be sent.');
        }

        return back()->with('message', 'A code was sent on WhatsApp.');
    }

    public function verifyOtp(Request $request)
    {
        $this->boot();
        $request->validate(['otp' => 'required|string']);
        $phone = session('gbhs_phone');
        if (! $phone) {
            return back()->with('not_permitted', 'Enter your phone number first.');
        }
        $result = app(BeyondAuthService::class)->verifyOtp($phone, $request->otp, 'gbhs');
        if (empty($result['success'])) {
            return back()->with('not_permitted', isset($result['error']) ? $result['error'] : 'That code is not valid.');
        }
        if (! GbhsRules::mayStorePersonal(true)) {
            return back();
        }
        $application = GbhsMembership::openDraft($phone);
        session(['gbhs_phone_verified' => true, 'gbhs_application_id' => $application->id]);

        return redirect()->route('gbhs.membership');
    }

    public function saveProfile(Request $request)
    {
        $existing = session('gbhs_phone_verified') ? GbhsApplication::find(session('gbhs_application_id')) : null;
        if ((! $existing || ! $existing->phone_verified) && ! $request->filled('phone')) {
            return back()->with('not_permitted', 'Enter your phone number before the rest of the form.');
        }
        $this->boot();
        $application = $this->applicationFromPhone($request);
        if (! $application) {
            return back()->with('not_permitted', 'Enter your phone number before the rest of the form.')->withInput();
        }
        $request->validate([
            'full_name' => 'required|string|max:160',
            'batch_id' => 'required|integer',
            'email' => 'nullable|email|max:160',
            'marital_status' => 'required|in:Married,Single,Divorced,Widower,Widow,Other',
            'employment_status' => 'required|in:Employed,Unemployed,Freelancer',
            'employer' => 'nullable|string|max:160',
            'country' => 'required|string|max:80',
            'region' => 'required|string|max:80',
            'city' => 'nullable|string|max:80',
            'city_other' => 'nullable|string|max:80',
        ]);
        $city = trim((string) $request->input('city'));
        if ($city === '' || strcasecmp($city, 'Other') === 0) {
            $city = trim((string) $request->input('city_other'));
        }
        if ($city === '') {
            return back()->with('not_permitted', 'Choose your city.')->withInput();
        }
        $data = $request->only([
            'full_name', 'date_of_birth', 'gender', 'marital_status', 'years_attended',
            'profession', 'employment_status', 'employer', 'email', 'country', 'region', 'batch_id',
        ]);
        $data['city'] = $city;
        if (empty($data['date_of_birth'])) {
            $data['date_of_birth'] = null;
        }
        if (empty($data['employer'])) {
            $data['employer'] = null;
        }
        if (empty($data['email'])) {
            $data['email'] = null;
        }
        $application->fill($data);
        $application->save();

        return redirect()->route('gbhs.membership');
    }

    public function saveIdentification(Request $request)
    {
        $this->boot();
        $application = $this->verifiedApplication();
        if (! $application) {
            return back()->with('not_permitted', 'Enter your phone number before identification.');
        }
        if ($request->input('action') === 'skip') {
            $application->id_step = 'skipped';
            $application->save();

            return redirect()->route('gbhs.membership');
        }
        $request->validate([
            'id_type' => 'nullable|in:national_id,passport',
            'id_holder_name' => 'nullable|string|max:160',
            'id_number' => 'nullable|string|max:40',
            'id_issued_on' => 'nullable|date',
            'document' => 'nullable|file|mimes:jpg,jpeg,png,webp|max:12288',
        ]);
        $extracted = ['name' => '', 'number' => '', 'issued_on' => '', 'read' => false];
        if ($request->hasFile('document')) {
            $path = $request->file('document')->getRealPath();
            try {
                $extracted = (new GbhsIdReader())->read($path);
            } finally {
                if (is_string($path) && is_file($path)) {
                    @unlink($path);
                }
            }
        }
        $application->id_type = $request->input('id_type') ?: ($application->country === 'Cameroon' ? 'national_id' : 'passport');
        $application->id_holder_name = $request->filled('id_holder_name') ? $request->id_holder_name : ($extracted['name'] ?: $application->id_holder_name);
        $application->id_number = $request->filled('id_number') ? $request->id_number : ($extracted['number'] ?: $application->id_number);
        $application->id_issued_on = $request->filled('id_issued_on') ? $request->id_issued_on : ($extracted['issued_on'] ?: $application->id_issued_on);
        if ($request->input('action') === 'read') {
            $application->save();
            $message = $extracted['read']
                ? 'We read the document and deleted the image. Check the name, number, and date of issue, then continue.'
                : 'The image was deleted and could not be read. Type the name, number, and date of issue, or try another photo.';

            return redirect()->route('gbhs.membership')->with($extracted['read'] ? 'message' : 'not_permitted', $message);
        }
        $application->id_step = 'saved';
        $application->save();

        return redirect()->route('gbhs.membership');
    }

    public function savePortrait(Request $request)
    {
        $this->boot();
        $application = $this->verifiedApplication();
        if (! $application || ! $application->id_step) {
            return back()->with('not_permitted', 'Finish identification before the portrait.');
        }
        if ($request->input('action') !== 'skip') {
            $request->validate(['photo' => 'nullable|image|max:12288']);
        }
        if ($request->hasFile('photo')) {
            $this->storePortraitFile($application, $request->file('photo'));

            return redirect()->route('gbhs.membership');
        } else {
            $application->photo_step = $application->photo ? 'saved' : 'skipped';
        }
        $application->save();

        return redirect()->route('gbhs.membership');
    }

    public function captureHandoff(Request $request)
    {
        $this->boot();
        $application = $this->verifiedApplication();
        if (! $application) {
            return response()->json(['ok' => false], 403);
        }
        $kind = (string) $request->input('kind');
        if (! in_array($kind, ['id', 'portrait'], true)) {
            return response()->json(['ok' => false], 422);
        }
        $token = Str::random(40);
        Cache::put($this->captureKey($token), [
            'application_id' => $application->id,
            'kind' => $kind,
            'status' => 'waiting',
        ], now()->addMinutes(20));
        $url = route('gbhs.capture', ['token' => $token]);

        return response()->json([
            'ok' => true,
            'token' => $token,
            'url' => $url,
            'qr' => \App\Support\OnlineInvitationQr::dataUri($url, 280, 1),
        ]);
    }

    public function captureStatus($token)
    {
        $this->boot();
        $application = $this->verifiedApplication();
        $row = $this->captureRow($token);
        if (! $application || ! $row || (int) $row['application_id'] !== (int) $application->id) {
            return response()->json(['ok' => false], 404);
        }

        return response()->json([
            'ok' => true,
            'status' => $row['status'],
            'message' => isset($row['message']) ? $row['message'] : '',
        ]);
    }

    public function capturePhone($token)
    {
        $this->boot();
        $row = $this->captureRow($token);
        if (! $row) {
            abort(404);
        }

        return view('gbhs.public.capture', [
            'setting' => GbhsSetting::query()->first(),
            'token' => $token,
            'kind' => $row['kind'],
            'done' => $row['status'] === 'done',
            'label' => $row['kind'] === 'portrait' ? 'Portrait' : 'Identification',
        ]);
    }

    public function capturePhoneStore(Request $request, $token)
    {
        $this->boot();
        $row = $this->captureRow($token);
        if (! $row || $row['status'] === 'done') {
            return redirect()->route('gbhs.capture', ['token' => $token]);
        }
        $application = GbhsApplication::find($row['application_id']);
        if (! $application) {
            abort(404);
        }
        $message = '';
        if ($row['kind'] === 'portrait') {
            $request->validate(['photo' => 'required|image|max:12288']);
            $this->storePortraitFile($application, $request->file('photo'));
            $message = 'The portrait was received from your phone. Continue when you are happy with it.';
        } else {
            $request->validate(['document' => 'required|file|mimes:jpg,jpeg,png,webp|max:12288']);
            $path = $request->file('document')->getRealPath();
            try {
                $extracted = (new GbhsIdReader())->read($path);
            } finally {
                if (is_string($path) && is_file($path)) {
                    @unlink($path);
                }
            }
            if (! empty($extracted['name'])) {
                $application->id_holder_name = $extracted['name'];
            }
            if (! empty($extracted['number'])) {
                $application->id_number = $extracted['number'];
            }
            if (! empty($extracted['issued_on'])) {
                $application->id_issued_on = $extracted['issued_on'];
            }
            $application->save();
            $message = ! empty($extracted['read'])
                ? 'We read the document and deleted the image. Check the name, number, and date of issue, then continue.'
                : 'The image was deleted and could not be read. Type the name, number, and date of issue, or try another photo.';
        }
        $row['status'] = 'done';
        $row['message'] = $message;
        Cache::put($this->captureKey($token), $row, now()->addMinutes(20));

        return redirect()->route('gbhs.capture', ['token' => $token]);
    }

    protected function storePortraitFile(GbhsApplication $application, $file)
    {
        $dir = public_path('gbhs/photos');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $stored = $application->id.'-'.time().'.jpg';
        $file->move($dir, $stored);
        $application->photo = 'gbhs/photos/'.$stored;
        $application->photo_step = 'saved';
        $application->save();
    }

    protected function captureKey($token)
    {
        return 'gbhs.capture.'.preg_replace('/[^A-Za-z0-9]/', '', (string) $token);
    }

    protected function captureRow($token)
    {
        $token = (string) $token;
        if (! preg_match('/^[A-Za-z0-9]{32,64}$/', $token)) {
            return null;
        }
        $row = Cache::get($this->captureKey($token));

        return is_array($row) ? $row : null;
    }

    protected function applicationFromPhone(Request $request)
    {
        $application = session('gbhs_phone_verified') ? GbhsApplication::find(session('gbhs_application_id')) : null;
        if ($application && $application->phone_verified) {
            return $application;
        }
        if (! $request->filled('phone') || ! GbhsRules::mayStorePersonal(true)) {
            return null;
        }
        $digits = preg_replace('/\D/', '', CountryDialCodes::combine(
            $request->input('country_code', '+237'),
            $request->phone
        ));
        if (strlen($digits) < 8) {
            return null;
        }
        $application = GbhsMembership::openDraft($digits);
        if (! $application) {
            return null;
        }
        $application->phone_verified = 1;
        $application->save();
        session([
            'gbhs_phone' => $digits,
            'gbhs_phone_verified' => true,
            'gbhs_application_id' => $application->id,
        ]);

        return $application;
    }

    protected function verifiedApplication()
    {
        if (! session('gbhs_phone_verified')) {
            return null;
        }
        $application = GbhsApplication::find(session('gbhs_application_id'));
        if (! $application || ! $application->phone_verified) {
            return null;
        }

        return $application;
    }

    public function sign(Request $request)
    {
        $this->boot();
        $application = GbhsApplication::find(session('gbhs_application_id'));
        if (! $application || ! $application->phone_verified || ! $application->full_name || ! $application->id_step || ! $application->photo_step) {
            return back()->with('not_permitted', 'Complete the details, identification, and portrait stages first.');
        }
        $bylaw = GbhsBylawVersion::where('status', 'published')->orderBy('id', 'desc')->first();
        if (! $bylaw || ! $bylaw->file) {
            return back()->with('not_permitted', 'The association has not published the bylaws yet.');
        }
        $request->validate([
            'signature' => 'required|string',
            'agree_bylaws' => 'accepted',
            'consent_processing' => 'accepted',
        ]);
        $application->bylaw_version_id = $bylaw->id;
        $application->agree_bylaws = 1;
        $application->consent_processing = 1;
        $application->consent_announcements = $request->filled('consent_announcements') ? 1 : 0;
        $application->declaration = 'I have read bylaws version '.$bylaw->version.' and I apply for membership.';
        $raw = $request->signature;
        if (strpos($raw, 'base64,') !== false) {
            $raw = substr($raw, strpos($raw, 'base64,') + 7);
        }
        $binary = base64_decode($raw, true);
        if ($binary === false || strlen($binary) < 80) {
            return back()->with('not_permitted', 'Draw your signature before submitting.');
        }
        $dir = public_path('gbhs/signatures');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $relative = 'gbhs/signatures/'.$application->id.'.png';
        file_put_contents(public_path($relative), $binary);
        $application->signature_path = $relative;
        $application->signed_at = now();
        $application->status = 'submitted';
        $application->submitted_at = now();
        $application->save();
        if ($application->consent_announcements) {
            try {
                app(\App\Services\BeyondWasenderService::class)->sendText(
                    $application->phone,
                    'GBHS Kumbo: application '.$application->reference.' was received. Status stays private until it is reviewed.'
                );
            } catch (\Throwable $e) {
            }
        }

        return redirect()->route('gbhs.membership')->with('message', 'Application '.$application->reference.' was submitted.');
    }

    public function bylaw($id)
    {
        $this->boot();
        $bylaw = GbhsBylawVersion::where('id', $id)->where('status', 'published')->first();
        if (! $bylaw || ! $bylaw->file || ! is_file(public_path($bylaw->file))) {
            abort(404);
        }

        return response()->file(public_path($bylaw->file));
    }

    public function donateForm()
    {
        $this->boot();
        $setting = GbhsSetting::query()->first();
        $projects = GbhsProject::where('published', 1)->orderBy('title')->get();
        $batches = GbhsBatch::where('year', '>', 0)->orderBy('year')->get();
        $methods = GbhsDonate::methods();

        return view('gbhs.public.donate', compact('setting', 'projects', 'batches', 'methods'));
    }

    public function donate(Request $request)
    {
        $this->boot();
        $request->validate([
            'donor_name' => 'required|string|max:160',
            'amount' => 'required|integer|min:100',
            'method' => 'required|in:momo,card,crypto',
            'phone' => 'nullable|string|max:24',
            'country_code' => 'nullable|string|max:8',
            'email' => 'nullable|email',
        ]);
        if (! $request->filled('phone')) {
            return back()->with('not_permitted', 'Enter your phone number.')->withInput();
        }
        $phone = preg_replace('/\D/', '', \App\Support\CountryDialCodes::combine(
            $request->input('country_code', '+237'),
            $request->phone
        ));
        $payment = GbhsPayment::create([
            'public_token' => Str::random(32),
            'donor_name' => $request->donor_name,
            'phone' => $phone,
            'email' => $request->email,
            'amount' => (int) $request->amount,
            'method' => $request->method,
            'project_id' => $request->project_id ?: null,
            'batch_id' => $request->batch_id ?: null,
            'anonymous' => $request->filled('anonymous') ? 1 : 0,
            'message' => $request->message,
            'status' => 'pending',
        ]);
        try {
            $started = GbhsDonate::start($payment);
        } catch (\Throwable $e) {
            return redirect()->route('gbhs.donate')->with('not_permitted', $e->getMessage());
        }
        if (! empty($started['url'])) {
            return redirect()->away($started['url']);
        }

        return redirect()->route('gbhs.pay.return', ['token' => $payment->public_token]);
    }

    public function payReturn($token)
    {
        $this->boot();
        $payment = GbhsPayment::where('public_token', $token)->first();
        if (! $payment) {
            abort(404);
        }
        $status = GbhsDonate::confirm($payment);
        $payment->refresh();
        $setting = GbhsSetting::query()->first();
        $crypto = json_decode((string) $payment->provider_reference, true);
        if (! is_array($crypto) || empty($crypto['address'])) {
            $crypto = null;
        }

        return view('gbhs.public.pay', compact('payment', 'status', 'setting', 'crypto'));
    }

    public function statusForm()
    {
        $this->boot();

        return view('gbhs.public.status', [
            'setting' => GbhsSetting::query()->first(),
            'application' => null,
        ]);
    }

    public function statusSend(Request $request)
    {
        $this->boot();
        $request->validate([
            'reference' => 'required|string',
            'phone' => 'required|string',
        ]);
        $otp = app(BeyondAuthService::class)->createOtp($request->phone, 'gbhs-status');
        session(['gbhs_status_phone' => $otp['phone'], 'gbhs_status_reference' => trim($request->reference)]);
        try {
            app(\App\Services\BeyondWasenderService::class)->sendOtp($otp['phone'], $otp['code'], 'GBHS Kumbo status');
        } catch (\Throwable $e) {
            return back()->with('not_permitted', 'The verification code could not be sent.');
        }

        return back()->with('message', 'A code was sent on WhatsApp.');
    }

    public function statusCheck(Request $request)
    {
        $this->boot();
        $request->validate(['otp' => 'required|string']);
        $phone = session('gbhs_status_phone');
        $reference = session('gbhs_status_reference');
        $result = app(BeyondAuthService::class)->verifyOtp($phone, $request->otp, 'gbhs-status');
        if (empty($result['success'])) {
            return back()->with('not_permitted', 'That code is not valid.');
        }
        $application = GbhsApplication::where('reference', $reference)->where('phone', $phone)->first();
        if (! $application) {
            return back()->with('not_permitted', 'No application matches that reference and phone.');
        }
        $member = $application->member_id ? GbhsMember::find($application->member_id) : null;
        if ($member) {
            session(['gbhs_member_id' => $member->id]);
        }

        return view('gbhs.public.status', [
            'setting' => GbhsSetting::query()->first(),
            'application' => $application,
            'member' => $member,
        ]);
    }

    public function memberHome()
    {
        $this->boot();
        $member = GbhsMember::find(session('gbhs_member_id'));
        if (! $member || $member->status !== 'active') {
            return redirect()->route('gbhs.status')->with('not_permitted', 'Open your application with the reference and the verified phone.');
        }
        $application = GbhsApplication::find($member->application_id);
        $events = GbhsEvent::where('published', 1)->orderBy('starts_at')->get();
        $projects = GbhsProject::where('published', 1)->get();
        $raised = [];
        foreach ($projects as $project) {
            $raised[$project->id] = (int) GbhsContribution::where('project_id', $project->id)->where('verified', 1)->whereNotNull('payment_id')->sum('amount');
        }
        $setting = GbhsSetting::query()->first();

        return view('gbhs.public.member', compact('member', 'application', 'events', 'projects', 'raised', 'setting'));
    }

    public function contact(Request $request)
    {
        $this->boot();
        $request->validate([
            'name' => 'required|string|max:160',
            'body' => 'required|string|max:2000',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
        ]);
        GbhsMessage::create($request->only(['name', 'email', 'phone', 'body']));

        return back()->with('message', 'Your message was received.');
    }

    protected function boot()
    {
        if (! GbhsTenant::ready()) {
            abort(503, 'GBHS Kumbo is not installed yet.');
        }
        GbhsCatalog::ensure();
    }

    protected function publicTotals(GbhsSetting $setting = null)
    {
        $rows = GbhsContribution::query()->get(['classification', 'amount', 'in_kind_value', 'in_kind_qty', 'verified'])->map(function ($row) {
            return $row->toArray();
        })->all();
        $summary = GbhsRules::summarize($rows);
        if ($setting && $setting->show_provisional) {
            return $summary;
        }

        return [
            'reported' => null,
            'monetary' => $summary['verified'],
            'verified' => $summary['verified'],
            'unverified' => null,
            'cancelled' => null,
            'in_kind' => null,
            'difference' => null,
            'provisional' => false,
        ];
    }
}
