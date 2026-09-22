<?php

namespace App\Services\Assistant;

use App\Assistant\AssistantMemory;
use Carbon\Carbon;

class AssistantConversationMemory
{
    public function forConversation($conversationId)
    {
        $row = AssistantMemory::firstOrNew(['conversation_id' => $conversationId]);
        if ($row->exists && $row->expires_at && $row->expires_at->lt(now())) {
            $row->active_intent = null;
            $row->parameters_json = null;
            $row->missing_json = null;
            $row->clarification_count = 0;
            $row->last_tool = null;
            $row->last_tool_result = null;
        }
        if (! $row->exists) {
            $row->clarification_count = 0;
        }

        return $row;
    }

    public function remember(AssistantMemory $memory, $intent, array $params = [], array $missing = [])
    {
        $merged = array_merge($memory->parameters(), $params);
        $memory->active_intent = $intent;
        $memory->setParameters($merged);
        $memory->setMissing($missing);
        $memory->expires_at = Carbon::now()->addHours((int) config('assistant.memory_ttl_hours'));
        $memory->save();

        return $memory;
    }

    public function bumpClarification(AssistantMemory $memory)
    {
        $memory->clarification_count = (int) $memory->clarification_count + 1;
        $memory->save();

        return $memory;
    }

    public function storeTool(AssistantMemory $memory, $tool, array $result)
    {
        $memory->last_tool = $tool;
        $memory->last_tool_result = json_encode($result);
        $memory->expires_at = Carbon::now()->addHours((int) config('assistant.memory_ttl_hours'));
        $memory->save();
    }

    public function storeSuggestion(AssistantMemory $memory, $body)
    {
        $memory->suggested_reply = $body;
        $memory->expires_at = Carbon::now()->addHours((int) config('assistant.memory_ttl_hours'));
        $memory->save();
    }

    public function clearSuggestion(AssistantMemory $memory)
    {
        $memory->suggested_reply = null;
        $memory->save();
    }
}
