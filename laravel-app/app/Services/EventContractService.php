<?php

namespace App\Services;

use App\Event;
use App\EventAssignment;
use App\EventContract;
use App\EventContractTemplate;
use App\GeneralSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDF;

class EventContractService
{
    public function renderTemplate(EventContractTemplate $template, Event $event, EventAssignment $assignment)
    {
        $profile = $assignment->workerProfile;
        $gs = GeneralSetting::first();
        $company = $gs->site_title ?? 'Beyond Enterprise';

        $replacements = [
            '{{company_name}}' => e($company),
            '{{event_name}}' => e($event->name),
            '{{event_reference}}' => e($event->reference_no),
            '{{worker_name}}' => e($profile ? $profile->displayName() : 'Worker'),
            '{{role}}' => e($assignment->assignment_role),
            '{{venue}}' => e($event->venue . ($event->city ? ', ' . $event->city : '')),
            '{{event_start}}' => $event->event_start_at ? $event->event_start_at->format('d M Y H:i') : 'TBD',
            '{{event_end}}' => $event->event_end_at ? $event->event_end_at->format('d M Y H:i') : 'TBD',
            '{{daily_rate}}' => number_format($assignment->event_daily_rate ?: $assignment->default_daily_rate ?: 0),
            '{{expected_days}}' => (string) ($assignment->expected_days ?: 1),
            '{{total_amount}}' => number_format($assignment->expected_total ?: 0),
        ];

        $header = str_replace(array_keys($replacements), array_values($replacements), $template->header ?: '');
        $body = str_replace(array_keys($replacements), array_values($replacements), $template->body);
        $footer = str_replace(array_keys($replacements), array_values($replacements), $template->footer ?: '');

        return compact('header', 'body', 'footer');
    }

    public function createContract(Event $event, EventAssignment $assignment, $templateId = null)
    {
        if ($assignment->contract()->whereNotIn('status', [EventContract::STATUS_CANCELLED])->exists()) {
            throw new \InvalidArgumentException('This assignment already has an active contract.');
        }

        $template = $templateId
            ? EventContractTemplate::findOrFail($templateId)
            : EventContractTemplate::where('is_active', true)->firstOrFail();

        $rendered = $this->renderTemplate($template, $event, $assignment);
        $html = ($rendered['header'] ? '<div class="header">' . $rendered['header'] . '</div>' : '')
            . $rendered['body']
            . ($rendered['footer'] ? '<div class="footer"><hr>' . $rendered['footer'] . '</div>' : '');

        $contract = EventContract::create([
            'event_id' => $event->id,
            'assignment_id' => $assignment->id,
            'template_id' => $template->id,
            'reference_no' => $this->nextReferenceNo(),
            'title' => $template->name . ' — ' . $assignment->assignment_role,
            'rendered_body' => $html,
            'status' => EventContract::STATUS_DRAFT,
            'created_by' => Auth::id(),
        ]);

        $assignment->update(['contract_status' => 'draft']);

        return $contract;
    }

    public function markSent(EventContract $contract)
    {
        $contract->update([
            'status' => EventContract::STATUS_SENT,
            'sent_at' => now(),
        ]);
        $contract->assignment->update(['contract_status' => 'sent']);

        return $contract->fresh();
    }

    public function workerSign(EventContract $contract, $signatureImage)
    {
        $contract->update([
            'status' => EventContract::STATUS_WORKER_SIGNED,
            'worker_signed_at' => now(),
            'worker_signature' => $signatureImage,
        ]);
        $contract->assignment->update(['contract_status' => 'worker_signed']);

        return $contract->fresh();
    }

    public function adminApprove(EventContract $contract, $adminSignature = null)
    {
        $contract->update([
            'status' => EventContract::STATUS_APPROVED,
            'admin_signed_at' => now(),
            'admin_signed_by' => Auth::id(),
            'admin_signature' => $adminSignature,
            'approved_at' => now(),
        ]);
        $contract->assignment->update(['contract_status' => 'approved']);

        $pdfPath = $this->generatePdf($contract);
        $contract->update(['signed_pdf_path' => $pdfPath]);

        return $contract->fresh();
    }

    public function generatePdf(EventContract $contract)
    {
        $contract->load(['event', 'assignment.workerProfile.customer', 'adminSigner']);
        $gs = GeneralSetting::first();

        $directory = public_path('event_contracts/signed');
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $workerSigPath = $this->persistSignature($contract->worker_signature, $contract->id, 'worker');
        $adminSigPath = $this->persistSignature($contract->admin_signature, $contract->id, 'admin');

        $pdf = PDF::loadView('pdf.event_contract_signed', [
            'contract' => $contract,
            'general_setting' => $gs,
            'workerSigPath' => $workerSigPath,
            'adminSigPath' => $adminSigPath,
        ])->setPaper('a4');

        $filename = 'event_contracts/signed/' . $contract->reference_no . '.pdf';
        $pdf->save(public_path($filename));

        return $filename;
    }

    protected function persistSignature($data, $contractId, $role)
    {
        if (! $data || strpos($data, 'data:image') !== 0) {
            return null;
        }
        $parts = explode(',', $data, 2);
        $binary = base64_decode($parts[1] ?? '');
        if (! $binary) {
            return null;
        }
        $dir = public_path('event_contracts/signatures');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        $path = $dir . '/' . $contractId . '_' . $role . '.png';
        file_put_contents($path, $binary);

        return $path;
    }

    public function nextReferenceNo()
    {
        $year = date('Y');
        $prefix = 'EC/' . $year . '/';
        $last = EventContract::where('reference_no', 'like', $prefix . '%')
            ->orderBy('reference_no', 'desc')
            ->value('reference_no');
        $seq = 1;
        if ($last && preg_match('/(\d+)$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function signingUrl(EventContract $contract)
    {
        return url('/event-contract/' . $contract->signature_token);
    }
}
