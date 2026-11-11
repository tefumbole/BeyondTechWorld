<?php

namespace App\Http\Controllers;

use App\Antivirus\AntivirusApprover;
use App\Antivirus\AntivirusRequest;
use App\Http\Controllers\Concerns\AntivirusHttp;
use App\Services\Antivirus\AntivirusRequestService;
use App\Support\AntivirusCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AntivirusFinanceController extends Controller
{
    use AntivirusHttp;

    /** @var AntivirusRequestService */
    protected $antivirus;

    public function __construct(AntivirusRequestService $antivirus)
    {
        $this->antivirus = $antivirus;
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();
        $query = AntivirusRequest::with('approvers')->where('status', '!=', 'draft');
        $region = trim((string) $request->query('region'));
        $year = (int) $request->query('year');
        $status = trim((string) $request->query('status'));
        if ($region !== '' && in_array($region, AntivirusCatalog::regions(), true)) {
            $query->where('region', $region);
        }
        if ($year >= 2024 && $year <= 2100) {
            $query->where('year', $year);
        }
        if (in_array($status, ['awaiting_station', 'awaiting_chief', 'approved', 'declined'], true)) {
            $query->where('status', $status);
        }

        return view('antivirus.finance.index', [
            'rows' => $query->orderBy('id', 'desc')->paginate(30)->appends($request->query()),
            'settings' => $this->antivirus->settings(),
            'regions' => AntivirusCatalog::regions(),
            'filters' => ['region' => $region, 'year' => $year ?: '', 'status' => $status],
            'publicUrl' => route('antivirus.form'),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $this->authorizeAdmin();
        try {
            $this->antivirus->saveSettings(
                $request->input('campaign_year'),
                $request->input('chief_name'),
                $request->input('chief_phone')
            );
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return back()->with('message', 'Antivirus settings saved.');
    }

    public function show($id)
    {
        $this->authorizeAdmin();
        $row = AntivirusRequest::with(['approvers', 'activities'])->findOrFail($id);

        return view('antivirus.finance.show', [
            'row' => $row,
            'settings' => $this->antivirus->settings(),
            'chiefVerified' => $row->chief_phone_verified_at && $row->chief_phone_verified_at->gt(now()->subMinutes(30)),
        ]);
    }

    public function chiefOtp(Request $request, $id)
    {
        $this->authorizeAdmin();
        $row = AntivirusRequest::findOrFail($id);
        $settings = $this->antivirus->settings();
        try {
            if (! $settings->chief_phone) {
                throw new \App\Antivirus\AntivirusRuleException('chief_phone', 'Save the Chief’s phone on the Antivirus tab first.');
            }
            if ((string) $request->input('send') === '1') {
                $this->sendAntivirusCode('chief-'.$row->id, $settings->chief_phone, $settings->chief_phone);

                return back()->with('message', 'A code was sent to the Chief’s phone.');
            }
            $this->checkAntivirusCode('chief-'.$row->id, $settings->chief_phone, $request->input('otp'));
            $this->antivirus->markChiefVerified($row);
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return back();
    }

    public function chiefSign(Request $request, $id)
    {
        $this->authorizeAdmin();
        try {
            $row = AntivirusRequest::findOrFail($id);
            $this->antivirus->signChief($row, $request->input('signature'));
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return back()->with('message', 'The request is approved.');
    }

    public function chiefDecline(Request $request, $id)
    {
        $this->authorizeAdmin();
        try {
            $row = AntivirusRequest::findOrFail($id);
            $this->antivirus->declineChief($row, $request->input('reason'));
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return back()->with('message', 'The request has been declined.');
    }

    public function payment(Request $request, $id)
    {
        $this->authorizeAdmin();
        try {
            $row = AntivirusRequest::findOrFail($id);
            $this->antivirus->recordPayment(
                $row,
                $request->input('payment_date'),
                $request->input('payment_amount'),
                $request->input('payment_reference')
            );
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return back()->with('message', 'Payment recorded.');
    }

    public function acknowledge($id)
    {
        $this->authorizeAdmin();
        try {
            $this->antivirus->acknowledge(AntivirusRequest::findOrFail($id));
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return back()->with('message', 'Finance acknowledgement saved.');
    }

    public function pdf($id)
    {
        $this->authorizeAdmin();
        $row = AntivirusRequest::findOrFail($id);
        if ($row->status === 'approved' && (! $row->pdf_path || ! Storage::disk('local')->exists($row->pdf_path))) {
            try {
                $this->antivirus->writePdf($row);
                $row = $row->fresh();
            } catch (\Throwable $e) {
                report($e);
            }
        }
        if (! $row->pdf_path || ! Storage::disk('local')->exists($row->pdf_path)) {
            abort(404);
        }

        return response()->file(storage_path('app/'.$row->pdf_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.str_replace(['/', ' '], '-', 'Antivirus-'.$row->reference).'.pdf"',
        ]);
    }

    public function selfie($id)
    {
        $this->authorizeAdmin();

        return $this->privateFile(AntivirusRequest::findOrFail($id)->selfie_path);
    }

    public function requesterSignature($id)
    {
        $this->authorizeAdmin();

        return $this->privateFile(AntivirusRequest::findOrFail($id)->signature_path);
    }

    public function chiefSignature($id)
    {
        $this->authorizeAdmin();

        return $this->privateFile(AntivirusRequest::findOrFail($id)->chief_signature_path);
    }

    public function approverSignature($id)
    {
        $this->authorizeAdmin();
        $approver = AntivirusApprover::findOrFail($id);

        return $this->privateFile($approver->signature_path);
    }

    public function resend(Request $request, $id)
    {
        $this->authorizeAdmin();
        $row = AntivirusRequest::findOrFail($id);
        try {
            $kind = (string) $request->input('kind');
            if ($kind === 'chief') {
                $this->antivirus->resendChief($row);
            } elseif ($kind === 'approver') {
                $approver = AntivirusApprover::where('antivirus_request_id', $row->id)->findOrFail($request->input('approver_id'));
                $this->antivirus->resendApprover($approver);
            } elseif ($kind === 'queued') {
                $this->antivirus->retryQueued();
            } else {
                throw new \App\Antivirus\AntivirusRuleException('request', 'Choose what to resend.');
            }
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return back()->with('message', 'The message was queued again.');
    }

    protected function privateFile($path)
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->file(storage_path('app/'.$path));
    }

    protected function authorizeAdmin()
    {
        if (! Auth::check() || ! in_array((int) Auth::user()->role_id, [1, 2], true)) {
            abort(403);
        }
    }
}
