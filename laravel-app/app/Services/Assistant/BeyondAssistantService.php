<?php

namespace App\Services\Assistant;

use App\Assistant\AssistantActivity;
use App\Assistant\IntentCatalog;
use App\Services\Rental\RentalAvailabilityService;
use App\Services\WhatsApp\WhatsAppConversationService;
use App\WhatsApp\WhatsAppMessage;
use Illuminate\Support\Facades\Schema;

class BeyondAssistantService
{
    protected $policy;
    protected $router;
    protected $context;
    protected $memory;
    protected $tools;
    protected $composer;
    protected $handover;
    protected $conversations;

    public function __construct(
        AssistantPolicyService $policy,
        AssistantIntentRouter $router,
        AssistantContextBuilder $context,
        AssistantConversationMemory $memory,
        AssistantToolExecutor $tools,
        AssistantResponseComposer $composer,
        AssistantHandoverService $handover,
        WhatsAppConversationService $conversations
    ) {
        $this->policy = $policy;
        $this->router = $router;
        $this->context = $context;
        $this->memory = $memory;
        $this->tools = $tools;
        $this->composer = $composer;
        $this->handover = $handover;
        $this->conversations = $conversations;
    }

    public function handleIncoming(WhatsAppMessage $message)
    {
        $started = microtime(true);
        if (! Schema::hasTable('assistant_activities')) {
            return ['skipped' => true, 'reason' => 'not_installed'];
        }
        $conversation = $message->conversation ?: $message->conversation()->first();
        if (! $conversation) {
            return ['skipped' => true, 'reason' => 'no_conversation'];
        }
        $fingerprint = $this->fingerprint($message);
        $existing = $this->existingActivity($fingerprint);
        if ($existing && in_array($existing->status, [AssistantActivity::COMPLETED, AssistantActivity::HANDED_OVER, AssistantActivity::SKIPPED], true)) {
            return ['skipped' => true, 'reason' => 'duplicate'];
        }
        $activity = $existing ?: $this->startActivity($conversation->id, $message->id, $fingerprint);
        list($allowed, $reason) = $this->policy->mayProcess($conversation);
        if (! $allowed) {
            $activity->status = AssistantActivity::SKIPPED;
            $activity->error = $reason;
            $activity->save();

            return ['skipped' => true, 'reason' => $reason];
        }
        if ($this->rateLimited($conversation)) {
            $activity->status = AssistantActivity::SKIPPED;
            $activity->error = 'rate_limited';
            $activity->save();

            return ['skipped' => true, 'reason' => 'rate_limited'];
        }

        $context = $this->context->build($conversation);
        $mem = $this->memory->forConversation($conversation->id);
        $slots = $this->extractSlots((string) $message->body, $mem->parameters());
        $classified = $this->router->classify((string) $message->body, $context, $slots);
        $slots = array_merge($slots, isset($classified['slots']) ? $classified['slots'] : []);
        $decision = $this->policy->decide($classified, $context['roles']);
        $this->memory->remember($mem, $decision['intent'], $slots);
        if ($decision['intent'] === IntentCatalog::RENTAL_REVISION && ! empty($slots['quotation_id'])) {
            $slots['revised_from_id'] = $slots['quotation_id'];
        }
        try {
            app(\App\Services\Rental\RentalRequestService::class)->capture($conversation, $slots, (string) $message->body, $decision['intent']);
        } catch (\Throwable $e) {
            $activity->error = $e->getMessage();
        }

        $activity->intent = $decision['intent'];
        $activity->confidence = $decision['confidence'];
        $activity->action = $decision['action'];
        $activity->provider = config('assistant.provider');
        $activity->model = config('assistant.model');

        $toolResult = null;
        $toolsRun = [];
        if ($decision['action'] === IntentCatalog::ACTION_HANDOVER) {
            $this->handover->toHuman($conversation, $decision['reason'] ?: 'handover');
            $activity->handover_reason = $decision['reason'];
            $activity->status = AssistantActivity::HANDED_OVER;
        } elseif ($decision['action'] === IntentCatalog::ACTION_CLARIFY) {
            $this->memory->bumpClarification($mem);
            if ((int) $mem->clarification_count > (int) config('assistant.max_clarifications')) {
                $this->handover->toHuman($conversation, 'too_many_clarifications');
                $decision['action'] = IntentCatalog::ACTION_HANDOVER;
                $activity->handover_reason = 'too_many_clarifications';
                $activity->status = AssistantActivity::HANDED_OVER;
            }
        } elseif ($decision['action'] === IntentCatalog::ACTION_TOOL) {
            $tool = $this->toolFor($decision['intent'], $slots);
            if ($tool) {
                $activity->tools_requested = $tool;
                try {
                    $toolResult = $this->tools->execute($tool, $this->toolParams($tool, $slots, $context), $context);
                } catch (\Throwable $e) {
                    $toolResult = ['success' => false, 'error' => 'tool_exception'];
                    $activity->error = $e->getMessage();
                }
                $toolsRun[] = $tool;
                $this->memory->storeTool($mem, $tool, is_array($toolResult) ? $toolResult : []);
                if (is_array($toolResult) && ! empty($toolResult['quotation_id'])) {
                    $slots['quotation_id'] = $toolResult['quotation_id'];
                    $slots['quotation_reference'] = isset($toolResult['reference']) ? $toolResult['reference'] : null;
                    $this->memory->remember($mem, $decision['intent'], $slots);
                    $open = app(\App\Services\Rental\RentalRequestService::class)->active($conversation);
                    if ($open) {
                        app(\App\Services\Rental\RentalRequestService::class)->markStaffReview($open, $toolResult['quotation_id']);
                    }
                }
                $activity->tools_executed = implode(',', $toolsRun);
                $activity->tool_status = ! empty($toolResult['success']) ? 'ok' : (isset($toolResult['error']) ? $toolResult['error'] : 'failed');
                if (empty($toolResult['success']) && in_array($toolResult['error'] ?? '', ['not_found', 'unknown_tool', 'unimplemented_tool'], true)
                    && in_array($decision['intent'], [IntentCatalog::BOOKING_STATUS, IntentCatalog::INTERNSHIP_TASK], true)) {
                    $this->handover->toHuman($conversation, 'tool_failed');
                    $decision['action'] = IntentCatalog::ACTION_HANDOVER;
                    $activity->handover_reason = 'tool_failed';
                    $activity->status = AssistantActivity::HANDED_OVER;
                }
            }
        }

        $reply = $this->composer->compose($decision['intent'], $decision['action'], $toolResult ?: [], $context, (string) $message->body, $mem->parameters());
        $sent = false;
        $conversation = $conversation->fresh();
        if ($conversation->mode === \App\WhatsApp\WhatsAppConversation::MODE_AI || $decision['action'] === IntentCatalog::ACTION_HANDOVER) {
            $send = $this->conversations->assistantReply($conversation, $reply);
            $sent = ! empty($send['success']);
            if (! $sent) {
                $activity->error = isset($send['error']) ? $send['error'] : 'send_failed';
            }
        }
        if ($decision['intent'] === IntentCatalog::DISCOUNT_REQUEST) {
            $this->handover->toHuman($conversation, 'discount_request');
            $activity->handover_reason = 'discount_request';
            $activity->status = AssistantActivity::HANDED_OVER;
        }
        $activity->response_preview = mb_substr($reply, 0, 240);
        $activity->sent = $sent;
        $activity->duration_ms = (int) ((microtime(true) - $started) * 1000);
        if ($activity->status === AssistantActivity::STARTED) {
            $activity->status = $sent ? AssistantActivity::COMPLETED : AssistantActivity::FAILED;
        }
        $activity->save();

        return ['skipped' => false, 'sent' => $sent, 'intent' => $decision['intent'], 'action' => $decision['action'], 'reply' => $reply];
    }

    public function suggest(\App\WhatsApp\WhatsAppConversation $conversation)
    {
        $context = $this->context->build($conversation);
        $last = WhatsAppMessage::where('conversation_id', $conversation->id)->where('direction', WhatsAppMessage::DIR_IN)->orderByDesc('id')->first();
        $incoming = $last ? (string) $last->body : '';
        $classified = $this->router->classify($incoming, $context, []);
        $toolResult = [];
        $decision = $this->policy->decide($classified, $context['roles']);
        if ($decision['action'] === IntentCatalog::ACTION_TOOL) {
            $tool = $this->toolFor($decision['intent'], []);
            if ($tool) {
                $toolResult = $this->tools->execute($tool, $this->toolParams($tool, [], $context), $context);
            }
        }
        $draft = $this->composer->draft($incoming, $context, $toolResult);
        $mem = $this->memory->forConversation($conversation->id);
        $this->memory->storeSuggestion($mem, $draft);

        return $draft;
    }

    protected function toolFor($intent, array $slots)
    {
        $map = [
            IntentCatalog::COMPANY_INFORMATION => 'get_company_information',
            IntentCatalog::SERVICE_ENQUIRY => 'get_services',
            IntentCatalog::GENERAL_ENQUIRY => 'get_services',
            IntentCatalog::RENTAL_ENQUIRY => $this->rentalTool($slots),
            IntentCatalog::EQUIPMENT_AVAILABILITY => $this->rentalTool($slots),
            IntentCatalog::PRICE_ENQUIRY => $this->rentalTool($slots),
            IntentCatalog::RENTAL_QUOTE => 'create_rental_quotation',
            IntentCatalog::RENTAL_CONFIRM => 'create_rental_quotation',
            IntentCatalog::RENTAL_REVISION => 'create_rental_quotation',
            IntentCatalog::RENTAL_ACCEPT => 'request_rental_booking',
            IntentCatalog::BOOKING_STATUS => 'get_booking_status',
            IntentCatalog::QUOTATION_REQUEST => 'get_customer_quotations',
            IntentCatalog::INTERNSHIP_TASK => 'get_current_internship_task',
            IntentCatalog::INTERNSHIP_STATUS => 'get_internship_progress',
            IntentCatalog::INTERNSHIP_ENQUIRY => 'get_internship_summary',
            IntentCatalog::DOCUMENT_REQUEST => 'list_available_documents',
            IntentCatalog::HUMAN_REQUEST => 'request_human_handover',
        ];

        return isset($map[$intent]) ? $map[$intent] : null;
    }

    protected function toolParams($tool, array $slots, array $context)
    {
        $params = $slots;
        if ($tool === 'search_rental_products') {
            $params['query'] = isset($slots['product']) ? $slots['product'] : (isset($slots['query']) ? $slots['query'] : '');
            if ($params['query'] === '' && ! empty($context['history'])) {
                $last = end($context['history']);
                $params['query'] = isset($last['body']) ? $last['body'] : '';
            }
        }

        return $params;
    }

    protected function extractSlots($text, array $existing)
    {
        $slots = $existing;
        $dated = preg_replace('/\b(\d{1,2})(st|nd|rd|th)\b/i', '$1', $text);
        $dated = preg_replace('/\b(\d{1,2})\s+of\s+/i', '$1 ', $dated);
        if (preg_match('/\b(wedding|concert|church|conference|birthday|funeral)\b/i', $text, $m)) {
            $slots['event_type'] = strtolower($m[1]);
        }
        if (preg_match('/\b(this weekend|next weekend|next month)\b/i', $dated, $m)) {
            $slots['event_date'] = strtolower($m[1]);
        } elseif (preg_match('/\bnext\s+(saturday|sunday|monday|tuesday|wednesday|thursday|friday)\b/i', $dated, $m)) {
            $slots['event_date'] = strtolower($m[0]);
        } elseif (preg_match('/\b(saturday|sunday|monday|tuesday|wednesday|thursday|friday|tomorrow)\b/i', $dated, $m)) {
            $slots['event_date'] = strtolower($m[1]);
        }
        if (preg_match('/\b(\d{2,4})\s*(guests|people|pax)\b/i', $text, $m) && (int) $m[1] >= 20) {
            $slots['guests'] = (int) $m[1];
        }
        if (preg_match('/\b(\d{1,2}\s+(?:of\s+)?(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*|(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\s+\d{1,2}|\d{4}-\d{2}-\d{2}|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})\b/i', $dated, $m)) {
            $slots['event_date'] = strtolower(preg_replace('/\s+of\s+/', ' ', $m[1]));
        }
        if (preg_match('/\bin\s+([A-Za-z][A-Za-z]{2,30})\b/', $text, $m)) {
            $slots['location'] = $m[1];
        }
        if (preg_match('/\b(\d{1,3}|one|two|three|four|five|six|eight|ten|twelve)\s+(moving heads?|par lights?|speakers?|led screens?|microphones?|jbl(?:\s+speakers?)?)\b/i', $text, $m)) {
            $slots['qty'] = $this->qtyWord($m[1]);
            $slots['product'] = strtolower($m[2]);
            $slots['query'] = $slots['product'];
        } elseif (preg_match('/\b(jbl|yamaha|speaker|speakers|led|lighting|sound|microphone|microphones)\b/i', $text, $m)) {
            $slots['product'] = strtolower($m[1]);
            $slots['query'] = strtolower($m[1]);
        }
        if (preg_match('/\bjbl\s+charge(?:\s*\d+)?(?:\s+bluetooth)?(?:\s+speakers?)?/i', $text, $m)) {
            $slots['product'] = strtolower(trim($m[0]));
            $slots['query'] = $slots['product'];
        }

        return $slots;
    }

    protected function rentalTool(array $slots)
    {
        return app(RentalAvailabilityService::class)->resolveRange($slots) ? 'check_rental_availability' : 'search_rental_products';
    }

    protected function qtyWord($value)
    {
        $words = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'eight' => 8, 'ten' => 10, 'twelve' => 12];
        $key = strtolower((string) $value);

        return isset($words[$key]) ? $words[$key] : max(1, (int) $value);
    }

    protected function sendQuotePdf($conversation, array $toolResult)
    {
        try {
            $path = app(\App\Http\Controllers\QuotationController::class)->buildQuotationPdf($toolResult['quotation_id']);
            $name = 'quotation_'.$toolResult['reference'].'.pdf';
            $send = $this->conversations->sendExistingDocument(
                $conversation,
                $path,
                $name,
                'Draft quotation '.$toolResult['reference'].'. Staff will review it before it is final.',
                null,
                'ASSISTANT'
            );

            return ! empty($send['success']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function fingerprint(WhatsAppMessage $message)
    {
        return hash('sha256', $message->id.'|'.$message->provider_message_id.'|'.$message->conversation_id);
    }

    protected function existingActivity($fingerprint)
    {
        if (! Schema::hasTable('assistant_activities')) {
            return null;
        }

        return AssistantActivity::where('incoming_fingerprint', $fingerprint)->first();
    }

    protected function startActivity($conversationId, $messageId, $fingerprint)
    {
        return AssistantActivity::create([
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'incoming_fingerprint' => $fingerprint,
            'status' => AssistantActivity::STARTED,
        ]);
    }

    protected function rateLimited($conversation)
    {
        $max = (int) config('assistant.max_replies_per_contact_hour');
        $count = WhatsAppMessage::where('conversation_id', $conversation->id)
            ->where('sender_type', 'ASSISTANT')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        return $count >= $max;
    }
}
