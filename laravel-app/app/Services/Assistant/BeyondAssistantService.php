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
        $ownerUser = $conversation->contact
            ? app(\App\Services\WhatsApp\OwnerAuthorizationService::class)->authorizePhone($conversation->contact->normalized_phone)
            : null;
        if ($ownerUser) {
            $answer = app(\App\Services\WhatsApp\OwnerCommandService::class)->handle($conversation, (string) $message->body, $ownerUser);
            if (is_string($answer)) {
                $this->conversations->ownerNotice($conversation, $answer);

                return ['skipped' => false, 'sent' => true, 'intent' => 'OWNER', 'reply' => $answer];
            }
            if ($conversation->mode !== \App\WhatsApp\WhatsAppConversation::MODE_AI) {
                $conversation->mode = \App\WhatsApp\WhatsAppConversation::MODE_AI;
                $conversation->assigned_user_id = null;
                $conversation->save();
            }
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
        $slots['text'] = (string) $message->body;
        $media = $message->media();
        if ($media) {
            $slots['media'] = $media;
            $slots['provider_message_id'] = $message->provider_message_id;
            if (isset($media['latitude'])) {
                $slots['latitude'] = $media['latitude'];
                $slots['longitude'] = isset($media['longitude']) ? $media['longitude'] : null;
                $slots['location_at'] = now()->toDateTimeString();
            }
        }
        $slots['provider_message_id'] = $message->provider_message_id;
        $slots['conversation_id'] = $conversation->id;
        $slots['roles'] = isset($context['roles']) ? $context['roles'] : [];
        try {
            app(AssistantLeadCapture::class)->apply($conversation, $slots, (string) $message->body);
        } catch (\Throwable $e) {
            $activity->error = $e->getMessage();
        }
        $choiceText = strtolower((string) $slots['text']);
        if (strpos($choiceText, 'intern') !== false) {
            $slots['context'] = 'intern';
        } elseif (preg_match('/\b(employee|staff|office)\b/', $choiceText)) {
            $slots['context'] = 'employee';
        } elseif (! empty($slots['tenant_choice_pending']) && preg_match('/\b(tenant|rent)\b/', $choiceText)) {
            $slots['context'] = 'tenant';
        }
        $directReply = null;
        $conversationalTool = null;
        $conversationalResult = null;
        $justNamed = ! empty($slots['captured_name']) && ! empty($mem->parameters()['awaiting_name']);
        $deterministic = $justNamed ? null : $this->router->deterministic((string) $message->body, $slots);
        if (! $justNamed) {
            $appointmentReply = app(\App\Services\Appointment\AppointmentConversationService::class)
                ->handle($conversation, $context, $mem, (string) $message->body, $deterministic);
            if (is_string($appointmentReply)) {
                $directReply = $appointmentReply;
                $classified = [
                    'intent' => IntentCatalog::APPOINTMENT_REQUEST,
                    'confidence' => 0.95,
                    'requires_erp' => false,
                    'needs_clarification' => false,
                    'slots' => [],
                ];
                $fresh = $mem->parameters();
                $slots = array_merge($slots, $fresh);
                if (! isset($fresh['appointment_draft'])) {
                    unset($slots['appointment_draft']);
                }
            }
        }
        if ($justNamed) {
            $directReply = 'Thanks '.$slots['captured_name'].'. Are you arranging this for yourself or for an organization?';
            $classified = [
                'intent' => IntentCatalog::GREETING,
                'confidence' => 0.97,
                'requires_erp' => false,
                'needs_clarification' => false,
                'slots' => [],
            ];
        }
        $high = (float) config('assistant.confidence_high');
        if (! isset($classified) && (! $deterministic || $deterministic['confidence'] < $high)) {
            $turn = app(ConversationalTurnService::class)->turn($conversation, $context, $mem, $slots, (string) $message->body);
            if (! empty($turn['handled'])) {
                $directReply = $turn['reply'];
                $conversationalTool = isset($turn['tool']) ? $turn['tool'] : null;
                $conversationalResult = isset($turn['tool_result']) ? $turn['tool_result'] : null;
                if (! empty($turn['memory'])) {
                    $slots = array_merge($slots, $turn['memory']);
                }
                if (! empty($turn['handover'])) {
                    $classified = [
                        'intent' => ! empty($turn['call_request']) ? IntentCatalog::CALL_REQUEST : IntentCatalog::HUMAN_REQUEST,
                        'confidence' => 0.99,
                        'requires_erp' => false,
                        'needs_clarification' => false,
                        'slots' => [],
                    ];
                    if (! empty($turn['reason'])) {
                        $classified['reason'] = $turn['reason'];
                    }
                } else {
                    $classified = [
                        'intent' => IntentCatalog::GENERAL_ENQUIRY,
                        'confidence' => 0.95,
                        'requires_erp' => false,
                        'needs_clarification' => ! empty($turn['clarify']),
                        'slots' => [],
                    ];
                }
            }
        }
        if (! isset($classified) && ! empty($slots['organization'])) {
            $directReply = 'Thanks, I have noted '.$slots['organization'].'. What do you need for the event?';
            $classified = [
                'intent' => IntentCatalog::GENERAL_ENQUIRY,
                'confidence' => 0.95,
                'requires_erp' => false,
                'needs_clarification' => false,
                'slots' => [],
            ];
        }
        if (! isset($classified)) {
            $classified = $this->router->classify((string) $message->body, $context, $slots);
        }
        $slots = array_merge($slots, isset($classified['slots']) ? $classified['slots'] : []);
        $decision = $this->policy->decide($classified, $context['roles']);
        if (! empty($classified['reason']) && $decision['action'] === IntentCatalog::ACTION_HANDOVER) {
            $decision['reason'] = $classified['reason'];
        }
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
        if ($conversationalResult) {
            $toolResult = $conversationalResult;
            if ($conversationalTool) {
                $toolsRun[] = $conversationalTool;
                $activity->tools_requested = $conversationalTool;
                $activity->tools_executed = $conversationalTool;
                $activity->tool_status = ! empty($conversationalResult['success']) ? 'ok' : 'failed';
            }
        }
        $otpCode = null;
        $otpTtl = 5;
        if ($decision['action'] === IntentCatalog::ACTION_HANDOVER) {
            if ($decision['intent'] === IntentCatalog::CALL_REQUEST) {
                app(\App\Services\WhatsApp\WhatsAppCallRequestService::class)->open($conversation, (string) $message->body);
            }
            $activity->handover_reason = $decision['reason'];
            $activity->status = AssistantActivity::HANDED_OVER;
        } elseif ($decision['action'] === IntentCatalog::ACTION_CLARIFY) {
            $this->memory->bumpClarification($mem);
            if ((int) $mem->clarification_count > AssistantRuntimeSettings::maxClarifications()) {
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
                $otpCode = null;
                $otpTtl = 5;
                if (is_array($toolResult) && isset($toolResult['otp_code'])) {
                    $otpCode = $toolResult['otp_code'];
                    $otpTtl = isset($toolResult['otp_ttl']) ? $toolResult['otp_ttl'] : 5;
                    unset($toolResult['otp_code'], $toolResult['otp_ttl']);
                }
                $this->memory->storeTool($mem, $tool, is_array($toolResult) ? $toolResult : []);
                if (is_array($toolResult) && ! empty($toolResult['location_required'])) {
                    $slots['attendance_pending'] = $decision['intent'];
                    $slots['job'] = isset($toolResult['job']) ? $toolResult['job'] : (isset($slots['job']) ? $slots['job'] : null);
                    $this->memory->remember($mem, $decision['intent'], $slots);
                }
                if (is_array($toolResult) && ! empty($toolResult['needs_choice'])) {
                    $slots['internship_pending'] = $decision['intent'];
                    $this->memory->remember($mem, $decision['intent'], $slots);
                } elseif (is_array($toolResult) && ! empty($toolResult['assignment_id'])) {
                    $slots['assignment_id'] = $toolResult['assignment_id'];
                    $slots['internship_pending'] = '';
                    $this->memory->remember($mem, $decision['intent'], $slots);
                }
                if (is_array($toolResult) && ! empty($toolResult['quotation_id'])) {
                    $slots['quotation_id'] = $toolResult['quotation_id'];
                    $slots['quotation_reference'] = isset($toolResult['reference']) ? $toolResult['reference'] : null;
                    $this->memory->remember($mem, $decision['intent'], $slots);
                    $open = app(\App\Services\Rental\RentalRequestService::class)->active($conversation);
                    if ($open) {
                        app(\App\Services\Rental\RentalRequestService::class)->markStaffReview($open, $toolResult['quotation_id']);
                    }
                }
                if (is_array($toolResult)) {
                    if (array_key_exists('verification_pending', $toolResult)) {
                        $slots['verification_pending'] = $toolResult['verification_pending'] ? 1 : 0;
                    }
                    if (! empty($toolResult['clear_verification'])) {
                        $slots['verification_pending'] = 0;
                    }
                    if (! empty($toolResult['document_context'])) {
                        $slots['document_context'] = $toolResult['document_context'];
                        $slots['context'] = $toolResult['document_context'];
                    }
                    if (array_key_exists('document_choice_pending', $toolResult)) {
                        $slots['document_choice_pending'] = $toolResult['document_choice_pending'] ? 1 : 0;
                    }
                    if (! empty($toolResult['tenant_context'])) {
                        $slots['context'] = 'tenant';
                    }
                    if (array_key_exists('tenant_choice_pending', $toolResult)) {
                        $slots['tenant_choice_pending'] = $toolResult['tenant_choice_pending'] ? 1 : 0;
                    }
                    if (array_key_exists('bill_pending', $toolResult)) {
                        $slots['bill_pending'] = $toolResult['bill_pending'] ? 1 : 0;
                    }
                    if (! empty($toolResult['bill_request_id'])) {
                        $slots['bill_request_id'] = $toolResult['bill_request_id'];
                    }
                    if (array_key_exists('maintenance_pending', $toolResult)) {
                        $slots['maintenance_pending'] = $toolResult['maintenance_pending'] ? 1 : 0;
                    }
                    if (! empty($toolResult['maintenance_request_id'])) {
                        $slots['maintenance_request_id'] = $toolResult['maintenance_request_id'];
                    }
                    $this->memory->remember($mem, $decision['intent'], $slots);
                }
                $activity->tools_executed = implode(',', $toolsRun);
                $activity->tool_status = ! empty($toolResult['success']) ? 'ok' : (isset($toolResult['error']) ? $toolResult['error'] : 'failed');
                if (empty($toolResult['success']) && in_array($toolResult['error'] ?? '', ['not_found', 'unknown_tool', 'unimplemented_tool'], true)
                    && in_array($decision['intent'], [IntentCatalog::BOOKING_STATUS, IntentCatalog::INTERNSHIP_TASK], true)) {
                    $decision['action'] = IntentCatalog::ACTION_HANDOVER;
                    $activity->handover_reason = 'tool_failed';
                    $activity->status = AssistantActivity::HANDED_OVER;
                }
            }
        }

        if ($decision['intent'] === IntentCatalog::GREETING && AssistantRuntimeSettings::collectUnknownName() && empty($slots['captured_name']) && empty($context['customer_id']) && empty($context['employee_id']) && empty($context['intern_user_id'])) {
            $slots['awaiting_name'] = 1;
            $this->memory->remember($mem, $decision['intent'], $slots);
        }

        $reply = $directReply !== null ? $directReply : $this->composer->compose($decision['intent'], $decision['action'], $toolResult ?: [], $context, (string) $message->body, $mem->parameters());
        $sent = false;
        $conversation = $conversation->fresh();
        if (is_array($toolResult) && ! empty($toolResult['document_path']) && $conversation->mode === \App\WhatsApp\WhatsAppConversation::MODE_AI) {
            $this->conversations->sendExistingDocument(
                $conversation,
                $toolResult['document_path'],
                isset($toolResult['document_name']) ? $toolResult['document_name'] : 'task-material',
                $reply,
                null,
                'ASSISTANT'
            );
        }
        if (! empty($toolResult['skip_assistant_reply'])) {
            $sent = true;
        } elseif ($otpCode && ($conversation->mode === \App\WhatsApp\WhatsAppConversation::MODE_AI || $decision['action'] === IntentCatalog::ACTION_HANDOVER)) {
            $secret = 'Your BeyondTechWorld verification code is '.$otpCode.'. It expires in '.$otpTtl.' minutes. Do not share this code.';
            $send = $this->conversations->sendSecretText($conversation, $secret);
            $sent = ! empty($send['success']);
            $otpCode = null;
            if (! $sent && is_array($toolResult) && ! empty($toolResult['challenge_id'])) {
                app(\App\Services\WhatsApp\WhatsAppVerificationService::class)->invalidateChallenge($toolResult['challenge_id']);
                $activity->error = isset($send['error']) ? $send['error'] : 'send_failed';
            }
        } elseif ($conversation->mode === \App\WhatsApp\WhatsAppConversation::MODE_AI || $decision['action'] === IntentCatalog::ACTION_HANDOVER) {
            $send = $this->conversations->assistantReply($conversation, $reply);
            $sent = ! empty($send['success']);
            if (! $sent) {
                $activity->error = isset($send['error']) ? $send['error'] : 'send_failed';
            }
        }
        if ($decision['action'] === IntentCatalog::ACTION_HANDOVER && $conversation->fresh()->mode === \App\WhatsApp\WhatsAppConversation::MODE_AI) {
            $roles = isset($context['roles']) ? $context['roles'] : [];
            if ($decision['intent'] === IntentCatalog::HUMAN_REQUEST && in_array('intern', $roles, true)) {
                app(\App\Services\Internship\InternshipWhatsAppService::class)->handover($context, [
                    'reason' => $decision['reason'] ?: 'intern_request',
                ]);
            } else {
                $this->handover->toHuman($conversation, $decision['reason'] ?: 'handover');
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
            IntentCatalog::PREVIOUS_QUOTATION => 'get_customer_quotation_details',
            IntentCatalog::INTERNSHIP_TASK => 'get_current_internship_task',
            IntentCatalog::INTERNSHIP_MATERIAL => 'get_task_materials',
            IntentCatalog::INTERNSHIP_SUBMIT => $this->internshipSubmitTool($slots),
            IntentCatalog::INTERNSHIP_STATUS => $this->internshipStatusTool($slots),
            IntentCatalog::INTERNSHIP_ENQUIRY => 'get_internship_summary',
            IntentCatalog::ATTENDANCE_IN => 'check_in',
            IntentCatalog::ATTENDANCE_OUT => 'check_out',
            IntentCatalog::ATTENDANCE_STATUS => 'get_attendance_status',
            IntentCatalog::ATTENDANCE_HOURS => 'get_work_hours',
            IntentCatalog::ATTENDANCE_ASSIGNMENT => 'get_current_assignment',
            IntentCatalog::ATTENDANCE_CORRECTION => 'request_attendance_correction',
            IntentCatalog::DOCUMENT_REQUEST => 'request_document',
            IntentCatalog::VERIFY_OTP => 'verify_otp',
            IntentCatalog::TENANT_BALANCE => 'get_rent_balance',
            IntentCatalog::TENANT_DUE => 'get_rent_due_date',
            IntentCatalog::TENANT_PAYMENTS => 'get_rent_payment_history',
            IntentCatalog::TENANT_CLAIM => 'reject_payment_claim',
            IntentCatalog::TENANT_DOCUMENT => 'request_tenant_document',
            IntentCatalog::TENANT_CLARIFY => 'clarify_tenant_balance',
            IntentCatalog::MAINTENANCE_CREATE => 'create_maintenance_request',
            IntentCatalog::MAINTENANCE_STATUS => 'get_maintenance_status',
            IntentCatalog::MAINTENANCE_ATTACH => 'add_maintenance_attachment',
            IntentCatalog::BILL_REQUEST => 'create_bill_payment_request',
            IntentCatalog::BILL_CONFIRM => 'confirm_bill_payment_request',
            IntentCatalog::BILL_STATUS => 'get_bill_payment_status',
            IntentCatalog::BILL_MEDIA => 'attach_bill_image',
            IntentCatalog::HUMAN_REQUEST => 'request_human_handover',
        ];

        return isset($map[$intent]) ? $map[$intent] : null;
    }

    protected function internshipSubmitTool(array $slots)
    {
        $text = isset($slots['text']) ? strtolower((string) $slots['text']) : '';
        if (preg_match('/^(yes|confirm|submit)\b|\b(submit these|yes,? submit)\b/', $text)) {
            return 'submit_internship_work';
        }
        if (! empty($slots['media'])) {
            return 'attach_submission_file';
        }
        if (strpos($text, 'github.com') !== false) {
            return 'attach_submission_link';
        }

        return 'prepare_internship_submission';
    }

    protected function internshipStatusTool(array $slots)
    {
        $text = isset($slots['text']) ? strtolower((string) $slots['text']) : '';
        if (preg_match('/reviewed|pass|grade|submission status|supervisor check/', $text)) {
            return 'get_submission_status';
        }

        return 'get_internship_progress';
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
        if (! empty($existing['awaiting_name']) && preg_match("/^[A-Za-z][A-Za-z '\\-]{1,40}$/", trim($text)) && ! preg_match('/\b(yes|no|ok|okay|help|price|speakers?|sound|church|school|company|how|are|you|today|great|good|fine|well|and|doing)\b/i', $text)) {
            $slots['captured_name'] = trim($text);
            $slots['awaiting_name'] = 0;
        }
        if (preg_match("/(?:my name is|i am|i'm)\s+([A-Za-z][A-Za-z'\\-]{1,40})/i", $text, $m)) {
            $candidate = trim($m[1]);
            if (! preg_match('/^(looking|interested|calling|here|from|with|for|the|a|great|good|fine|well|ok|okay|doing)\b/i', $candidate)) {
                $slots['captured_name'] = $candidate;
                $slots['awaiting_name'] = 0;
            }
        }
        if (preg_match('/\b([A-Za-z][A-Za-z]+)\s+(church|school|company|organisation|organization)\b/i', $text, $m)) {
            $slots['organization'] = trim($m[1].' '.$m[2]);
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
