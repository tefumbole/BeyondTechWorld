<?php

namespace App\Http\Controllers;

use App\Antivirus\AntivirusRequest;
use App\Antivirus\AntivirusRuleException;
use App\Http\Controllers\Concerns\AntivirusHttp;
use App\Services\Antivirus\AntivirusRequestService;
use App\Services\Cloud\CloudTenantContext;
use App\Services\MobileMoneyHolderService;
use App\Support\AntivirusCatalog;
use App\Support\WhatsAppPhone;
use Illuminate\Http\Request;

class AntivirusPublicController extends Controller
{
    use AntivirusHttp;

    /** @var AntivirusRequestService */
    protected $antivirus;

    public function __construct(AntivirusRequestService $antivirus)
    {
        $this->antivirus = $antivirus;
    }

    public function form()
    {
        $this->guardTenant();
        $draft = $this->draft();
        $panel = $this->panel($draft);
        $token = (string) session('antivirus_handoff_token', '');
        $handoff = null;
        $qr = null;
        if ($token !== '' && $panel === 'capture') {
            $handoff = $this->antivirus->findHandoff($token);
            if (! $handoff || $handoff->consumed_at || ($handoff->expires_at && $handoff->expires_at->lt(now()))) {
                session()->forget('antivirus_handoff_token');
                $handoff = null;
            } else {
                $qr = $this->handoffQr($token, (string) session('antivirus_handoff_focus', ''));
                if ($qr === '') {
                    $qr = null;
                }
            }
        }

        return view('beyond.antivirus.form', [
            'panel' => $panel,
            'visual' => $this->visual($panel),
            'percent' => $this->percent($panel, $draft),
            'draft' => $draft,
            'year' => (int) $this->antivirus->settings()->campaign_year,
            'phone' => $draft ? WhatsAppPhone::display($draft->requester_phone) : WhatsAppPhone::display((string) session('antivirus_lookup_phone', '')),
            'suggestedName' => (string) session('antivirus_lookup_name', ''),
            'lookupSource' => (string) session('antivirus_lookup_source', ''),
            'regions' => AntivirusCatalog::regions(),
            'requesterRoles' => AntivirusCatalog::requesterRoles(),
            'approverRoles' => AntivirusCatalog::approverRoles(),
            'savedApprovers' => $draft ? $this->antivirus->savedApprovers($draft) : [],
            'qr' => $qr,
            'handoff' => $handoff,
            'handoffFocus' => (string) session('antivirus_handoff_focus', ''),
            'resign' => $draft && $draft->signature_path && ! $draft->signatureIsCurrent(),
        ]);
    }

    public function phone(Request $request)
    {
        $this->guardTenant();
        try {
            $digits = WhatsAppPhone::normalize($request->input('phone'));
            if (strpos($digits, '237') !== 0 || strlen($digits) !== 12) {
                throw new AntivirusRuleException('phone', 'Enter a Cameroon phone number, for example 675321739.');
            }
            $found = $this->lookupName($digits);
            $this->rememberHit($digits, $found);
            session([
                'antivirus_lookup_phone' => $digits,
                'antivirus_lookup_name' => $found['name'],
                'antivirus_lookup_source' => $found['source'],
            ]);
            $typed = trim((string) $request->input('name'));
            if ($typed === '') {
                return redirect()->route('antivirus.form')->withInput(['phone' => WhatsAppPhone::display($digits)])->with(
                    'message',
                    $found['name'] !== '' ? 'Check the name, then continue. A code is sent on the next step.' : 'No registered name was found. Type your full name, then continue.'
                );
            }
            $source = ($found['name'] !== '' && strcasecmp($typed, $found['name']) === 0) ? $found['source'] : 'manual';
            $this->sendAntivirusCode('requester', $digits, $digits);
            session([
                'antivirus_pending_name' => $typed,
                'antivirus_pending_source' => $source,
                'antivirus_otp_phone' => $digits,
            ]);
        } catch (AntivirusRuleException $e) {
            return $this->antivirusFail($e);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['phone' => 'Enter a Cameroon phone number, for example 675321739.']);
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return redirect()->route('antivirus.form')->with('message', 'A code was sent to your phone. Type it here.');
    }

    public function name(Request $request)
    {
        $this->guardTenant();
        $phone = (string) session('antivirus_lookup_phone', '');
        if ($phone === '') {
            return redirect()->route('antivirus.form');
        }
        try {
            $suggested = (string) session('antivirus_lookup_name', '');
            $source = (string) session('antivirus_lookup_source', 'manual');
            if ((string) $request->input('use_lookup') === '1' && $suggested !== '' && in_array($source, ['campay', 'pawapay'], true)) {
                $name = $suggested;
            } else {
                $name = trim((string) $request->input('name'));
                $source = 'manual';
            }
            if (trim($name) === '') {
                throw new AntivirusRuleException('name', 'Enter your full name.');
            }
            $this->sendAntivirusCode('requester', $phone, $phone);
            session([
                'antivirus_pending_name' => $name,
                'antivirus_pending_source' => $source,
                'antivirus_otp_phone' => $phone,
            ]);
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return redirect()->route('antivirus.form')->with('message', 'A code was sent to your phone. Type it here.');
    }

    public function otp(Request $request)
    {
        $this->guardTenant();
        $phone = (string) session('antivirus_otp_phone', '');
        if ($phone === '') {
            return redirect()->route('antivirus.form');
        }
        try {
            $this->checkAntivirusCode('requester', $phone, $request->input('otp'));
            $draft = $this->antivirus->beginDraft(
                $phone,
                (string) session('antivirus_pending_name', ''),
                (string) session('antivirus_pending_source', 'manual')
            );
            session([
                'antivirus_draft_id' => $draft->id,
                'antivirus_otp_phone' => null,
            ]);
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return redirect()->route('antivirus.form');
    }

    public function role(Request $request)
    {
        try {
            $draft = $this->requireDraft();
            $had = (bool) $draft->signature_path;
            $this->antivirus->saveRole($draft, $request->input('role'), $request->input('role_other'));
            $draft = $draft->fresh();
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }
        $redirect = redirect()->route('antivirus.form');
        if ($had && ! $draft->signatureIsCurrent()) {
            $redirect->with('message', 'Sign again. Your role changed after the last signature.');
        }

        return $redirect;
    }

    public function station(Request $request)
    {
        try {
            $draft = $this->requireDraft();
            $had = (bool) $draft->signature_path;
            $lines = null;
            if ((string) $request->input('by_department') === '1') {
                $names = (array) $request->input('department_name', []);
                $qtys = (array) $request->input('department_quantity', []);
                $lines = [];
                foreach ($names as $index => $name) {
                    $lines[] = [
                        'name' => $name,
                        'quantity' => isset($qtys[$index]) ? $qtys[$index] : '',
                    ];
                }
            }
            $this->antivirus->saveStation(
                $draft,
                $request->input('region'),
                $request->input('station'),
                $request->input('quantity'),
                $lines
            );
            $draft = $draft->fresh();
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }
        $redirect = redirect()->route('antivirus.form');
        if ($had && ! $draft->signatureIsCurrent()) {
            $redirect->with('message', 'Sign again. The station, quantity, or year changed.');
        }

        return $redirect;
    }

    public function selfie(Request $request)
    {
        try {
            $this->antivirus->saveSelfie($this->requireDraft(), $request->file('selfie'));
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return redirect()->route('antivirus.form');
    }

    public function signature(Request $request)
    {
        try {
            $this->antivirus->saveSignature($this->requireDraft(), $request->input('signature'));
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return redirect()->route('antivirus.form');
    }

    public function handoff(Request $request)
    {
        try {
            $draft = $this->requireDraft();
            $focus = $request->input('focus') === 'sign' ? 'sign' : 'selfie';
            $token = $this->antivirus->issueHandoff($draft);
            session([
                'antivirus_handoff_token' => $token,
                'antivirus_handoff_focus' => $focus,
            ]);
            if ($request->expectsJson() || $request->ajax()) {
                $qr = $this->handoffQr($token, $focus);
                if ($qr === '') {
                    return response()->json(['message' => 'The QR code could not be created. Try again.'], 500);
                }

                return response()->json([
                    'qr' => $qr,
                    'status_url' => route('antivirus.status'),
                ]);
            }
        } catch (AntivirusRuleException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return $this->antivirusFail($e);
        } catch (\Throwable $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'The QR code could not be created. Try again.'], 500);
            }

            throw $e;
        }

        return redirect()->route('antivirus.form');
    }

    public function status()
    {
        $draft = $this->draft();
        if (! $draft) {
            return response()->json(['ready' => false]);
        }

        return response()->json([
            'selfie' => (bool) $draft->selfie_path,
            'signature' => (bool) $draft->signatureIsCurrent(),
            'ready' => (bool) ($draft->selfie_path && $draft->signatureIsCurrent()),
        ]);
    }

    public function lookup(Request $request)
    {
        $this->guardTenant();
        try {
            $digits = WhatsAppPhone::normalize($request->input('phone'));
        } catch (\Throwable $e) {
            return response()->json(['name' => '', 'source' => 'manual'], 422);
        }
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return response()->json(['name' => '', 'source' => 'manual'], 422);
        }
        $found = $this->lookupName($digits);
        $this->rememberHit($digits, $found);

        return response()->json([
            'name' => isset($found['name']) ? (string) $found['name'] : '',
            'source' => isset($found['source']) ? (string) $found['source'] : 'manual',
        ]);
    }

    public function approvers(Request $request)
    {
        try {
            $draft = $this->requireDraft();
            $raw = [$this->approverInput($request, '1')];
            $second = $this->approverInput($request, '2');
            if ($second['phone'] !== '' || $second['role'] !== '' || $second['manual_name'] !== '') {
                $raw[] = $second;
            }
            $hits = session('antivirus_hits', []);
            $this->antivirus->saveApprovers($draft, $raw, is_array($hits) ? $hits : []);
            session(['antivirus_reviewing' => 1]);
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return redirect()->route('antivirus.form');
    }

    public function editApprovers()
    {
        session()->forget('antivirus_reviewing');

        return redirect()->route('antivirus.form');
    }

    public function submit()
    {
        $existing = $this->draft();
        if ($existing && $existing->status !== 'draft') {
            return redirect()->route('antivirus.form');
        }
        try {
            $draft = $this->requireDraft();
            $this->antivirus->submit($draft);
            session()->forget('antivirus_reviewing');
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return redirect()->route('antivirus.form')->with('message', 'Your request has been submitted.');
    }

    public function restart()
    {
        $draft = $this->draft();
        if ($draft && $draft->status === 'draft') {
            try {
                $this->antivirus->discard($draft);
            } catch (\Throwable $e) {
                return $this->antivirusFail($e);
            }
        }
        session()->forget([
            'antivirus_draft_id',
            'antivirus_lookup_phone',
            'antivirus_lookup_name',
            'antivirus_lookup_source',
            'antivirus_otp_phone',
            'antivirus_pending_name',
            'antivirus_pending_source',
            'antivirus_reviewing',
            'antivirus_handoff_token',
            'antivirus_hits',
        ]);

        return redirect()->route('antivirus.form');
    }

    protected function approverInput(Request $request, $slot)
    {
        return [
            'role' => trim((string) $request->input('approver'.$slot.'_role')),
            'role_other' => trim((string) $request->input('approver'.$slot.'_role_other')),
            'phone' => trim((string) $request->input('approver'.$slot.'_phone')),
            'manual_name' => trim((string) $request->input('approver'.$slot.'_manual_name')),
        ];
    }

    protected function lookupName($digits)
    {
        try {
            $hit = app(MobileMoneyHolderService::class)->lookup($digits, false, false);
        } catch (\Throwable $e) {
            $hit = [];
        }
        $source = isset($hit['source']) ? (string) $hit['source'] : '';
        $name = isset($hit['name']) ? trim((string) $hit['name']) : '';
        if (! in_array($source, ['campay', 'pawapay'], true) || $name === '') {
            return ['name' => '', 'source' => 'manual'];
        }

        return ['name' => $name, 'source' => $source];
    }

    protected function handoffQr($token, $focus = 'selfie')
    {
        $focus = $focus === 'sign' ? 'sign' : 'selfie';
        try {
            $svg = (string) \QrCode::size(168)->margin(1)->generate(
                route('antivirus.handoff.show', ['token' => $token]).'?focus='.$focus
            );
        } catch (\Throwable $e) {
            return '';
        }
        $svg = (string) preg_replace('/^\s*<\?xml[^>]*\?>\s*/', '', $svg);
        $start = strpos($svg, '<svg');
        if ($start === false) {
            return '';
        }

        return substr($svg, $start);
    }

    protected function draft()
    {
        $id = (int) session('antivirus_draft_id', 0);
        if ($id < 1) {
            return null;
        }

        return AntivirusRequest::find($id);
    }

    protected function requireDraft()
    {
        $this->guardTenant();
        $draft = $this->draft();
        if (! $draft || $draft->status !== 'draft') {
            throw new AntivirusRuleException('request', 'Verify your phone number before continuing.');
        }

        return $draft;
    }

    protected function panel($draft)
    {
        if ($draft && $draft->status !== 'draft') {
            return 'done';
        }
        if (! $draft || ! $draft->phone_verified_at) {
            if (session('antivirus_otp_phone')) {
                return 'otp';
            }

            return 'phone';
        }
        if (! $draft->requester_role) {
            return 'role';
        }
        if (! $draft->region || ! $draft->station || ! $draft->quantity) {
            return 'station';
        }
        if (! $draft->selfie_path || ! $draft->signatureIsCurrent()) {
            return 'capture';
        }
        if (session('antivirus_reviewing')) {
            return 'review';
        }

        return 'approvers';
    }

    protected function visual($panel)
    {
        if (in_array($panel, ['station'], true)) {
            return 2;
        }
        if ($panel === 'capture') {
            return 3;
        }
        if (in_array($panel, ['approvers', 'review', 'done'], true)) {
            return 4;
        }

        return 1;
    }

    protected function percent($panel, $draft)
    {
        $map = [
            'phone' => 10,
            'otp' => 22,
            'role' => 36,
            'station' => 52,
            'capture' => 68,
            'approvers' => 84,
            'review' => 94,
            'done' => 100,
        ];
        $value = isset($map[$panel]) ? $map[$panel] : 10;
        if ($panel === 'capture' && $draft) {
            if ($draft->selfie_path) {
                $value = 74;
            }
            if ($draft->signatureIsCurrent()) {
                $value = 80;
            }
        }

        return $value;
    }

    protected function rememberHit($digits, array $found)
    {
        if ($found['name'] === '') {
            return;
        }
        $hits = session('antivirus_hits', []);
        if (! is_array($hits)) {
            $hits = [];
        }
        $hits[$digits] = $found;
        session(['antivirus_hits' => $hits]);
    }

    protected function guardTenant()
    {
        if (! app(CloudTenantContext::class)->has()) {
            abort(503);
        }
    }
}
