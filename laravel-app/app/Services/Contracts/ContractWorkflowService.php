<?php

namespace App\Services\Contracts;

use App\BtwContract;
use App\ContractRevision;
use App\ContractSetting;
use App\ContractSignatory;
use App\SignatureEvent;
use App\SignatureRequest;
use App\Services\Messaging\NotificationRouter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ContractWorkflowService
{
    protected $instances;
    protected $audit;
    protected $pdf;
    protected $notify;

    public function __construct(
        ContractInstanceService $instances,
        ContractAuditService $audit,
        ContractPdfService $pdf,
        NotificationRouter $notify
    ) {
        $this->instances = $instances;
        $this->audit = $audit;
        $this->pdf = $pdf;
        $this->notify = $notify;
    }

    public function markReady(BtwContract $contract)
    {
        $missing = $this->instances->validateForSend($contract);
        if ($missing) {
            throw new \InvalidArgumentException('Unresolved required fields: '.implode(', ', $missing));
        }
        $contract->status = BtwContract::STATUS_READY_TO_SEND;
        $contract->save();
        $this->audit->log($contract->id, 'ready_to_send');

        return $contract;
    }

    public function freezeRevision(BtwContract $contract)
    {
        $revision = $contract->currentRevision;
        if (! $revision) {
            throw new \RuntimeException('No revision to freeze.');
        }
        $html = $this->instances->renderedHtml($contract);
        $revision->content_html = $html; // freeze resolved content
        $revision->checksum = hash('sha256', (string) $html);
        $revision->state = 'frozen';
        $revision->frozen_at = now();
        $revision->save();
        $this->audit->log($contract->id, 'revision_frozen', null, ['revision_id' => $revision->id, 'checksum' => $revision->checksum]);

        return $revision;
    }

    public function sendForSignature(BtwContract $contract, $request = null)
    {
        $missing = $this->instances->validateForSend($contract);
        if ($missing) {
            throw new \InvalidArgumentException('Unresolved required fields: '.implode(', ', $missing));
        }

        return DB::transaction(function () use ($contract, $request) {
            $this->freezeRevision($contract);
            $this->revokeOpenRequests($contract);

            $days = (int) ContractSetting::getValue('default_validity_days', 14);
            $contract->signature_expires_at = now()->addDays(max(1, $days));
            $contract->sent_at = now();
            $contract->status = BtwContract::STATUS_AWAITING_CLIENT;
            $contract->save();

            $minStage = (int) $contract->signatories()->where('status', 'pending')->min('stage');
            $targets = $contract->signatories()
                ->where('status', 'pending')
                ->where('stage', $minStage)
                ->get();

            foreach ($targets as $sig) {
                $this->issueRequest($contract, $sig, $request);
            }

            $this->refreshAwaitingStatus($contract);
            $this->audit->log($contract->id, 'sent_for_signature', null, ['signatories' => $targets->pluck('id')], $request);
            $this->recordEvent($contract, null, 'sent', $request);

            return $contract->fresh(['signatories']);
        });
    }

    public function resend(BtwContract $contract, ContractSignatory $signatory, $request = null)
    {
        $last = SignatureRequest::where('signatory_id', $signatory->id)->orderByDesc('sent_at')->first();
        if ($last && $last->sent_at && $last->sent_at->gt(now()->subMinutes(5))) {
            throw new \RuntimeException('Please wait before resending (rate limited).');
        }
        SignatureRequest::where('signatory_id', $signatory->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $this->issueRequest($contract, $signatory, $request);
        $this->audit->log($contract->id, 'signature_resent', null, ['signatory_id' => $signatory->id], $request);

        return true;
    }

    public function findByToken($plainToken)
    {
        $hash = hash('sha256', $plainToken);
        $req = SignatureRequest::where('token_hash', $hash)->whereNull('revoked_at')->first();
        if (! $req || ! $req->isActive()) {
            return null;
        }

        return $req;
    }

    public function markOpened(SignatureRequest $req, $request = null)
    {
        if (! $req->opened_at) {
            $req->opened_at = now();
            $req->save();
        }
        $sig = $req->signatory;
        if ($sig) {
            $this->recordEvent($sig->contract_id ? \App\BtwContract::find($sig->contract_id) : null, $sig, 'opened', $request);
        }
    }

    public function sign(SignatureRequest $req, array $payload, $request = null)
    {
        $sig = $req->signatory;
        if (! $sig || $sig->status === 'signed') {
            throw new \RuntimeException('Invalid signature request.');
        }
        $contract = BtwContract::findOrFail($sig->contract_id);
        $revision = $contract->currentRevision;
        if (! $revision || ! $revision->isFrozen()) {
            throw new \RuntimeException('Contract revision is not frozen.');
        }
        if ($req->expires_at && $req->expires_at->isPast()) {
            throw new \RuntimeException('Signature link expired.');
        }

        return DB::transaction(function () use ($req, $sig, $contract, $payload, $request) {
            $sig->status = 'signed';
            $sig->signed_at = now();
            $sig->typed_name = $payload['typed_name'] ?? $sig->display_name;
            $sig->signature_image = $payload['signature_image'] ?? null;
            $sig->ip_address = $request ? $request->ip() : null;
            $sig->user_agent = $request ? substr((string) $request->userAgent(), 0, 500) : null;
            $sig->save();

            $req->revoked_at = now();
            $req->save();

            $this->recordEvent($contract, $sig, 'signed', $request, [
                'typed_name' => $sig->typed_name,
                'consent' => ! empty($payload['consent']),
                'revision_checksum' => optional($contract->currentRevision)->checksum,
            ]);
            $this->audit->log($contract->id, 'signed', null, ['signatory_id' => $sig->id, 'role' => $sig->role], $request);

            $this->advanceWorkflow($contract, $request);

            return $contract->fresh(['signatories', 'documents']);
        });
    }

    public function decline(SignatureRequest $req, $reason, $request = null)
    {
        $sig = $req->signatory;
        $contract = BtwContract::findOrFail($sig->contract_id);
        $sig->status = 'declined';
        $sig->declined_reason = $reason;
        $sig->save();
        $req->revoked_at = now();
        $req->save();
        $contract->status = BtwContract::STATUS_DECLINED;
        $contract->save();
        $this->revokeOpenRequests($contract);
        $this->recordEvent($contract, $sig, 'declined', $request, ['reason' => $reason]);
        $this->audit->log($contract->id, 'declined', null, ['reason' => $reason], $request);

        return $contract;
    }

    public function cancel(BtwContract $contract, $reason = null, $request = null)
    {
        if ($contract->isSigned()) {
            throw new \RuntimeException('Signed contracts cannot be cancelled; use supersede.');
        }
        $contract->status = BtwContract::STATUS_CANCELLED;
        $contract->cancelled_at = now();
        $contract->save();
        $this->revokeOpenRequests($contract);
        $this->audit->log($contract->id, 'cancelled', null, ['reason' => $reason], $request);

        return $contract;
    }

    public function supersede(BtwContract $contract, $request = null)
    {
        if (! $contract->isSigned()) {
            throw new \RuntimeException('Only signed contracts can be superseded.');
        }
        $template = $contract->template;
        if (! $template || ! $template->currentVersion) {
            throw new \RuntimeException('Template unavailable for supersede.');
        }

        $new = $this->instances->createFromTemplate($template, [
            'title' => $contract->title.' (Amendment)',
            'effective_date' => now()->toDateString(),
            'start_date' => optional($contract->start_date)->toDateString(),
            'end_date' => optional($contract->end_date)->toDateString(),
            'value' => $contract->value,
            'currency' => $contract->currency,
            'jurisdiction' => $contract->jurisdiction,
            'purpose' => $contract->purpose,
            'payment_schedule' => $contract->payment_schedule,
            'party_a_role' => optional($contract->partyA)->role_label,
            'party_b_role' => optional($contract->partyB)->role_label,
            'party_a' => $contract->partyA ? $contract->partyA->snapshot() + ['subject_type' => $contract->partyA->subject_type, 'subject_id' => $contract->partyA->subject_id] : [],
            'party_b' => $contract->partyB ? $contract->partyB->snapshot() + ['subject_type' => $contract->partyB->subject_type, 'subject_id' => $contract->partyB->subject_id] : [],
            'link_type' => $contract->primary_link_type,
            'link_id' => $contract->primary_link_id,
        ]);
        $new->supersedes = $contract->id;
        $new->save();
        $contract->status = BtwContract::STATUS_SUPERSEDED;
        $contract->superseded_by = $new->id;
        $contract->save();
        $this->audit->log($contract->id, 'superseded', null, ['new_contract_id' => $new->id], $request);
        $this->audit->log($new->id, 'created_as_supersede', null, ['supersedes' => $contract->id], $request);

        return $new;
    }

    public function materialEditCreatesRevision(BtwContract $contract, array $input)
    {
        if (in_array($contract->status, [BtwContract::STATUS_DRAFT, BtwContract::STATUS_IN_REVIEW], true)) {
            return $this->instances->updateDraft($contract, $input);
        }

        // After send: invalidate tokens and create new draft revision
        $this->revokeOpenRequests($contract);
        $old = $contract->currentRevision;
        if ($old && $old->isFrozen()) {
            $old->state = 'superseded';
            $old->save();
        }
        $nextNo = ((int) $contract->revisions()->max('revision_no')) + 1;
        $content = $input['content_html'] ?? ($old ? $old->content_html : '');
        $revision = ContractRevision::create([
            'id' => (string) Str::uuid(),
            'contract_id' => $contract->id,
            'revision_no' => $nextNo,
            'content_html' => $content,
            'checksum' => hash('sha256', (string) $content),
            'state' => 'draft',
            'created_by' => Auth::id(),
        ]);
        $contract->current_revision_id = $revision->id;
        $contract->status = BtwContract::STATUS_DRAFT;
        $contract->sent_at = null;
        $contract->save();
        $contract->signatories()->update([
            'status' => 'pending',
            'signed_at' => null,
            'signature_image' => null,
            'typed_name' => null,
            'revision_id' => $revision->id,
        ]);
        $this->instances->updateDraft($contract, $input);
        $this->audit->log($contract->id, 'new_revision_after_edit', null, ['revision_no' => $nextNo]);

        return $contract->fresh(['currentRevision']);
    }

    protected function advanceWorkflow(BtwContract $contract, $request = null)
    {
        $pending = $contract->signatories()->where('required', true)->where('status', 'pending')->get();
        if ($pending->isEmpty()) {
            $contract->status = BtwContract::STATUS_SIGNED;
            $contract->signed_at = now();
            $contract->save();
            try {
                $this->pdf->generateFinal($contract);
            } catch (\Throwable $e) {
                \Log::error('[contracts] final PDF failed: '.$e->getMessage());
            }
            $this->audit->log($contract->id, 'fully_signed', null, null, $request);
            $this->recordEvent($contract, null, 'completed', $request);

            return;
        }

        $minStage = (int) $pending->min('stage');
        $toInvite = $pending->where('stage', $minStage);
        foreach ($toInvite as $sig) {
            $hasOpen = SignatureRequest::where('signatory_id', $sig->id)->whereNull('revoked_at')->exists();
            if (! $hasOpen) {
                $this->issueRequest($contract, $sig, $request);
            }
        }
        $this->refreshAwaitingStatus($contract);
    }

    protected function refreshAwaitingStatus(BtwContract $contract)
    {
        $pending = $contract->signatories()->where('required', true)->where('status', 'pending')->get();
        if ($pending->isEmpty()) {
            return;
        }
        $roles = $pending->pluck('role')->all();
        $hasClient = (bool) array_intersect($roles, ['party_a', 'party_b', 'witness_a', 'witness_b']);
        $hasAdmin = in_array('admin', $roles, true);
        if ($hasClient) {
            $contract->status = BtwContract::STATUS_AWAITING_CLIENT;
        } elseif ($hasAdmin) {
            $contract->status = BtwContract::STATUS_AWAITING_ADMIN;
        }
        $contract->save();
    }

    protected function issueRequest(BtwContract $contract, ContractSignatory $sig, $request = null)
    {
        $plain = Str::random(48);
        $days = (int) ContractSetting::getValue('default_validity_days', 14);
        SignatureRequest::create([
            'id' => (string) Str::uuid(),
            'signatory_id' => $sig->id,
            'token_hash' => hash('sha256', $plain),
            'channel' => 'portal',
            'sent_at' => now(),
            'expires_at' => now()->addDays(max(1, $days)),
            'attempts' => 1,
        ]);

        $url = url('/contracts/sign/'.$plain);
        $msg = "Beyond Enterprise: Please review and sign contract {$contract->number} ({$contract->title}). Open: {$url}";

        if ($sig->phone) {
            try {
                $this->notify->sendWhatsAppText($sig->phone, $msg);
            } catch (\Throwable $e) {
                \Log::warning('[contracts] WhatsApp notify failed: '.$e->getMessage());
            }
        }
        if ($sig->email) {
            try {
                Mail::raw($msg, function ($m) use ($sig, $contract) {
                    $m->to($sig->email)->subject('Sign contract '.$contract->number);
                });
            } catch (\Throwable $e) {
                \Log::warning('[contracts] Email notify failed: '.$e->getMessage());
            }
        }

        // Admin signatories without contact: leave link for admin UI
        $sig->setAttribute('last_sign_url', $url);
        $this->recordEvent($contract, $sig, 'invite_sent', $request, ['url' => $url]);
    }

    protected function revokeOpenRequests(BtwContract $contract)
    {
        $ids = $contract->signatories()->pluck('id');
        SignatureRequest::whereIn('signatory_id', $ids)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $contract->signatories()->where('status', 'pending')->update(['status' => 'pending']);
        // reset only non-signed for re-send flow is handled by caller
    }

    protected function recordEvent($contract, $sig, $type, $request = null, array $meta = [])
    {
        if (! $contract) {
            return;
        }
        try {
            SignatureEvent::create([
                'id' => (string) Str::uuid(),
                'contract_id' => $contract->id,
                'revision_id' => $contract->current_revision_id,
                'signatory_id' => $sig ? $sig->id : null,
                'event_type' => $type,
                'event_at' => now(),
                'actor_type' => Auth::check() ? 'user' : 'signer',
                'actor_id' => Auth::check() ? (string) Auth::id() : ($sig ? $sig->id : null),
                'ip_address' => $request ? $request->ip() : null,
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 500) : null,
                'metadata_json' => $meta ? json_encode($meta) : null,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('[contracts] event log: '.$e->getMessage());
        }
    }
}
