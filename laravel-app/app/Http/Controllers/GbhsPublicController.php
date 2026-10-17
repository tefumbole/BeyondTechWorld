<?php

namespace App\Http\Controllers;

use App\Gbhs\GbhsApplication;
use App\Gbhs\GbhsBatch;
use App\Gbhs\GbhsBylawVersion;
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
use App\Gbhs\GbhsTenant;
use App\Services\BeyondAuthService;
use Illuminate\Http\Request;
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
        $section = GbhsSection::where('slug', $slug)->where('published', 1)->first();
        $setting = GbhsSetting::query()->first();
        $leaders = GbhsLeader::where('published', 1)->orderBy('sort')->get();
        $batches = GbhsBatch::orderBy('year')->get();
        $news = GbhsSection::where('kind', 'news')->where('published', 1)->orderBy('id', 'desc')->get();
        $documents = Schema::hasTable('gbhs_documents')
            ? \App\Gbhs\GbhsDocument::where('published', 1)->orderBy('sort')->get()
            : collect();
        $events = GbhsEvent::where('published', 1)->orderBy('starts_at')->get();
        $projects = GbhsProject::where('published', 1)->orderBy('id', 'desc')->get();
        $raised = [];
        foreach ($projects as $project) {
            $raised[$project->id] = (int) GbhsContribution::where('project_id', $project->id)
                ->where('verified', 1)
                ->whereNotNull('payment_id')
                ->whereNotIn('classification', ['reversal', 'in_kind'])
                ->sum('amount');
        }
        $totals = $this->publicTotals($setting);
        $donors = GbhsContribution::where('verified', 1)
            ->where('anonymous', 0)
            ->whereNotIn('classification', ['reversal', 'in_kind'])
            ->orderBy('id', 'desc')
            ->limit(30)
            ->get();

        return view('gbhs.public.page', compact(
            'slug', 'section', 'setting', 'leaders', 'batches', 'news', 'documents',
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
        $step = 'phone';
        if (session('gbhs_phone_verified') && $application) {
            $step = $application->signature_path ? 'done' : ($application->full_name ? 'sign' : 'profile');
            if ($application->status === 'submitted' || $application->status === 'approved') {
                $step = 'done';
            }
        }

        return view('gbhs.public.membership', compact('setting', 'section', 'batches', 'bylaw', 'application', 'step'));
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
        if (! GbhsRules::mayStorePersonal(session('gbhs_phone_verified'))) {
            return back()->with('not_permitted', 'Verify your phone before entering personal details.');
        }
        $this->boot();
        $application = GbhsApplication::find(session('gbhs_application_id'));
        if (! $application || ! $application->phone_verified) {
            return back()->with('not_permitted', 'Verify your phone before entering personal details.');
        }
        $request->validate([
            'full_name' => 'required|string|max:160',
            'batch_id' => 'required|integer',
            'email' => 'nullable|email|max:160',
            'agree_bylaws' => 'accepted',
            'consent_processing' => 'accepted',
            'photo' => 'nullable|image|max:4096',
        ]);
        $bylaw = GbhsBylawVersion::where('status', 'published')->orderBy('id', 'desc')->first();
        if (! $bylaw || ! $bylaw->file) {
            return back()->with('not_permitted', 'The association has not published a bylaws PDF yet.');
        }
        $data = $request->only([
            'full_name', 'date_of_birth', 'gender', 'marital_status', 'years_attended',
            'profession', 'employer', 'email', 'country', 'region', 'city', 'address', 'batch_id',
        ]);
        if (empty($data['date_of_birth'])) {
            $data['date_of_birth'] = null;
        }
        $data['bylaw_version_id'] = $bylaw->id;
        $data['agree_bylaws'] = 1;
        $data['consent_processing'] = 1;
        $data['consent_announcements'] = $request->filled('consent_announcements') ? 1 : 0;
        $data['declaration'] = 'I have read bylaws version '.$bylaw->version.' and I apply for membership.';
        if ($request->hasFile('photo')) {
            $dir = public_path('gbhs/photos');
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $file = $request->file('photo');
            $stored = $application->id.'-'.time().'.'.$file->getClientOriginalExtension();
            $file->move($dir, $stored);
            $data['photo'] = 'gbhs/photos/'.$stored;
        }
        $application->fill($data);
        $application->save();

        return redirect()->route('gbhs.membership');
    }

    public function sign(Request $request)
    {
        $this->boot();
        $application = GbhsApplication::find(session('gbhs_application_id'));
        if (! $application || ! $application->phone_verified || ! $application->full_name || ! $application->agree_bylaws) {
            return back()->with('not_permitted', 'Complete the form and accept the published bylaws first.');
        }
        $request->validate(['signature' => 'required|string']);
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
        $methods = array_keys(GbhsDonate::methods());
        $request->validate([
            'donor_name' => 'required|string|max:160',
            'amount' => 'required|integer|min:100',
            'method' => 'required|in:'.implode(',', $methods),
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
        ]);
        if ($request->method === 'momo' && ! $request->filled('phone')) {
            return back()->with('not_permitted', 'Enter the Mobile Money number.')->withInput();
        }
        $payment = GbhsPayment::create([
            'public_token' => Str::random(32),
            'donor_name' => $request->donor_name,
            'phone' => $request->phone,
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

        return view('gbhs.public.pay', compact('payment', 'status', 'setting'));
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
