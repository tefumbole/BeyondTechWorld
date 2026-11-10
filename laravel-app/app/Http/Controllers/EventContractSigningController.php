<?php

namespace App\Http\Controllers;

use App\EventContract;
use App\Services\EventContractService;
use Illuminate\Http\Request;

class EventContractSigningController extends Controller
{
    public function show($token, EventContractService $contracts)
    {
        $contract = EventContract::with(['event', 'assignment.workerProfile.customer'])
            ->where('signature_token', $token)
            ->firstOrFail();

        if (in_array($contract->status, [EventContract::STATUS_WORKER_SIGNED, EventContract::STATUS_APPROVED], true)) {
            return view('events.contracts.signed', compact('contract'))
                ->with('message', 'This link has expired. The contract is already signed.');
        }

        return view('events.contracts.sign', compact('contract'));
    }

    public function sign(Request $request, $token, EventContractService $contracts)
    {
        $contract = EventContract::where('signature_token', $token)->firstOrFail();

        if (in_array($contract->status, [EventContract::STATUS_WORKER_SIGNED, EventContract::STATUS_APPROVED], true)) {
            return redirect()->route('event.contract.sign', $token)
                ->with('message', 'This contract has already been signed.');
        }

        $request->validate([
            'agreement_accepted' => 'required|accepted',
            'signature_image' => 'required|string|max:500000',
        ]);

        $contracts->workerSign($contract, $request->signature_image);
        $signed = $contracts->finishSigned($contract->fresh());
        $signed->load('assignment.workerProfile.customer', 'assignment.workerProfile.user');
        $phone = $signed->assignment ? app(\App\Services\EventCrewInviteService::class)->phoneFor($signed->assignment) : '';
        if ($phone && $signed->signed_pdf_path && is_file(public_path($signed->signed_pdf_path))) {
            try {
                $this->sendWhatsAppDocumentToPhone(
                    $phone,
                    public_path($signed->signed_pdf_path),
                    $signed->reference_no.'.pdf',
                    url($signed->signed_pdf_path)
                );
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Signed contract WhatsApp failed: '.$e->getMessage());
            }
        }

        return redirect()->route('event.contract.sign', $token)
            ->with('message', 'Signed. Your copy, with the company signature, has been sent to your WhatsApp.');
    }
}
