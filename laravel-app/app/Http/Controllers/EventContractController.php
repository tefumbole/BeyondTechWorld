<?php

namespace App\Http\Controllers;

use App\Event;
use App\EventAssignment;
use App\EventContract;
use App\EventContractTemplate;
use App\Services\EventContractService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class EventContractController extends Controller
{
    protected $all_permission = [];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $role = Role::find(Auth::user()->role_id);
            foreach (Role::findByName($role->name)->permissions as $permission) {
                $this->all_permission[] = $permission->name;
            }
            View::share('all_permission', $this->all_permission);

            return $next($request);
        });
    }

    protected function can($perm)
    {
        if (! in_array($perm, $this->all_permission, true)) {
            abort(403);
        }
    }

    public function generate(Request $request, $eventId, EventContractService $contracts)
    {
        $this->can('event_contracts.create');

        $event = Event::with('assignments.workerProfile')->findOrFail($eventId);
        $data = $request->validate([
            'assignment_id' => 'required|exists:event_assignments,id',
            'template_id' => 'nullable|exists:event_contract_templates,id',
        ]);

        $assignment = EventAssignment::where('event_id', $event->id)->findOrFail($data['assignment_id']);

        try {
            $contract = $contracts->createContract($event, $assignment, $data['template_id'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['contract' => $e->getMessage()]);
        }

        return redirect()->route('events.show', ['id' => $event->id, 'tab' => 'contracts'])
            ->with('message', 'Contract ' . $contract->reference_no . ' created.');
    }

    public function send($eventId, $contractId, EventContractService $contracts)
    {
        $this->can('event_contracts.send');

        $contract = EventContract::with(['event', 'assignment.workerProfile.customer'])
            ->where('event_id', $eventId)
            ->findOrFail($contractId);

        if (! in_array($contract->status, [EventContract::STATUS_DRAFT, EventContract::STATUS_SENT], true)) {
            return back()->withErrors(['contract' => 'Contract cannot be sent in current status.']);
        }

        $contracts->markSent($contract);
        $url = $contracts->signingUrl($contract);
        $profile = $contract->assignment->workerProfile;
        $phone = $profile->telephone ?? optional($profile->customer)->phone_number;

        if ($phone) {
            try {
                $msg = \App\Support\WhatsAppMessage::eventContractSignRequest(
                    optional($profile)->name ?: optional(optional($profile)->customer)->name,
                    $contract->event->name,
                    $url
                );
                $this->sendWhatsAppToPhone($phone, $msg);
            } catch (\Exception $e) {
                Log::warning('Contract send WhatsApp failed: ' . $e->getMessage());
            }
        }

        return back()->with('message', 'Contract sent. Signing link: ' . $url);
    }

    public function review($contractId)
    {
        $this->can('event_contracts.approve');

        $contract = EventContract::with(['event', 'assignment.workerProfile.customer', 'template'])
            ->findOrFail($contractId);

        return view('events.contracts.review', compact('contract'));
    }

    public function approve(Request $request, $contractId, EventContractService $contracts)
    {
        $this->can('event_contracts.approve');

        $contract = EventContract::with('assignment.workerProfile.customer')->findOrFail($contractId);

        if ($contract->status !== EventContract::STATUS_WORKER_SIGNED) {
            return back()->withErrors(['contract' => 'Worker must sign before admin approval.']);
        }

        $request->validate(['admin_signature' => 'nullable|string|max:500000']);
        $contract = $contracts->adminApprove($contract, $request->admin_signature);

        $profile = $contract->assignment->workerProfile;
        $phone = $profile->telephone ?? optional($profile->customer)->phone_number;
        if ($phone && $contract->signed_pdf_path) {
            try {
                $this->sendWhatsAppToPhone($phone, 'Your event contract ' . $contract->reference_no . ' has been approved by Beyond Enterprise.');
                $this->sendWhatsAppDocumentToPhone(
                    $phone,
                    public_path($contract->signed_pdf_path),
                    $contract->reference_no . '.pdf',
                    url($contract->signed_pdf_path)
                );
            } catch (\Exception $e) {
                Log::warning('Contract approval WhatsApp failed: ' . $e->getMessage());
            }
        }

        return redirect()->route('events.show', ['id' => $contract->event_id, 'tab' => 'contracts'])
            ->with('message', 'Contract approved and PDF generated.');
    }

    public function preview($contractId)
    {
        $this->can('event_contracts.view');
        $contract = EventContract::with('event', 'assignment.workerProfile')->findOrFail($contractId);

        return view('events.contracts.preview', compact('contract'));
    }
}
