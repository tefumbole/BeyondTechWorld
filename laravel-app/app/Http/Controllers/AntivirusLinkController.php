<?php

namespace App\Http\Controllers;

use App\Antivirus\AntivirusRequest;
use App\Antivirus\AntivirusRuleException;
use App\Http\Controllers\Concerns\AntivirusHttp;
use App\Services\Antivirus\AntivirusRequestService;
use App\Support\AntivirusCatalog;
use App\Support\WhatsAppPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AntivirusLinkController extends Controller
{
    use AntivirusHttp;

    /** @var AntivirusRequestService */
    protected $antivirus;

    public function __construct(AntivirusRequestService $antivirus)
    {
        $this->antivirus = $antivirus;
    }

    public function handoff($token)
    {
        $handoff = $this->antivirus->findHandoff($token);
        if (! $handoff) {
            return $this->linkError(new AntivirusRuleException('link', 'This phone link is not valid.'));
        }
        if (! $handoff->consumed_at && $handoff->expires_at && $handoff->expires_at->lt(now())) {
            return $this->linkError(new AntivirusRuleException('link', 'This phone link has expired. Generate a new QR code on the original screen.'));
        }
        $request = AntivirusRequest::find($handoff->antivirus_request_id);
        if (! $request) {
            return $this->linkError(new AntivirusRuleException('link', 'This phone link is not valid.'));
        }

        return view('beyond.antivirus.handoff', [
            'handoff' => $handoff,
            'antivirus' => $request,
            'token' => $token,
            'done' => (bool) $handoff->consumed_at,
            'focus' => request('focus') === 'sign' ? 'sign' : 'selfie',
        ]);
    }

    public function handoffOtp(Request $request, $token)
    {
        try {
            $handoff = $this->antivirus->openHandoff($token);
            $row = AntivirusRequest::findOrFail($handoff->antivirus_request_id);
            if ((string) $request->input('send') === '1') {
                $this->sendAntivirusCode('handoff-'.$handoff->id, $row->requester_phone, $row->requester_phone);

                return back()->with('message', 'A code was sent to your phone.');
            }
            $this->checkAntivirusCode('handoff-'.$handoff->id, $row->requester_phone, $request->input('otp'));
            $this->antivirus->markHandoffVerified($handoff);
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return back();
    }

    public function handoffCapture(Request $request, $token)
    {
        try {
            $handoff = $this->antivirus->openHandoff($token);
            $focus = $request->input('focus') === 'sign' ? 'sign' : 'selfie';
            $this->antivirus->captureHandoff($handoff, $request->file('selfie'), $request->input('signature'), $focus);
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return back()->with('message', 'Saved. You can continue on the first screen.');
    }

    public function approve($token)
    {
        try {
            $approver = $this->antivirus->openApprover($token);
        } catch (AntivirusRuleException $e) {
            return $this->linkError($e);
        }
        $row = $approver->requestRow;

        return view('beyond.antivirus.approve', [
            'approver' => $approver,
            'antivirus' => $row,
            'token' => $token,
            'verified' => $approver->phone_verified_at && $approver->phone_verified_at->gt(now()->subMinutes(30)),
            'phone' => WhatsAppPhone::display($approver->phone),
        ]);
    }

    public function approveOtp(Request $request, $token)
    {
        try {
            $approver = $this->antivirus->openApprover($token);
            if ((string) $request->input('send') === '1') {
                $this->sendAntivirusCode('approver-'.$approver->id, $approver->phone, $approver->phone);

                return back()->with('message', 'A code was sent to your phone.');
            }
            $this->checkAntivirusCode('approver-'.$approver->id, $approver->phone, $request->input('otp'));
            $this->antivirus->markApproverVerified($approver);
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return back();
    }

    public function approveSign(Request $request, $token)
    {
        try {
            $approver = $this->antivirus->openApprover($token);
            $this->antivirus->signApprover($approver, $request->input('signature'), (string) $request->input('confirm_name') === '1');
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return back()->with('message', 'Your signature has been recorded.');
    }

    public function approveDecline(Request $request, $token)
    {
        try {
            $approver = $this->antivirus->openApprover($token);
            $this->antivirus->declineApprover($approver, $request->input('reason'));
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return back()->with('message', 'The request has been declined.');
    }

    public function receipt($token)
    {
        try {
            $row = $this->antivirus->findByReceiptToken($token);
        } catch (AntivirusRuleException $e) {
            return $this->linkError($e);
        }
        if (! $row || $row->status !== 'approved') {
            return $this->linkError(new AntivirusRuleException('link', 'This confirmation link is not available.'));
        }

        return view('beyond.antivirus.receipt', [
            'antivirus' => $row,
            'token' => $token,
            'verified' => $row->receipt_phone_verified_at && $row->receipt_phone_verified_at->gt(now()->subMinutes(30)),
            'phone' => WhatsAppPhone::display($row->requester_phone),
        ]);
    }

    public function receiptOtp(Request $request, $token)
    {
        try {
            $row = $this->antivirus->findByReceiptToken($token);
            if ((string) $request->input('send') === '1') {
                $this->sendAntivirusCode('receipt-'.$row->id, $row->requester_phone, $row->requester_phone);

                return back()->with('message', 'A code was sent to your phone.');
            }
            $this->checkAntivirusCode('receipt-'.$row->id, $row->requester_phone, $request->input('otp'));
            $this->antivirus->markReceiptVerified($row);
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return back();
    }

    public function receiptStore(Request $request, $token)
    {
        try {
            $row = $this->antivirus->findByReceiptToken($token);
            $this->antivirus->confirmReceipt($row, $request->input('quantity'), $request->input('note'));
        } catch (\Throwable $e) {
            return $this->antivirusFail($e);
        }

        return back()->with('message', 'Receipt confirmation saved.');
    }

    public function letter($token)
    {
        try {
            $row = $this->antivirus->findByPdfToken($token);
        } catch (AntivirusRuleException $e) {
            return $this->linkError($e);
        }
        if (! $row || $row->status !== 'approved' || ! $row->pdf_path || ! Storage::disk('local')->exists($row->pdf_path)) {
            return $this->linkError(new AntivirusRuleException('link', 'The signed approval is not ready yet.'));
        }

        return response()->file(storage_path('app/'.$row->pdf_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.str_replace(['/', ' '], '-', 'Antivirus-'.$row->reference).'.pdf"',
        ]);
    }

    protected function linkError(AntivirusRuleException $e)
    {
        return response()->view('beyond.antivirus.message', [
            'title' => 'Antivirus request',
            'message' => $e->getMessage(),
        ], 410);
    }
}
