<?php

namespace App\Services\Assistant;

use App\Jobs\SendAssistantEscalation;
use App\Services\Messaging\NotificationRouter;
use App\Services\WhatsApp\WhatsAppConversationService;
use App\Support\TwilioAdminCopy;
use App\User;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppConversationEvent;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssistantHandoverService
{
    protected $conversations;

    public function __construct(WhatsAppConversationService $conversations)
    {
        $this->conversations = $conversations;
    }

    public function toHuman(WhatsAppConversation $conversation, $reason = 'handover')
    {
        $conversation->mode = WhatsAppConversation::MODE_HUMAN;
        if ($conversation->status === WhatsAppConversation::STATUS_CLOSED) {
            $conversation->status = WhatsAppConversation::STATUS_WAITING_STAFF;
        }
        $agentId = AssistantRuntimeSettings::handoverUserId();
        if ($agentId > 0 && ! $conversation->assigned_user_id && User::where('id', $agentId)->exists()) {
            $conversation->assigned_user_id = $agentId;
        }
        $conversation->save();
        WhatsAppConversationEvent::create([
            'conversation_id' => $conversation->id,
            'type' => WhatsAppConversationEvent::TAKEOVER,
            'body' => 'AI handed over: '.$reason,
        ]);
        SendAssistantEscalation::dispatch($conversation->id, [
            'reason' => $this->redact($reason),
        ]);

        return $conversation;
    }

    public function escalationNumber()
    {
        $stored = trim((string) WhatsAppSetting::getValue('AI_ESCALATION_WHATSAPP_NUMBER', ''));
        if ($stored === '') {
            $stored = trim((string) config('assistant.escalation_whatsapp_number', ''));
        }

        return $stored;
    }

    public function retrySend($conversationId, array $details)
    {
        $conversation = WhatsAppConversation::with('contact')->find($conversationId);
        if (! $conversation || $conversation->mode !== WhatsAppConversation::MODE_HUMAN) {
            return $this->result(false, false, false, 'not_human');
        }
        $number = $this->destination();
        $customer = $conversation->contact ? (string) $conversation->contact->normalized_phone : '';
        if ($number !== '' && $customer !== '' && $this->samePhone($number, $customer)) {
            $this->flagFailure($conversation, 'loop_blocked');

            return $this->result(false, false, true, 'loop_blocked');
        }
        $claim = $this->claim($conversation->id);
        if ($claim === 'submitted') {
            return $this->result(true, false, true, null, true);
        }
        if ($claim === 'in_progress') {
            return $this->result(false, false, true, 'in_progress', true);
        }

        return $this->attempt($conversation, $details, $number, $customer);
    }

    public function redact($text)
    {
        $text = trim((string) $text);
        $text = preg_replace('/\b(?:\d[ -]?){13,19}\b/', '[redacted]', $text);
        $text = preg_replace('/\b(otp|pin|cvv|cvc|password|secret|token)\b[:\s]*\S*/i', '[redacted]', $text);

        return mb_substr($text, 0, 500);
    }

    protected function destination()
    {
        $number = $this->escalationNumber();
        if ($number === '') {
            $number = trim((string) TwilioAdminCopy::PHONE);
        }

        return $number;
    }

    protected function attempt(WhatsAppConversation $conversation, array $details, $number, $customer)
    {
        $fresh = $conversation->fresh();
        if (! $fresh || $fresh->mode !== WhatsAppConversation::MODE_HUMAN) {
            return $this->result(false, false, true, 'not_human');
        }
        $summary = $this->staffSummary($fresh, $details, $customer);
        try {
            $sent = app(NotificationRouter::class)->sendWasenderText($number, TwilioAdminCopy::clip($summary));
        } catch (\Throwable $e) {
            $sent = ['success' => false, 'error' => $e->getMessage(), 'transient' => true];
        }
        if (! empty($sent['success'])) {
            $this->mark($fresh, 'Escalation submitted');

            return $this->result(true, false, true);
        }

        $error = isset($sent['error']) ? (string) $sent['error'] : 'send_failed';
        $transient = ! empty($sent['transient']) || $this->isTransient($error);
        if ($transient) {
            $this->mark($fresh, 'Escalation retrying: '.mb_substr($error, 0, 120));
            Log::warning('[beyond-assistant] handover alert retrying', [
                'conversation_id' => $fresh->id,
                'error' => mb_substr($error, 0, 180),
            ]);

            return $this->result(false, false, true, $error, false, true);
        }

        $this->flagFailure($fresh, $error);

        return $this->result(false, false, true, $error);
    }

    protected function claim($conversationId)
    {
        return DB::transaction(function () use ($conversationId) {
            WhatsAppConversation::where('id', $conversationId)->lockForUpdate()->first();
            $submitted = WhatsAppConversationEvent::where('conversation_id', $conversationId)
                ->where('type', WhatsAppConversationEvent::TAKEOVER)
                ->where('body', 'Escalation submitted')
                ->where('created_at', '>=', now()->subMinutes(15))
                ->exists();
            if ($submitted) {
                return 'submitted';
            }
            $sending = WhatsAppConversationEvent::where('conversation_id', $conversationId)
                ->where('type', WhatsAppConversationEvent::TAKEOVER)
                ->where('body', 'Escalation sending')
                ->where('created_at', '>=', now()->subMinutes(2))
                ->exists();
            if ($sending) {
                return 'in_progress';
            }
            WhatsAppConversationEvent::create([
                'conversation_id' => $conversationId,
                'type' => WhatsAppConversationEvent::TAKEOVER,
                'body' => 'Escalation sending',
            ]);

            return 'claimed';
        });
    }

    protected function mark(WhatsAppConversation $conversation, $body)
    {
        $row = WhatsAppConversationEvent::where('conversation_id', $conversation->id)
            ->where('type', WhatsAppConversationEvent::TAKEOVER)
            ->where('body', 'Escalation sending')
            ->orderByDesc('id')
            ->first();
        if ($row) {
            $row->body = $body;
            $row->save();

            return;
        }
        WhatsAppConversationEvent::create([
            'conversation_id' => $conversation->id,
            'type' => WhatsAppConversationEvent::TAKEOVER,
            'body' => $body,
        ]);
    }

    protected function staffSummary(WhatsAppConversation $conversation, array $details, $customer)
    {
        $reason = $this->redact(isset($details['reason']) ? $details['reason'] : 'handover');
        $who = $customer !== '' ? $customer : 'A visitor';
        if ($conversation->contact) {
            $name = trim((string) $conversation->contact->displayName());
            if ($name !== '') {
                $who = trim($name.' '.$who);
            }
        }
        $lines = [];
        $messages = $conversation->messages()->orderBy('id', 'desc')->limit(8)->get()->reverse();
        foreach ($messages as $message) {
            $body = trim((string) $message->body);
            if ($body === '') {
                continue;
            }
            $speaker = $message->direction === WhatsAppMessage::DIR_IN ? 'Customer' : 'Mbole AI';
            $lines[] = $speaker.': '.$this->redact($body);
        }
        $link = url('/admin/whatsapp/conversations/'.$conversation->id);
        $text = "Mbole AI needs a staff member to contact this chat.\n"
            .$who."\n"
            .'Reason: '.$reason."\n"
            .'Conversation: '.$link;
        if ($lines) {
            $text .= "\n\n".implode("\n", $lines);
        }

        return $text;
    }

    protected function flagFailure(WhatsAppConversation $conversation, $error)
    {
        Log::warning('[beyond-assistant] handover delivery failed', [
            'conversation_id' => $conversation->id,
            'error' => mb_substr((string) $error, 0, 180),
        ]);
        $this->mark($conversation, 'Escalation failed: '.mb_substr((string) $error, 0, 160));
    }

    protected function isTransient($error)
    {
        return (bool) preg_match('/timeout|temporar|unavailable|connection|429|500|502|503|504/i', (string) $error);
    }

    protected function samePhone($left, $right)
    {
        $a = substr(preg_replace('/\D+/', '', (string) $left), -9);
        $b = substr(preg_replace('/\D+/', '', (string) $right), -9);

        return $a !== '' && $a === $b;
    }

    protected function result($submitted, $delivered, $queued, $error = null, $duplicate = false, $retrying = false)
    {
        return [
            'handed_over' => true,
            'submitted' => (bool) $submitted,
            'delivered' => (bool) $delivered,
            'queued' => (bool) $queued,
            'duplicate' => (bool) $duplicate,
            'retrying' => (bool) $retrying,
            'error' => $error,
        ];
    }
}
