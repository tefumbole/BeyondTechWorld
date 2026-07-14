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

        if ($contract->status === EventContract::STATUS_APPROVED) {
            return view('events.contracts.signed', compact('contract'));
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

        return redirect()->route('event.contract.sign', $token)
            ->with('message', 'Thank you! Your signature has been recorded. Beyond Enterprise will countersign and send the final PDF shortly.');
    }
}
