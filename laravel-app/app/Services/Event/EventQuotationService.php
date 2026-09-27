<?php

namespace App\Services\Event;

use App\Product;
use App\Quotation;
use App\Services\Rental\RentalQuoteService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Bridges event solutions into existing Stage 4 quotation + client review flow.
 */
class EventQuotationService
{
    public function createDraftFromSolution(array $solution, array $slots, array $context)
    {
        $equipment = isset($solution['equipment_lines']) ? $solution['equipment_lines'] : [];
        $lines = [];
        foreach ($equipment as $row) {
            if (empty($row['product_id']) || empty($row['available'])) {
                continue;
            }
            $lines[] = [
                'product_id' => (int) $row['product_id'],
                'quantity' => max(1, (int) (isset($row['requested_qty']) ? $row['requested_qty'] : 1)),
            ];
        }

        // Package commercial amounts that are not physical products become note + shipping/order adjustments via note.
        $commercialNote = [];
        foreach (isset($solution['commercial_lines']) ? $solution['commercial_lines'] : [] as $c) {
            $price = isset($c['price']) ? $c['price'] : null;
            $label = isset($c['label']) ? $c['label'] : $c['key'];
            if (! empty($c['pending_pricing']) || $price === null) {
                $commercialNote[] = $label.': Pending Pricing';
            } else {
                $commercialNote[] = $label.': '.number_format((float) $price, 0).' CFA';
            }
        }
        $slots['event_solution_note'] = implode('; ', $commercialNote);
        if (! empty($solution['event']['type'])) {
            $slots['event_type'] = $solution['event']['type'];
        }
        if (! empty($solution['event']['venue'])) {
            $slots['location'] = $solution['event']['venue'];
        }
        if (! empty($solution['event']['date'])) {
            $slots['event_date'] = $solution['event']['date'];
        }

        if ($lines === []) {
            // Still create a note-only draft via a zero-line failure path is not ideal —
            // require at least one available equipment line OR use package as synthetic note quotation.
            return [
                'success' => false,
                'error' => 'no_available_equipment',
                'message' => 'I could not lock catalogue lines for this package yet. I can still summarize the estimate, or connect you with staff to finalize the quotation.',
                'estimate' => $solution,
            ];
        }

        $result = app(RentalQuoteService::class)->createDraft(array_merge($slots, [
            'proposal_lines' => $lines,
            'use_proposal_lines' => true,
        ]), $context);

        if (! empty($result['success']) && ! empty($result['quotation_id']) && Schema::hasColumn('quotations', 'note')) {
            $q = Quotation::find($result['quotation_id']);
            if ($q && $commercialNote) {
                $q->note = trim(($q->note ? $q->note.' ' : '').'Packages: '.implode(' | ', $commercialNote));
                if (Schema::hasColumn('quotations', 'workflow_state')) {
                    $q->workflow_state = 'ai_draft';
                }
                $q->save();
            }
        }

        return $result;
    }

    public function generateCustomerReview($quotationId, array $context = [])
    {
        $quotation = Quotation::find((int) $quotationId);
        if (! $quotation) {
            return ['success' => false, 'error' => 'not_found'];
        }
        // Reuse staff send path: issues token + STATUS_AWAITING.
        $sent = app(RentalQuoteService::class)->approveAndSend($quotation, isset($context['user_id']) ? $context['user_id'] : null);
        if (empty($sent['success'])) {
            return $sent;
        }
        if (Schema::hasColumn('quotations', 'workflow_state')) {
            $quotation->workflow_state = 'awaiting_client_signature';
            $quotation->save();
        }

        return [
            'success' => true,
            'quotation_id' => $quotation->id,
            'reference' => $quotation->reference_no,
            'approval_url' => isset($sent['approval_url']) ? $sent['approval_url'] : $quotation->fresh()->approvalUrl(),
            'workflow_state' => 'awaiting_client_signature',
            'message' => 'Your quotation has been prepared and sent to you for review. Please check the details and sign/accept it if everything is correct. Once you\'ve completed your review, it will be sent to our team for final approval.',
            'delivered' => true,
        ];
    }

    public function reviewStatus($quotationId)
    {
        $quotation = Quotation::find((int) $quotationId);
        if (! $quotation) {
            return ['success' => false, 'error' => 'not_found'];
        }
        $state = Schema::hasColumn('quotations', 'workflow_state') ? $quotation->workflow_state : null;
        if (! $state) {
            if ($quotation->hasClientSignature() && (int) $quotation->quotation_status === Quotation::STATUS_PENDING) {
                $state = 'customer_accepted';
            } elseif ((int) $quotation->quotation_status === Quotation::STATUS_AWAITING) {
                $state = 'awaiting_client_signature';
            } elseif ((int) $quotation->quotation_status === Quotation::STATUS_APPROVED) {
                $state = 'admin_approved';
            } else {
                $state = 'ai_draft';
            }
        }

        return [
            'success' => true,
            'quotation_id' => $quotation->id,
            'reference' => $quotation->reference_no,
            'quotation_status' => (int) $quotation->quotation_status,
            'status_label' => Quotation::statusLabel($quotation->quotation_status),
            'workflow_state' => $state,
            'client_signed_at' => $quotation->client_signed_at,
            'approval_url' => $quotation->approvalUrl(),
        ];
    }

    public function markCustomerAccepted(Quotation $quotation)
    {
        // Compatible with existing integer statuses: keep PENDING until admin final-approves.
        $quotation->quotation_status = Quotation::STATUS_PENDING;
        $quotation->client_approval_token = null;
        if (Schema::hasColumn('quotations', 'workflow_state')) {
            $quotation->workflow_state = 'customer_accepted';
        }
        $quotation->save();

        return $quotation;
    }

    public function adminFinalApprove(Quotation $quotation, $userId = null)
    {
        $quotation->quotation_status = Quotation::STATUS_APPROVED;
        if (Schema::hasColumn('quotations', 'workflow_state')) {
            $quotation->workflow_state = 'admin_approved';
        }
        if (Schema::hasColumn('quotations', 'approval_sent_by') && $userId) {
            $quotation->approval_sent_by = $userId;
        }
        $quotation->save();

        return $quotation;
    }
}
