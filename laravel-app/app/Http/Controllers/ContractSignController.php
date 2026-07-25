<?php

namespace App\Http\Controllers;

use App\Services\Contracts\ContractInstanceService;
use App\Services\Contracts\ContractWorkflowService;
use Illuminate\Http\Request;

class ContractSignController extends Controller
{
    protected $workflow;
    protected $instances;

    public function __construct(ContractWorkflowService $workflow, ContractInstanceService $instances)
    {
        $this->workflow = $workflow;
        $this->instances = $instances;
    }

    public function show(Request $request, $token)
    {
        $req = $this->workflow->findByToken($token);
        if (! $req) {
            return view('contracts.sign', [
                'error' => 'This signature link is invalid or has expired.',
                'token' => $token,
                'contract' => null,
                'signatory' => null,
                'bodyHtml' => '',
            ]);
        }
        $this->workflow->markOpened($req, $request);
        $sig = $req->signatory;
        $contract = $sig ? $sig->contract()->with(['partyA', 'partyB', 'currentRevision', 'signatories'])->first() : null;

        return view('contracts.sign', [
            'error' => null,
            'token' => $token,
            'contract' => $contract,
            'signatory' => $sig,
            'bodyHtml' => $contract ? $this->instances->renderedHtml($contract) : '',
            'requestRow' => $req,
        ]);
    }

    public function submit(Request $request, $token)
    {
        $request->validate([
            'typed_name' => 'required|string|max:255',
            'consent' => 'required|accepted',
        ]);
        $req = $this->workflow->findByToken($token);
        if (! $req) {
            return back()->withErrors(['token' => 'Invalid or expired signature link.']);
        }
        try {
            $this->workflow->sign($req, [
                'typed_name' => $request->typed_name,
                'consent' => true,
                'signature_image' => $request->get('signature_image'),
            ], $request);
        } catch (\Throwable $e) {
            return back()->withErrors(['sign' => $e->getMessage()]);
        }

        return view('contracts.sign', [
            'error' => null,
            'token' => $token,
            'contract' => null,
            'signatory' => null,
            'bodyHtml' => '',
            'done' => true,
            'message' => 'Thank you. Your signature has been recorded.',
        ]);
    }

    public function decline(Request $request, $token)
    {
        $request->validate(['reason' => 'required|string|max:1000']);
        $req = $this->workflow->findByToken($token);
        if (! $req) {
            return back()->withErrors(['token' => 'Invalid or expired signature link.']);
        }
        try {
            $this->workflow->decline($req, $request->reason, $request);
        } catch (\Throwable $e) {
            return back()->withErrors(['decline' => $e->getMessage()]);
        }

        return view('contracts.sign', [
            'error' => null,
            'token' => $token,
            'contract' => null,
            'signatory' => null,
            'bodyHtml' => '',
            'done' => true,
            'message' => 'You have declined to sign this contract.',
        ]);
    }
}
