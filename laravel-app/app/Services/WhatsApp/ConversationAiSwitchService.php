<?php

namespace App\Services\WhatsApp;

use App\Assistant\AssistantMemory;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppConversationEvent;
use App\WhatsApp\WhatsAppVerificationChallenge;
use Illuminate\Support\Facades\Schema;

class ConversationAiSwitchService
{
    public function preview()
    {
        $eligible = 0;
        $excluded = [
            'assigned' => 0,
            'paused' => 0,
            'closed' => 0,
            'verification' => 0,
            'attendance' => 0,
        ];
        foreach (WhatsAppConversation::orderBy('id')->get() as $conversation) {
            $reason = $this->excludeReason($conversation);
            if ($reason === null) {
                $eligible++;
            } elseif (isset($excluded[$reason])) {
                $excluded[$reason]++;
            }
        }

        return ['eligible' => $eligible, 'excluded' => $excluded];
    }

    public function switchEligible($actorId = null)
    {
        $updated = 0;
        foreach (WhatsAppConversation::orderBy('id')->get() as $conversation) {
            if ($this->excludeReason($conversation) !== null) {
                continue;
            }
            if ($conversation->mode === WhatsAppConversation::MODE_AI) {
                continue;
            }
            $conversation->mode = WhatsAppConversation::MODE_AI;
            $conversation->save();
            WhatsAppConversationEvent::create([
                'conversation_id' => $conversation->id,
                'type' => WhatsAppConversationEvent::MODE,
                'body' => 'Switched eligible conversation to AI',
                'actor_user_id' => $actorId,
            ]);
            $updated++;
        }

        return $updated;
    }

    /**
     * Hand open chats back to AI without wiping their messages, including chats a person had taken over.
     */
    public function resumeOpenConversations($actorId = null)
    {
        $updated = 0;
        foreach (WhatsAppConversation::orderBy('id')->get() as $conversation) {
            $mode = strtoupper((string) $conversation->mode);
            $status = strtoupper((string) $conversation->status);
            if ($mode === WhatsAppConversation::MODE_PAUSED || $status === 'PAUSED') {
                continue;
            }
            if ($mode === WhatsAppConversation::MODE_CLOSED || $status === WhatsAppConversation::STATUS_CLOSED) {
                continue;
            }
            if ($mode === WhatsAppConversation::MODE_AI && empty($conversation->assigned_user_id)) {
                continue;
            }
            $conversation->mode = WhatsAppConversation::MODE_AI;
            $conversation->assigned_user_id = null;
            $conversation->save();
            WhatsAppConversationEvent::create([
                'conversation_id' => $conversation->id,
                'type' => WhatsAppConversationEvent::MODE,
                'body' => 'AI ON resumed this chat from the existing messages',
                'actor_user_id' => $actorId,
            ]);
            $updated++;
        }

        return $updated;
    }

    /**
     * AI Off: open chats leave AI and wait in Human mode. Paused and closed chats stay as they are.
     */
    public function holdOpenConversations($actorId = null)
    {
        $updated = 0;
        foreach (WhatsAppConversation::orderBy('id')->get() as $conversation) {
            $mode = strtoupper((string) $conversation->mode);
            $status = strtoupper((string) $conversation->status);
            if ($mode === WhatsAppConversation::MODE_PAUSED || $status === 'PAUSED') {
                continue;
            }
            if ($mode === WhatsAppConversation::MODE_CLOSED || $status === WhatsAppConversation::STATUS_CLOSED) {
                continue;
            }
            if ($mode === WhatsAppConversation::MODE_HUMAN) {
                continue;
            }
            $conversation->mode = WhatsAppConversation::MODE_HUMAN;
            $conversation->save();
            WhatsAppConversationEvent::create([
                'conversation_id' => $conversation->id,
                'type' => WhatsAppConversationEvent::MODE,
                'body' => 'AI OFF switched this chat to Human mode',
                'actor_user_id' => $actorId,
            ]);
            $updated++;
        }

        return $updated;
    }

    public function excludeReason(WhatsAppConversation $conversation)
    {
        $mode = strtoupper((string) $conversation->mode);
        $status = strtoupper((string) $conversation->status);
        if ($mode === WhatsAppConversation::MODE_PAUSED || $status === 'PAUSED') {
            return 'paused';
        }
        if ($mode === WhatsAppConversation::MODE_CLOSED || $status === WhatsAppConversation::STATUS_CLOSED) {
            return 'closed';
        }
        if ($mode === WhatsAppConversation::MODE_HUMAN && $conversation->assigned_user_id) {
            return 'assigned';
        }
        $params = $this->memoryParams($conversation->id);
        if (! empty($params['verification_pending']) || ! empty($params['document_choice_pending'])) {
            return 'verification';
        }
        if (! empty($params['attendance_pending'])) {
            return 'attendance';
        }
        if ($this->hasOpenChallenge($conversation->id)) {
            return 'verification';
        }

        return null;
    }

    protected function memoryParams($conversationId)
    {
        if (! Schema::hasTable('assistant_memories')) {
            return [];
        }
        $row = AssistantMemory::where('conversation_id', $conversationId)->first();

        return $row ? $row->parameters() : [];
    }

    protected function hasOpenChallenge($conversationId)
    {
        if (! Schema::hasTable('whatsapp_verification_challenges')) {
            return false;
        }

        return WhatsAppVerificationChallenge::where('conversation_id', $conversationId)
            ->whereNull('verified_at')
            ->whereNull('invalidated_at')
            ->exists();
    }
}
