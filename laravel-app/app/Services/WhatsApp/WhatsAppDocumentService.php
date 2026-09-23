<?php

namespace App\Services\WhatsApp;

use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppDocumentRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsAppDocumentService
{
    protected $registry;
    protected $auth;
    protected $otp;
    protected $files;
    protected $conversations;

    public function __construct(
        WhatsAppDocumentRegistry $registry,
        DocumentAuthorizationService $auth,
        WhatsAppVerificationService $otp,
        WhatsAppDocumentFileService $files,
        WhatsAppConversationService $conversations
    ) {
        $this->registry = $registry;
        $this->auth = $auth;
        $this->otp = $otp;
        $this->files = $files;
        $this->conversations = $conversations;
    }

    public function handle(array $context, $text, array $memory = [], $providerMessageId = null)
    {
        $text = trim((string) $text);
        if ($this->looksLikePath($text)) {
            return $this->finish($this->open($context, 'UNKNOWN', $providerMessageId), WhatsAppDocumentRequest::DENIED, 'path_rejected', $this->deny());
        }
        if (! empty($memory['verification_pending']) && preg_match('/\d{6}/', $text) && ! preg_match('/\bcheck\s*-?\s*(in|out)\b/i', $text)) {
            return $this->verifyAndSend($context, $text, $memory);
        }
        $existing = $this->existingRequest($providerMessageId);
        if ($existing) {
            return ['success' => true, 'duplicate' => true, 'message' => $existing->public_message, 'skip_assistant_reply' => true];
        }
        if ($this->hourlyRequests($context) >= max(1, (int) config('services.whatsapp.document_hourly_limit', 8))) {
            return ['success' => true, 'message' => 'Please wait before requesting another document.'];
        }

        $type = $this->typeFrom($text, $memory);
        $choice = $this->roleChoice($text, $memory, isset($context['roles']) ? $context['roles'] : []);
        if ($type === 'LIST') {
            return $this->listResult($context, $choice);
        }
        $roles = isset($context['roles']) ? $context['roles'] : [];
        if ($type === 'AMBIGUOUS' && $choice === null && $this->roleCount($context) > 1) {
            return [
                'success' => true,
                'needs_choice' => true,
                'document_choice_pending' => 1,
                'message' => $this->choiceMessage($roles),
            ];
        }
        if ($type === 'AMBIGUOUS_CONTRACT' && $choice === null && in_array('employee', $roles, true) && in_array('customer', $roles, true)) {
            return [
                'success' => true,
                'needs_choice' => true,
                'document_choice_pending' => 1,
                'message' => 'Do you mean your employment contract or a customer contract?',
            ];
        }
        if ($type === 'AMBIGUOUS' && $choice) {
            $which = 'Which document should I send?';
            if ($choice === 'employee') {
                $which = 'Which employment document should I send?';
            } elseif ($choice === 'intern') {
                $which = 'Which internship document should I send?';
            } elseif ($choice === 'customer') {
                $which = 'Which customer document should I send?';
            }

            return [
                'success' => true,
                'document_context' => $choice,
                'document_choice_pending' => 0,
                'message' => $which,
            ];
        }
        if ($choice) {
            $context['document_context'] = $choice;
        }
        if ($type === null) {
            return ['success' => true, 'document_context' => $choice, 'message' => 'Which document should I send?'];
        }
        if ($type === 'AMBIGUOUS_CONTRACT') {
            if ($choice === 'employee' || (in_array('employee', $roles, true) && ! in_array('customer', $roles, true))) {
                $type = 'EMPLOYEE_CONTRACT';
            } else {
                $type = 'CUSTOMER_CONTRACT';
            }
        }

        $definition = $this->registry->get($type);
        $request = $this->open($context, $type, $providerMessageId);
        $request->document_type = $type;
        $request->sensitivity = $definition ? $definition['sensitivity'] : null;
        $request->requested_reference = $this->referenceFrom($text);
        $owner = $definition ? $this->owner($context, $definition['owner'], $choice) : null;
        if ($owner) {
            $request->identity_type = $owner['type'];
            $request->identity_id = $owner['id'];
        } elseif ($choice) {
            $request->identity_type = $choice;
        }
        $request->save();

        if (! $definition || empty($definition['available'])) {
            return $this->unavailable($request, $type, $choice ?: ($owner ? $owner['type'] : null));
        }
        if (! $owner) {
            return $this->finish($request, WhatsAppDocumentRequest::DENIED, 'wrong_owner', $this->deny(), $choice);
        }

        $record = $this->resolve($type, $owner, $text);
        if (empty($record['ok'])) {
            return $this->finish($request, WhatsAppDocumentRequest::DENIED, $record['code'], $record['message'], $choice);
        }
        if (! empty($record['choose'])) {
            $request->status = WhatsAppDocumentRequest::REQUESTED;
            $request->public_message = $record['message'];
            $request->save();

            return ['success' => true, 'message' => $record['message'], 'document_context' => $choice ?: $owner['type']];
        }
        $request->resolved_record_id = (string) $record['id'];
        $request->save();

        $session = $this->otp->activeSession(
            isset($context['contact_id']) ? $context['contact_id'] : 0,
            $owner['type'],
            $owner['id'],
            $definition['scope']
        );
        $decision = $this->auth->decide($owner['type'], $owner['id'], $definition['owner'], $owner['id'], $definition, $session);
        if (empty($decision['allowed']) && $decision['code'] === 'verification_required') {
            return $this->beginOtp($request, $context, $owner, $definition, $choice);
        }
        if (empty($decision['allowed'])) {
            return $this->finish($request, WhatsAppDocumentRequest::DENIED, $decision['code'], $this->deny(), $choice);
        }

        return $this->deliver($request, $context, $type, $record['id'], $session, false);
    }

    public function listFor(array $context)
    {
        $roles = isset($context['roles']) ? $context['roles'] : [];

        return $this->registry->availableFor($roles);
    }

    public function metrics()
    {
        if (! Schema::hasTable('whatsapp_document_requests')) {
            return $this->emptyMetrics();
        }
        $today = [Carbon::today()->startOfDay(), Carbon::now()->endOfDay()];
        $requests = WhatsAppDocumentRequest::whereBetween('created_at', $today);

        return [
            'requests_today' => (clone $requests)->count(),
            'awaiting_otp' => WhatsAppDocumentRequest::where('status', WhatsAppDocumentRequest::AWAITING_OTP)->count(),
            'verified' => Schema::hasTable('whatsapp_verification_sessions')
                ? \App\WhatsApp\WhatsAppVerificationSession::whereBetween('verified_at', $today)->count()
                : 0,
            'sent' => (clone $requests)->where('status', WhatsAppDocumentRequest::SENT)->count(),
            'failed' => (clone $requests)->where('status', WhatsAppDocumentRequest::FAILED)->count(),
            'denied' => (clone $requests)->where('status', WhatsAppDocumentRequest::DENIED)->count(),
            'expired' => Schema::hasTable('whatsapp_verification_challenges')
                ? \App\WhatsApp\WhatsAppVerificationChallenge::whereNotNull('invalidated_at')->whereNull('verified_at')->whereBetween('updated_at', $today)->count()
                : 0,
        ];
    }

    public function diagnostics()
    {
        $lastChallenge = Schema::hasTable('whatsapp_verification_challenges')
            ? \App\WhatsApp\WhatsAppVerificationChallenge::orderByDesc('id')->first()
            : null;
        $lastVerified = Schema::hasTable('whatsapp_verification_sessions')
            ? \App\WhatsApp\WhatsAppVerificationSession::orderByDesc('id')->first()
            : null;

        return [
            'otp_service' => class_exists(WhatsAppVerificationService::class) ? 'ready' : 'missing',
            'last_otp_generated' => $lastChallenge ? (string) $lastChallenge->created_at : null,
            'last_otp_verified' => $lastVerified ? (string) $lastVerified->verified_at : null,
            'otp_failures' => Schema::hasTable('whatsapp_verification_challenges')
                ? \App\WhatsApp\WhatsAppVerificationChallenge::where('attempts', '>', 0)->whereNull('verified_at')->count()
                : 0,
            'rate_limited' => Schema::hasTable('whatsapp_document_requests')
                ? WhatsAppDocumentRequest::where('failure_code', 'rate_limited')->count()
                : 0,
            'registry_available' => count($this->registry->availableFor(['customer', 'employee', 'intern'])),
            'generation_failures' => Schema::hasTable('whatsapp_document_requests')
                ? WhatsAppDocumentRequest::where('failure_code', 'generation_failed')->count()
                : 0,
            'send_failures' => Schema::hasTable('whatsapp_document_requests')
                ? WhatsAppDocumentRequest::where('failure_code', 'send_failed')->count()
                : 0,
            'unauthorized' => Schema::hasTable('whatsapp_document_requests')
                ? WhatsAppDocumentRequest::where('failure_code', 'wrong_owner')->count()
                : 0,
        ];
    }

    public function panel(WhatsAppConversation $conversation)
    {
        if (! Schema::hasTable('whatsapp_document_requests')) {
            return null;
        }
        $request = WhatsAppDocumentRequest::where('conversation_id', $conversation->id)->orderByDesc('id')->first();
        $session = null;
        if (Schema::hasTable('whatsapp_verification_sessions') && $conversation->contact_id) {
            $session = \App\WhatsApp\WhatsAppVerificationSession::where('whatsapp_contact_id', $conversation->contact_id)
                ->orderByDesc('id')
                ->first();
        }
        if (! $request && ! $session) {
            return null;
        }

        return [
            'identity' => $request ? $request->identity_type : ($session ? $session->identity_type : null),
            'document_type' => $request ? $request->document_type : null,
            'status' => $request ? $request->status : null,
            'verified_until' => $session && $session->isActive() ? (string) $session->expires_at : null,
            'verification' => $session && $session->isActive() ? 'verified' : ($request && $request->status === WhatsAppDocumentRequest::AWAITING_OTP ? 'pending' : 'none'),
        ];
    }

    public function retrySend(WhatsAppDocumentRequest $request)
    {
        if ($request->status === WhatsAppDocumentRequest::SENT) {
            return ['success' => false, 'error' => 'already_sent'];
        }
        $path = $this->files->assertSafe($request->local_path);
        if (! $path) {
            return ['success' => false, 'error' => 'missing_file'];
        }
        $conversation = WhatsAppConversation::find($request->conversation_id);
        if (! $conversation) {
            return ['success' => false, 'error' => 'missing_conversation'];
        }
        $sent = $this->conversations->sendExistingDocument($conversation, $path, $request->file_name ?: 'document.pdf', 'Your document is attached.', null, 'ASSISTANT');
        if (empty($sent['success'])) {
            $request->status = WhatsAppDocumentRequest::FAILED;
            $request->failure_code = 'send_failed';
            $request->failed_at = Carbon::now();
            $request->public_message = 'I could not send that document. It can be retried.';
            $request->save();

            return ['success' => false, 'error' => 'send_failed'];
        }
        $request->status = WhatsAppDocumentRequest::SENT;
        $request->sent_at = Carbon::now();
        $request->failure_code = null;
        $request->public_message = 'Document sent.';
        $request->save();
        $this->cleanup($request);

        return ['success' => true];
    }

    protected function verifyAndSend(array $context, $text, array $memory)
    {
        $contactId = isset($context['contact_id']) ? (int) $context['contact_id'] : 0;
        $checked = $this->otp->verify($contactId, $text);
        $request = WhatsAppDocumentRequest::where('conversation_id', isset($context['conversation_id']) ? $context['conversation_id'] : 0)
            ->where('status', WhatsAppDocumentRequest::AWAITING_OTP)
            ->orderByDesc('id')
            ->first();
        if (empty($checked['ok'])) {
            $message = $this->otpFailure(isset($checked['error']) ? $checked['error'] : 'mismatch');
            if ($request && isset($checked['error']) && in_array($checked['error'], ['expired', 'locked'], true)) {
                $request->status = $checked['error'] === 'expired' ? WhatsAppDocumentRequest::EXPIRED : WhatsAppDocumentRequest::DENIED;
                $request->failure_code = $checked['error'];
                $request->public_message = $message;
                $request->save();
            }

            return [
                'success' => true,
                'message' => $message,
                'verification_pending' => empty($checked['error']) || $checked['error'] === 'mismatch' ? 1 : 0,
                'clear_verification' => isset($checked['error']) && $checked['error'] !== 'mismatch' ? 1 : 0,
                'document_context' => isset($memory['document_context']) ? $memory['document_context'] : null,
            ];
        }
        if (! $request) {
            return ['success' => true, 'message' => 'Verification succeeded. Ask again for the document you need.', 'clear_verification' => 1, 'document_context' => isset($memory['document_context']) ? $memory['document_context'] : null];
        }
        $definition = $this->registry->get($request->document_type);
        $session = $checked['session'];
        $decision = $this->auth->decide(
            $request->identity_type,
            $request->identity_id,
            $definition ? $definition['owner'] : '',
            $request->identity_id,
            $definition ?: [],
            $session
        );
        if (empty($decision['allowed'])) {
            return $this->finish($request, WhatsAppDocumentRequest::DENIED, $decision['code'], $this->deny(), $request->identity_type, true);
        }
        $request->session_id = $session->id;
        $request->status = WhatsAppDocumentRequest::VERIFIED;
        $request->save();

        return $this->deliver($request, $context, $request->document_type, $request->resolved_record_id, $session, true);
    }

    protected function beginOtp(WhatsAppDocumentRequest $request, array $context, array $owner, array $definition, $choice)
    {
        $issued = $this->otp->issue(
            isset($context['contact_id']) ? $context['contact_id'] : 0,
            isset($context['conversation_id']) ? $context['conversation_id'] : null,
            $owner['type'],
            $owner['id'],
            $definition['scope'],
            $request->provider_message_id
        );
        if (empty($issued['ok'])) {
            $message = $issued['error'] === 'cooldown'
                ? 'A verification code is already active. Enter it, or wait before requesting another.'
                : 'Too many verification codes were requested. Try again later.';
            $request->status = WhatsAppDocumentRequest::AWAITING_OTP;
            $request->failure_code = $issued['error'];
            $request->public_message = $message;
            $request->save();

            return [
                'success' => true,
                'message' => $message,
                'verification_pending' => 1,
                'document_context' => $choice ?: $owner['type'],
            ];
        }
        $public = 'Verification is required before I can send that document. Enter the code sent on WhatsApp. It expires in '.$issued['ttl_minutes'].' minutes.';
        $request->status = WhatsAppDocumentRequest::AWAITING_OTP;
        $request->challenge_id = $issued['challenge_id'];
        $request->public_message = $public;
        $request->failure_code = null;
        $request->save();

        return [
            'success' => true,
            'message' => $public,
            'otp_code' => $issued['code'],
            'otp_ttl' => $issued['ttl_minutes'],
            'challenge_id' => $issued['challenge_id'],
            'verification_pending' => 1,
            'document_context' => $choice ?: $owner['type'],
        ];
    }

    protected function deliver(WhatsAppDocumentRequest $request, array $context, $type, $recordId, $session, $clearVerification)
    {
        $request->status = WhatsAppDocumentRequest::GENERATING;
        $request->authorized_at = Carbon::now();
        if ($session) {
            $request->session_id = $session->id;
        }
        $request->save();
        try {
            if ($type === 'CUSTOMER_QUOTATION') {
                $path = $this->files->quotation($recordId);
                $ephemeral = false;
                $name = 'quotation-'.$recordId.'.pdf';
            } else {
                $path = $this->files->invoice($recordId);
                $ephemeral = ! config('services.whatsapp.document_use_fixtures');
                $name = 'invoice-'.$recordId.'.pdf';
            }
        } catch (\Throwable $e) {
            return $this->finish($request, WhatsAppDocumentRequest::FAILED, 'generation_failed', 'I could not prepare that document. A team member can try again.', $request->identity_type, $clearVerification);
        }
        $safe = $this->files->assertSafe($path);
        if (! $safe) {
            return $this->finish($request, WhatsAppDocumentRequest::DENIED, 'path_rejected', $this->deny(), $request->identity_type, $clearVerification);
        }
        $request->local_path = $safe;
        $request->file_name = $name;
        $request->ephemeral = $ephemeral;
        $request->status = WhatsAppDocumentRequest::SENDING;
        $request->save();
        $conversation = isset($context['conversation']) ? $context['conversation'] : WhatsAppConversation::find($request->conversation_id);
        if (! $conversation) {
            return $this->finish($request, WhatsAppDocumentRequest::FAILED, 'send_failed', 'I could not send that document. It can be retried.', $request->identity_type, $clearVerification);
        }
        $label = $type === 'CUSTOMER_QUOTATION' ? 'quotation' : 'invoice';
        $sent = $this->conversations->sendExistingDocument($conversation, $safe, $name, 'Your '.$label.' is attached.', null, 'ASSISTANT');
        if (empty($sent['success'])) {
            return $this->finish($request, WhatsAppDocumentRequest::FAILED, 'send_failed', 'I could not send that document. It can be retried.', $request->identity_type, $clearVerification);
        }
        $request->status = WhatsAppDocumentRequest::SENT;
        $request->sent_at = Carbon::now();
        $request->public_message = 'I sent your '.$label.'.';
        $request->failure_code = null;
        $request->save();
        $this->cleanup($request);

        return [
            'success' => true,
            'message' => $request->public_message,
            'clear_verification' => $clearVerification ? 1 : 0,
            'document_context' => $request->identity_type,
            'document_sent' => true,
        ];
    }

    protected function resolve($type, array $owner, $text)
    {
        if ($type === 'CUSTOMER_QUOTATION') {
            return $this->resolveRows('quotations', 'customer_id', $owner['id'], $text, 'quotation');
        }
        if ($type === 'CUSTOMER_INVOICE') {
            return $this->resolveRows('sales', 'customer_id', $owner['id'], $text, 'invoice');
        }

        return ['ok' => false, 'code' => 'not_available', 'message' => 'That document is not available on WhatsApp.'];
    }

    protected function resolveRows($table, $column, $ownerId, $text, $label)
    {
        if (! Schema::hasTable($table)) {
            return ['ok' => false, 'code' => 'not_available', 'message' => 'That document is not available on WhatsApp.'];
        }
        $reference = $this->referenceFrom($text);
        $query = DB::table($table)->where($column, (int) $ownerId);
        if ($reference !== null && ctype_digit($reference)) {
            $owned = (clone $query)->where('id', (int) $reference)->first();
            if (! $owned) {
                return ['ok' => false, 'code' => 'wrong_owner', 'message' => $this->deny()];
            }

            return ['ok' => true, 'id' => $owned->id];
        }
        if ($reference !== null) {
            $owned = (clone $query)->where('reference_no', $reference)->first();
            if (! $owned) {
                return ['ok' => false, 'code' => 'wrong_owner', 'message' => $this->deny()];
            }

            return ['ok' => true, 'id' => $owned->id];
        }
        $rows = $query->orderByDesc('id')->limit(8)->get();
        if (count($rows) === 0) {
            return ['ok' => false, 'code' => 'not_found', 'message' => 'I could not find that document for this account.'];
        }
        if (count($rows) === 1 || preg_match('/\b(last|latest)\b/i', $text)) {
            return ['ok' => true, 'id' => $rows[0]->id];
        }
        $lines = [];
        foreach ($rows as $row) {
            $ref = isset($row->reference_no) && $row->reference_no ? $row->reference_no : ('#'.$row->id);
            $when = isset($row->created_at) ? substr((string) $row->created_at, 0, 10) : '';
            $lines[] = $ref.($when !== '' ? ' ('.$when.')' : '');
        }

        return [
            'ok' => true,
            'choose' => true,
            'message' => 'I found more than one '.$label.'. Reply with the reference:'."\n".implode("\n", $lines),
        ];
    }

    protected function unavailable(WhatsAppDocumentRequest $request, $type, $choice)
    {
        if ($type === 'CUSTOMER_RECEIPT') {
            return $this->receipt($request, $choice);
        }
        if ($type === 'INTERNSHIP_CERTIFICATE') {
            $message = 'That certificate is not currently available.';
        } elseif ($type === 'EMPLOYEE_PAYSLIP') {
            $message = 'Payslips are not available on WhatsApp yet.';
        } else {
            $message = 'That document is not available on WhatsApp.';
        }

        return $this->finish($request, WhatsAppDocumentRequest::DENIED, 'not_available', $message, $choice);
    }

    protected function receipt(WhatsAppDocumentRequest $request, $choice)
    {
        $customerId = (int) $request->identity_id;
        if ($customerId < 1 && $choice !== 'customer') {
            $customerId = 0;
        }
        $hasPayment = false;
        if ($customerId > 0 && Schema::hasTable('payments') && Schema::hasTable('sales')) {
            $saleIds = DB::table('sales')->where('customer_id', $customerId)->pluck('id')->all();
            if ($saleIds) {
                $hasPayment = DB::table('payments')->whereIn('sale_id', $saleIds)->exists();
            }
        }
        $message = $hasPayment
            ? 'A payment is recorded, but there is no receipt file to send.'
            : 'I could not find a recorded payment for this account, so I cannot send a receipt.';

        return $this->finish($request, WhatsAppDocumentRequest::DENIED, 'not_available', $message, 'customer');
    }

    protected function owner(array $context, $ownerType, $choice)
    {
        if ($choice && $choice !== $ownerType && $ownerType !== 'customer') {
            return null;
        }
        if ($ownerType === 'customer' && ! empty($context['customer_id'])) {
            return ['type' => 'customer', 'id' => (int) $context['customer_id']];
        }
        if ($ownerType === 'employee' && ! empty($context['employee_id']) && ($choice === null || $choice === 'employee')) {
            return ['type' => 'employee', 'id' => (int) $context['employee_id']];
        }
        if ($ownerType === 'intern' && ! empty($context['intern_user_id']) && ($choice === null || $choice === 'intern')) {
            return ['type' => 'intern', 'id' => (int) $context['intern_user_id']];
        }

        return null;
    }

    protected function typeFrom($text, array $memory)
    {
        $t = strtolower($text);
        if (preg_match('/\b(what documents|which documents|documents can i)\b/', $t)) {
            return 'LIST';
        }
        if (preg_match('/\binvoice\b/', $t)) {
            return 'CUSTOMER_INVOICE';
        }
        if (preg_match('/\breceipt\b/', $t)) {
            return 'CUSTOMER_RECEIPT';
        }
        if (preg_match('/\b(quotation|quote)\b/', $t)) {
            return 'CUSTOMER_QUOTATION';
        }
        if (preg_match('/\bpayslip\b/', $t)) {
            return 'EMPLOYEE_PAYSLIP';
        }
        if (preg_match('/\btimesheet\b/', $t)) {
            return 'EMPLOYEE_TIMESHEET';
        }
        if (preg_match('/\bmission\b/', $t)) {
            return 'EMPLOYEE_MISSION_ORDER';
        }
        if (preg_match('/\bcertificate\b/', $t)) {
            return 'INTERNSHIP_CERTIFICATE';
        }
        if (preg_match('/\bassessment\b/', $t)) {
            return 'INTERNSHIP_ASSESSMENT';
        }
        if (preg_match('/\binternship letter\b/', $t)) {
            return 'INTERNSHIP_LETTER';
        }
        if (preg_match('/\bcontract\b/', $t)) {
            return 'AMBIGUOUS_CONTRACT';
        }
        if (preg_match('/\bdocument\b/', $t)) {
            return 'AMBIGUOUS';
        }
        if (! empty($memory['document_choice_pending']) || ! empty($memory['document_context'])) {
            if (preg_match('/^(employee|employment|staff)$/', $t)) {
                return 'AMBIGUOUS';
            }
            if (preg_match('/^(intern|internship)$/', $t)) {
                return 'AMBIGUOUS';
            }
        }

        return null;
    }

    protected function roleChoice($text, array $memory, array $roles)
    {
        $t = strtolower($text);
        if (preg_match('/\bintern/', $t)) {
            return 'intern';
        }
        if (preg_match('/\b(employee|employment|staff|office|payslip|timesheet)\b/', $t)) {
            return 'employee';
        }
        if (preg_match('/\b(quotation|quote|invoice|receipt|customer)\b/', $t)) {
            return 'customer';
        }
        if (! empty($memory['document_context'])) {
            return $memory['document_context'];
        }
        if (! empty($memory['context']) && in_array($memory['context'], ['employee', 'intern', 'customer'], true)) {
            return $memory['context'];
        }

        return null;
    }

    protected function roleCount(array $context)
    {
        $roles = isset($context['roles']) ? $context['roles'] : [];
        $n = 0;
        foreach (['customer', 'employee', 'intern'] as $role) {
            if (in_array($role, $roles, true)) {
                $n++;
            }
        }

        return $n;
    }

    protected function choiceMessage(array $roles)
    {
        $bits = [];
        if (in_array('customer', $roles, true)) {
            $bits[] = 'a customer document';
        }
        if (in_array('employee', $roles, true)) {
            $bits[] = 'an employment document';
        }
        if (in_array('intern', $roles, true)) {
            $bits[] = 'an internship document';
        }
        if (count($bits) < 2) {
            return 'Which document should I send?';
        }

        return 'Do you mean '.implode(' or ', $bits).'?';
    }

    protected function listResult(array $context, $choice)
    {
        $roles = isset($context['roles']) ? $context['roles'] : [];
        if ($choice) {
            $roles = [$choice];
        }
        $rows = $this->registry->availableFor($roles);
        if ($rows === []) {
            return ['success' => true, 'message' => 'There are no documents available for this account on WhatsApp.', 'document_context' => $choice];
        }
        $labels = [];
        foreach ($rows as $row) {
            $labels[] = $row['label'];
        }

        return ['success' => true, 'message' => 'I can send: '.implode(', ', $labels).'.', 'document_context' => $choice];
    }

    protected function open(array $context, $type, $providerMessageId)
    {
        return WhatsAppDocumentRequest::create([
            'whatsapp_contact_id' => isset($context['contact_id']) ? $context['contact_id'] : null,
            'conversation_id' => isset($context['conversation_id']) ? $context['conversation_id'] : null,
            'identity_type' => null,
            'identity_id' => null,
            'document_type' => $type,
            'status' => WhatsAppDocumentRequest::REQUESTED,
            'provider_message_id' => $providerMessageId ? (string) $providerMessageId : null,
            'requested_at' => Carbon::now(),
        ]);
    }

    protected function finish(WhatsAppDocumentRequest $request, $status, $code, $message, $contextRole = null, $clearVerification = false)
    {
        $request->status = $status;
        $request->failure_code = $code;
        $request->public_message = $message;
        if (in_array($status, [WhatsAppDocumentRequest::FAILED, WhatsAppDocumentRequest::DENIED, WhatsAppDocumentRequest::EXPIRED], true)) {
            $request->failed_at = Carbon::now();
        }
        $request->save();

        return [
            'success' => true,
            'message' => $message,
            'document_context' => $contextRole,
            'clear_verification' => $clearVerification ? 1 : 0,
        ];
    }

    protected function existingRequest($providerMessageId)
    {
        if (! $providerMessageId) {
            return null;
        }

        return WhatsAppDocumentRequest::where('provider_message_id', (string) $providerMessageId)->first();
    }

    protected function hourlyRequests(array $context)
    {
        if (empty($context['contact_id']) || ! Schema::hasTable('whatsapp_document_requests')) {
            return 0;
        }

        return WhatsAppDocumentRequest::where('whatsapp_contact_id', $context['contact_id'])
            ->where('created_at', '>=', Carbon::now()->subHour())
            ->count();
    }

    protected function referenceFrom($text)
    {
        if (preg_match('/\b(?:invoice|quotation|quote|receipt|payslip|contract|certificate)\s+#?([A-Za-z0-9\-]+)\b/i', $text, $m)) {
            return $m[1];
        }

        return null;
    }

    protected function looksLikePath($text)
    {
        return (bool) preg_match('#\.\.|://|/etc/|\\\\#', (string) $text);
    }

    protected function otpFailure($error)
    {
        if ($error === 'expired') {
            return 'That code has expired. Request the document again.';
        }
        if ($error === 'locked') {
            return 'That code is no longer valid. Request the document again to receive a new code.';
        }
        if ($error === 'replay') {
            return 'That code has already been used.';
        }
        if ($error === 'missing') {
            return 'There is no verification in progress.';
        }

        return 'That code is not valid. You can try again.';
    }

    protected function deny()
    {
        return "I couldn't provide that document for this account.";
    }

    protected function cleanup(WhatsAppDocumentRequest $request)
    {
        if ($request->ephemeral && $request->local_path && is_file($request->local_path)) {
            @unlink($request->local_path);
        }
    }

    protected function emptyMetrics()
    {
        return [
            'requests_today' => 0,
            'awaiting_otp' => 0,
            'verified' => 0,
            'sent' => 0,
            'failed' => 0,
            'denied' => 0,
            'expired' => 0,
        ];
    }
}
