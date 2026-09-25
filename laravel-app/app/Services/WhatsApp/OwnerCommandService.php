<?php

namespace App\Services\WhatsApp;

use App\Assistant\AssistantActivity;
use App\User;
use App\WhatsApp\Lead;
use App\WhatsApp\LeadCatalog;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppGroup;
use App\WhatsApp\WhatsAppGroupAudit;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppSetting;
use Illuminate\Support\Facades\Schema;

class OwnerCommandService
{
    public function handle(WhatsAppConversation $conversation, $text, User $owner)
    {
        $text = trim((string) $text);
        $pending = $this->pending($conversation);
        if ($pending && strtoupper($text) === 'YES') {
            return $this->executePending($conversation, $owner, $pending);
        }
        if ($pending && (strtoupper($text) === 'SEND' || preg_match('/^send this to\b/i', $text))) {
            return app(GroupIntelligenceService::class)->confirmSend($text, $conversation, $owner);
        }
        if ($pending && ! $this->isExactCommand($text)) {
            $this->clearPending($conversation);

            return null;
        }
        $exact = $this->exact($text, $owner);
        if ($exact !== null) {
            $this->audit(null, $owner->id, 'owner_command', $text);

            return $exact;
        }
        $natural = $this->natural($text, $conversation, $owner);
        if ($natural !== null) {
            $this->audit(null, $owner->id, 'owner_command', mb_substr($text, 0, 180));
        }

        return $natural;
    }

    public function run($tool, array $params, array $context)
    {
        if (empty($context['owner_authorized']) || empty($context['owner_user_id'])) {
            return ['success' => false, 'error' => 'privileged', 'message' => 'That action is limited to an authorized owner.'];
        }
        $owner = User::find($context['owner_user_id']);
        if (! $owner) {
            return ['success' => false, 'error' => 'privileged'];
        }
        $conversation = null;
        if (! empty($context['conversation_id'])) {
            $conversation = WhatsAppConversation::find($context['conversation_id']);
        }

        return $this->dispatch($tool, $params, $owner, $conversation, false);
    }

    protected function exact($text, User $owner)
    {
        $t = strtoupper(trim(preg_replace('/\s+/', ' ', $text)));
        if ($t === 'AI ON') {
            return $this->setEnabled(true);
        }
        if ($t === 'AI OFF') {
            return $this->setEnabled(false);
        }
        if ($t === 'AI FIRST ON') {
            return $this->setAiFirst(true);
        }
        if ($t === 'AI FIRST OFF') {
            return $this->setAiFirst(false);
        }
        if ($t === 'AI STATUS') {
            return $this->statusText();
        }
        if ($t === 'GROUPS') {
            return app(GroupRegistryService::class)->listText();
        }

        return null;
    }

    protected function natural($text, WhatsAppConversation $conversation, User $owner)
    {
        $t = strtolower($text);
        if (preg_match('/\b(turn ai on|enable (the )?ai|start the ai)\b/', $t) && strpos($t, 'first') === false) {
            return $this->setEnabled(true);
        }
        if (preg_match('/\b(stop the ai|turn ai off|disable (the )?ai)\b/', $t)) {
            return $this->setEnabled(false);
        }
        if (preg_match('/\b(ai handle all new|new conversations|ai-first|ai first)\b/', $t) && strpos($t, 'off') === false && strpos($t, 'stop') === false) {
            return $this->setAiFirst(true);
        }
        if (preg_match('/\b(give all eligible|switch (all|the) eligible|switch all conversations)\b/', $t)) {
            return $this->previewSwitch($conversation, $owner);
        }
        if (preg_match('/\b(need my attention|waiting for a response|who is waiting)\b/', $t)) {
            return $this->attentionText();
        }
        if (preg_match('/\b(today\'?s new leads|new leads)\b/', $t)) {
            return $this->leadsText();
        }
        if (preg_match('/\b(waiting for quotations|quotations waiting)\b/', $t)) {
            return $this->quotationText();
        }
        if (preg_match('/\btake over\b/', $t) || preg_match('/\bgive\b.+\bto me\b/', $t)) {
            return $this->takeoverText($text, $owner);
        }
        if (preg_match('/\breturn\b.+\bto ai\b/', $t)) {
            return $this->returnText($text, $owner);
        }
        if (preg_match('/\b(monitor|stop monitoring|mention only|ai active)\b/', $t)) {
            return app(GroupRegistryService::class)->command($text, $owner);
        }
        if (preg_match('/\b(summarize|what did i miss|morning brief|what happened|main issues|deadlines|decisions|promised|problems were reported|before i call)\b/', $t)) {
            return app(GroupIntelligenceService::class)->ownerQuestion($text, $owner);
        }
        if (preg_match('/\bcreate those action items\b/', $t)) {
            return app(GroupIntelligenceService::class)->previewActions($conversation, $owner);
        }
        if (preg_match('/\bdraft a reply\b/', $t)) {
            return app(GroupIntelligenceService::class)->draft($text, $conversation, $owner);
        }
        if (strtoupper($text) === 'SEND' || preg_match('/^send this to\b/i', $text)) {
            return app(GroupIntelligenceService::class)->confirmSend($text, $conversation, $owner);
        }

        return null;
    }

    protected function dispatch($tool, array $params, User $owner, $conversation, $confirmed)
    {
        if ($tool === 'get_ai_status') {
            return ['success' => true, 'message' => $this->statusText()];
        }
        if ($tool === 'set_ai_enabled') {
            return ['success' => true, 'message' => $this->setEnabled(! empty($params['enabled']))];
        }
        if ($tool === 'set_ai_first') {
            return ['success' => true, 'message' => $this->setAiFirst(! empty($params['enabled']))];
        }
        if ($tool === 'switch_eligible_conversations_to_ai') {
            if (! $confirmed) {
                return ['success' => true, 'message' => $conversation ? $this->previewSwitch($conversation, $owner) : 'Confirm that switch from the owner chat.'];
            }
            $count = app(ConversationAiSwitchService::class)->switchEligible($owner->id);

            return ['success' => true, 'message' => $count.' eligible conversation(s) are now AI.'];
        }
        if ($tool === 'get_conversations_needing_attention') {
            return ['success' => true, 'message' => $this->attentionText()];
        }
        if ($tool === 'get_open_leads_summary') {
            return ['success' => true, 'message' => $this->leadsText()];
        }
        if ($tool === 'get_pending_quotation_summary') {
            return ['success' => true, 'message' => $this->quotationText()];
        }
        if ($tool === 'get_failed_whatsapp_summary') {
            return ['success' => true, 'message' => $this->failedText()];
        }

        return ['success' => false, 'error' => 'unknown_tool'];
    }

    protected function setEnabled($on)
    {
        WhatsAppSetting::putValue('assistant_enabled', $on ? '1' : '0');

        return $on ? 'Beyond Assistant is enabled. Existing conversations were not changed.' : 'Beyond Assistant is disabled. Automatic AI replies are off.';
    }

    protected function setAiFirst($on)
    {
        WhatsAppSetting::putValue('ai_first', $on ? '1' : '0');
        if (! $on) {
            $mode = WhatsAppSetting::getValue('default_conversation_mode', 'HUMAN');
            if (strtoupper((string) $mode) === 'AI') {
                WhatsAppSetting::putValue('default_conversation_mode', 'HUMAN');
            }
        }

        return $on
            ? 'AI-first is on for new conversations only. Open chats were not changed.'
            : 'AI-first is off. New conversations follow the configured non-AI mode.';
    }

    public function statusText()
    {
        $enabled = WhatsAppSetting::getValue('assistant_enabled', '0') === '1';
        $first = WhatsAppSetting::getValue('ai_first', '0') === '1';
        $ai = WhatsAppConversation::where('mode', WhatsAppConversation::MODE_AI)->count();
        $human = WhatsAppConversation::where('mode', WhatsAppConversation::MODE_HUMAN)->count();
        $waiting = WhatsAppConversation::where('status', WhatsAppConversation::STATUS_WAITING_STAFF)->count();
        $failed = 0;
        if (Schema::hasTable('assistant_activities')) {
            $failed = AssistantActivity::where('status', AssistantActivity::FAILED)->count();
        }
        $leads = 0;
        if (Schema::hasTable('leads')) {
            $leads = Lead::whereNotIn('status', LeadCatalog::closedStatuses())->count();
        }

        return 'Assistant: '.($enabled ? 'enabled' : 'disabled')
            .'. AI-first: '.($first ? 'on' : 'off')
            .'. AI conversations: '.$ai
            .'. HUMAN conversations: '.$human
            .'. Waiting for staff: '.$waiting
            .'. Failed AI messages: '.$failed
            .'. Unresolved leads: '.$leads.'.';
    }

    protected function previewSwitch(WhatsAppConversation $conversation, User $owner)
    {
        $preview = app(ConversationAiSwitchService::class)->preview();
        $excluded = array_sum($preview['excluded']);
        $this->rememberPending($conversation, [
            'action' => 'switch_eligible_conversations_to_ai',
            'eligible' => $preview['eligible'],
        ]);

        return $preview['eligible'].' conversations are eligible for AI. '.$excluded.' are excluded, including staff-owned chats, and will remain HUMAN. Switch the '.$preview['eligible'].' eligible conversations?';
    }

    protected function attentionText()
    {
        $rows = WhatsAppConversation::whereIn('status', [
            WhatsAppConversation::STATUS_WAITING_STAFF,
            WhatsAppConversation::STATUS_OPEN,
        ])->where('mode', WhatsAppConversation::MODE_HUMAN)->orderByDesc('id')->limit(8)->get();
        if ($rows->isEmpty()) {
            return 'No conversations are waiting for a staff response.';
        }
        $lines = ['Conversations needing attention:'];
        foreach ($rows as $row) {
            $name = optional($row->contact)->displayName() ?: 'Unknown';
            $lines[] = '#'.$row->id.' '.$name.' ('.$row->status.')';
        }

        return implode("\n", $lines);
    }

    protected function leadsText()
    {
        if (! Schema::hasTable('leads')) {
            return 'No lead records are available.';
        }
        $rows = Lead::whereNotIn('status', LeadCatalog::closedStatuses())
            ->whereDate('created_at', date('Y-m-d'))
            ->orderByDesc('id')->limit(8)->get();
        if ($rows->isEmpty()) {
            return 'No new leads were opened today.';
        }
        $lines = ['New leads today:'];
        foreach ($rows as $lead) {
            $lines[] = ($lead->name ?: 'Unnamed').($lead->company ? ' ('.$lead->company.')' : '');
        }

        return implode("\n", $lines);
    }

    protected function quotationText()
    {
        if (! Schema::hasTable('quotations')) {
            return 'No quotation records are available.';
        }
        $count = \App\Quotation::where('quotation_status', 1)->count();

        return $count.' quotation(s) are still pending in the ERP. This count is from the quotation table, not from group chat.';
    }

    protected function failedText()
    {
        $count = WhatsAppMessage::where('status', WhatsAppMessage::STATUS_FAILED)->count();

        return $count.' WhatsApp message(s) are marked failed.';
    }

    protected function takeoverText($text, User $owner)
    {
        $matches = $this->findConversations($text);
        if (count($matches) === 0) {
            return 'I could not find that conversation.';
        }
        if (count($matches) > 1) {
            return 'More than one conversation matches. Reply with the conversation id.';
        }
        $conversation = $matches[0];
        $conversation->mode = WhatsAppConversation::MODE_HUMAN;
        $conversation->assigned_user_id = $owner->id;
        $conversation->save();
        $this->audit(null, $owner->id, 'human_override', 'Assigned conversation '.$conversation->id);

        return 'That conversation is now HUMAN and assigned to you.';
    }

    protected function returnText($text, User $owner)
    {
        $matches = $this->findConversations($text);
        if (count($matches) !== 1) {
            return count($matches) === 0 ? 'I could not find that conversation.' : 'More than one conversation matches. Reply with the conversation id.';
        }
        $conversation = $matches[0];
        $conversation->mode = WhatsAppConversation::MODE_AI;
        if ((int) $conversation->assigned_user_id === (int) $owner->id) {
            $conversation->assigned_user_id = null;
        }
        $conversation->save();
        $this->audit(null, $owner->id, 'return_to_ai', 'Returned conversation '.$conversation->id);

        return 'That conversation is back on AI.';
    }

    protected function findConversations($text)
    {
        $needle = trim(preg_replace('/\b(take over|return|give|to me|to ai|conversation)\b/i', ' ', $text));
        $needle = trim(preg_replace('/\s+/', ' ', $needle));
        if (preg_match('/\d{8,}/', $text, $m)) {
            $phone = app(WhatsAppIdentityService::class)->normalize($m[0]);
            $contactIds = \App\WhatsApp\WhatsAppContact::where('normalized_phone', $phone)->pluck('id');

            return WhatsAppConversation::whereIn('contact_id', $contactIds)->get()->all();
        }
        if ($needle === '') {
            return [];
        }
        $contactIds = \App\WhatsApp\WhatsAppContact::where('wa_name', 'like', '%'.$needle.'%')->pluck('id');

        return WhatsAppConversation::whereIn('contact_id', $contactIds)->get()->all();
    }

    protected function executePending(WhatsAppConversation $conversation, User $owner, array $pending)
    {
        $this->clearPending($conversation);
        if (($pending['action'] ?? '') === 'switch_eligible_conversations_to_ai') {
            $count = app(ConversationAiSwitchService::class)->switchEligible($owner->id);
            $this->audit(null, $owner->id, 'owner_command', 'Confirmed bulk AI switch');

            return $count.' eligible conversation(s) are now AI. Staff-owned chats were left HUMAN.';
        }
        if (($pending['action'] ?? '') === 'create_actions') {
            return app(GroupIntelligenceService::class)->confirmActions($owner, $pending);
        }
        if (($pending['action'] ?? '') === 'send_group') {
            return app(GroupIntelligenceService::class)->sendConfirmed($owner, $pending);
        }

        return 'There is nothing waiting to confirm.';
    }

    public function rememberPending(WhatsAppConversation $conversation, array $payload)
    {
        $payload['expires'] = now()->addMinutes(15)->toDateTimeString();
        $memory = app(\App\Services\Assistant\AssistantConversationMemory::class)->forConversation($conversation->id);
        app(\App\Services\Assistant\AssistantConversationMemory::class)->remember($memory, 'OWNER', [
            'owner_pending' => $payload,
        ]);
    }

    protected function pending(WhatsAppConversation $conversation)
    {
        if (! Schema::hasTable('assistant_memories')) {
            return null;
        }
        $memory = app(\App\Services\Assistant\AssistantConversationMemory::class)->forConversation($conversation->id);
        $params = $memory->parameters();
        $pending = isset($params['owner_pending']) && is_array($params['owner_pending']) ? $params['owner_pending'] : null;
        if (! $pending || empty($pending['expires']) || strtotime($pending['expires']) < time()) {
            return null;
        }

        return $pending;
    }

    protected function clearPending(WhatsAppConversation $conversation)
    {
        if (! Schema::hasTable('assistant_memories')) {
            return;
        }
        $memory = app(\App\Services\Assistant\AssistantConversationMemory::class)->forConversation($conversation->id);
        $params = $memory->parameters();
        $params['owner_pending'] = null;
        app(\App\Services\Assistant\AssistantConversationMemory::class)->remember($memory, 'OWNER', $params);
    }

    protected function isExactCommand($text)
    {
        return in_array(strtoupper(trim($text)), ['AI ON', 'AI OFF', 'AI FIRST ON', 'AI FIRST OFF', 'AI STATUS', 'GROUPS', 'YES'], true);
    }

    public function audit($groupId, $userId, $type, $body)
    {
        if (! Schema::hasTable('whatsapp_group_audits')) {
            return;
        }
        WhatsAppGroupAudit::create([
            'group_id' => $groupId,
            'actor_user_id' => $userId,
            'type' => $type,
            'body' => mb_substr((string) $body, 0, 500),
        ]);
    }
}
