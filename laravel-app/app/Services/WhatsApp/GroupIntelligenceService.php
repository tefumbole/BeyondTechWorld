<?php

namespace App\Services\WhatsApp;

use App\User;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppGroup;
use App\WhatsApp\WhatsAppGroupAction;
use App\WhatsApp\WhatsAppGroupMessage;
use Carbon\Carbon;

class GroupIntelligenceService
{
    public function extract(WhatsAppGroup $group, WhatsAppGroupMessage $message)
    {
        $body = trim((string) $message->body);
        if ($body === '') {
            return;
        }
        $lower = strtolower($body);
        if (preg_match('/\b(will|should|need the|by \d)/', $lower) && ! preg_match('/\b(agreed|decided|approved)\b/', $lower)) {
            $kind = preg_match('/\b(should|could|suggest|propose)\b/', $lower) ? 'PROPOSAL' : 'ACTION';
            if (preg_match('/\b(delay|unavailable|absent|complaint|fault|not arrived|behind schedule)\b/', $lower)) {
                $kind = 'ISSUE';
            }
            WhatsAppGroupAction::create([
                'group_id' => $group->id,
                'message_id' => $message->id,
                'description' => mb_substr($body, 0, 500),
                'responsible_name' => $this->person($body),
                'due_text' => $this->due($body),
                'kind' => $kind,
                'status' => WhatsAppGroupAction::SUGGESTED,
                'confidence' => 'medium',
            ]);
        }
        if (preg_match('/\b(we agreed|we decided|approved|decision is)\b/', $lower)) {
            WhatsAppGroupAction::create([
                'group_id' => $group->id,
                'message_id' => $message->id,
                'description' => mb_substr($body, 0, 500),
                'kind' => 'DECISION',
                'status' => 'RECORDED',
                'confidence' => 'medium',
            ]);
        }
    }

    public function ownerQuestion($text, User $owner)
    {
        $t = strtolower($text);
        if (strpos($t, 'morning brief') !== false || strpos($t, 'what happened') !== false || strpos($t, 'what did i miss') !== false || strpos($t, 'before i call') !== false) {
            return $this->brief($owner);
        }
        if (strpos($t, 'main issues') !== false || strpos($t, 'across') !== false) {
            return $this->crossGroup();
        }
        $group = $this->namedGroup($text);
        $since = $this->since($text);
        if (! $group) {
            return $this->crossGroup();
        }
        app(OwnerCommandService::class)->audit($group->id, $owner->id, 'summary_requested', $text);

        return $this->summary($group, $since, $t);
    }

    public function summary(WhatsAppGroup $group, Carbon $since, $question)
    {
        $messages = $this->messages($group, $since);
        if ($messages->isEmpty()) {
            return 'I have no stored messages for '.($group->name ?: 'that group').' in that window. I will not guess.';
        }
        $decisions = [];
        $proposals = [];
        $actions = [];
        $issues = [];
        foreach ($messages as $message) {
            $body = trim((string) $message->body);
            $lower = strtolower($body);
            if (preg_match('/\b(we agreed|we decided|approved)\b/', $lower)) {
                $decisions[] = $body;
            } elseif (preg_match('/\b(should|could|suggest|propose)\b/', $lower)) {
                $proposals[] = $body;
            }
            if (preg_match('/\b(will|need the)\b/', $lower)) {
                $actions[] = $body;
            }
            if (preg_match('/\b(delay|unavailable|absent|complaint|fault|not arrived|behind)\b/', $lower)) {
                $issues[] = $body;
            }
        }
        $lines = ['Stored discussion in '.($group->name ?: 'the group').' since '.$since->toDateTimeString().':'];
        $lines[] = 'Decisions: '.($decisions ? implode(' | ', $decisions) : 'none recorded.');
        $lines[] = 'Pending proposals (not decisions): '.($proposals ? implode(' | ', $proposals) : 'none.');
        $lines[] = 'Action items (suggested): '.($actions ? implode(' | ', $actions) : 'none.');
        $lines[] = 'Potential issues: '.($issues ? implode(' | ', $issues) : 'none.');
        if (strpos($question, 'actually') !== false || strpos($question, 'erp') !== false) {
            $lines[] = $this->erpNote($messages);
        }
        $memory = $group->memory();
        $memory['last_topic'] = mb_substr($question, 0, 80);
        $group->setMemory($memory);
        $group->last_summary_at = now();
        $group->save();

        return implode("\n", $lines);
    }

    public function brief(User $owner)
    {
        $parts = ['Morning brief. Group lines are from stored messages. ERP lines are from records.'];
        $parts[] = app(OwnerCommandService::class)->statusText();
        $groups = WhatsAppGroup::where('enabled', true)->get();
        if ($groups->isEmpty()) {
            $parts[] = 'No groups are enabled for monitoring.';
        }
        foreach ($groups as $group) {
            $count = $this->messages($group, Carbon::now()->startOfDay())->count();
            $parts[] = ($group->name ?: $group->group_jid).': '.$count.' stored message(s) today.';
        }
        app(OwnerCommandService::class)->audit(null, $owner->id, 'summary_requested', 'morning brief');

        return implode("\n", $parts);
    }

    public function crossGroup()
    {
        $groups = WhatsAppGroup::where('enabled', true)->get();
        if ($groups->isEmpty()) {
            return 'No enabled groups to summarize.';
        }
        $lines = ['Issues across enabled groups today:'];
        $seen = [];
        foreach ($groups as $group) {
            foreach ($this->messages($group, Carbon::now()->startOfDay()) as $message) {
                $body = trim((string) $message->body);
                $key = strtolower($body);
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                if (preg_match('/\b(delay|unavailable|complaint|fault|behind|not arrived)\b/i', $body)) {
                    $seen[$key] = true;
                    $lines[] = ($group->name ?: 'Group').': '.$body;
                }
            }
        }
        if (count($lines) === 1) {
            $lines[] = 'No issue-like messages are stored today.';
        }

        return implode("\n", $lines);
    }

    public function previewActions(WhatsAppConversation $conversation, User $owner)
    {
        $rows = WhatsAppGroupAction::where('status', WhatsAppGroupAction::SUGGESTED)->where('kind', 'ACTION')->limit(8)->get();
        if ($rows->isEmpty()) {
            return 'There are no suggested action items to create.';
        }
        $lines = ['I can create these ERP tasks after you reply YES:'];
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = $row->id;
            $who = $row->responsible_name ? ' ('.$row->responsible_name.')' : '';
            $lines[] = '- '.$row->description.$who;
        }
        app(OwnerCommandService::class)->rememberPending($conversation, [
            'action' => 'create_actions',
            'ids' => $ids,
        ]);

        return implode("\n", $lines);
    }

    public function confirmActions(User $owner, array $pending)
    {
        $ids = isset($pending['ids']) ? $pending['ids'] : [];
        $created = 0;
        $held = 0;
        foreach (WhatsAppGroupAction::whereIn('id', $ids)->get() as $row) {
            $assignee = $this->assignee($row->responsible_name, $owner);
            if (! $assignee) {
                $held++;
                continue;
            }
            $tasks = [];
            try {
                $tasks = app(\App\Services\TaskService::class)->createTasks([[
                    'subject' => mb_substr($row->description, 0, 120),
                    'description' => $row->description.' Source group message '.$row->message_id,
                    'priority' => 'medium',
                    'assignee_ids' => [$assignee],
                ]], $owner->id);
            } catch (\Throwable $e) {
                $held++;
                continue;
            }
            if ($tasks) {
                $row->status = WhatsAppGroupAction::CONFIRMED;
                $row->task_id = isset($tasks[0]) ? $tasks[0]->id : null;
                $row->save();
                $created++;
            } else {
                $held++;
            }
        }
        app(OwnerCommandService::class)->audit(null, $owner->id, 'action_confirmed', $created.' created, '.$held.' still suggested');

        return $created.' task(s) created. '.$held.' suggestion(s) stayed suggested because no ERP assignee was found.';
    }

    public function draft($text, WhatsAppConversation $conversation, User $owner)
    {
        $group = $this->namedGroup($text);
        if (! $group) {
            return 'Which group should I draft for? Name one discovered group.';
        }
        $draft = 'Draft for '.$group->name.': We saw the update in the group and will confirm the next step from the ERP before changing the plan.';
        app(OwnerCommandService::class)->rememberPending($conversation, [
            'action' => 'draft_only',
            'group_id' => $group->id,
            'draft' => $draft,
        ]);
        app(OwnerCommandService::class)->audit($group->id, $owner->id, 'draft_generated', $group->name);

        return $draft."\n\nThis was not sent. Reply SEND when you want me to ask before posting it to ".$group->name.'.';
    }

    public function confirmSend($text, WhatsAppConversation $conversation, User $owner)
    {
        $memory = app(\App\Services\Assistant\AssistantConversationMemory::class)->forConversation($conversation->id);
        $pending = isset($memory->parameters()['owner_pending']) ? $memory->parameters()['owner_pending'] : null;
        if (! is_array($pending) || empty($pending['draft']) || empty($pending['group_id'])) {
            return 'There is no draft waiting. I will not guess which group you mean.';
        }
        $group = WhatsAppGroup::find($pending['group_id']);
        if (! $group) {
            return 'That draft group no longer exists.';
        }
        if (strtoupper(trim($text)) === 'SEND') {
            app(OwnerCommandService::class)->rememberPending($conversation, [
                'action' => 'send_group',
                'group_id' => $group->id,
                'draft' => $pending['draft'],
            ]);

            return 'Send this to '.$group->name.'?';
        }
        if (preg_match('/^send this to\s+(.+)$/i', trim($text), $m)) {
            if (stripos($group->name, trim($m[1])) === false) {
                return 'That name does not match the draft target. I did not send it.';
            }

            return $this->sendConfirmed($owner, $pending);
        }

        return 'I did not send anything.';
    }

    public function sendConfirmed(User $owner, array $pending)
    {
        $group = WhatsAppGroup::find($pending['group_id']);
        if (! $group) {
            return 'I could not find that group.';
        }
        $provider = app(\App\Contracts\WhatsApp\WhatsAppProviderInterface::class);
        if (! method_exists($provider, 'sendGroupText')) {
            return 'Group sending is not available on this provider.';
        }
        $result = $provider->sendGroupText($group->group_jid, $pending['draft']);
        app(OwnerCommandService::class)->audit($group->id, $owner->id, 'group_message_sent', $group->name);
        if (empty($result['success'])) {
            return 'The group message was not sent.';
        }

        return 'Sent to '.$group->name.'.';
    }

    public function compareClaim($groupStatement, $erpAvailable)
    {
        return 'The group discussion says '.$groupStatement.', but the ERP currently records '.$erpAvailable.' available.';
    }

    protected function messages(WhatsAppGroup $group, Carbon $since)
    {
        return WhatsAppGroupMessage::where('group_id', $group->id)
            ->where('message_at', '>=', $since)
            ->orderBy('id')
            ->get();
    }

    protected function since($text)
    {
        $t = strtolower($text);
        if (strpos($t, 'yesterday') !== false) {
            return Carbon::now()->subDay()->startOfDay();
        }
        if (strpos($t, 'week') !== false) {
            return Carbon::now()->startOfWeek();
        }
        if (strpos($t, '24 hour') !== false) {
            return Carbon::now()->subHours(24);
        }
        if (strpos($t, 'last summary') !== false) {
            return Carbon::now()->subDay();
        }

        return Carbon::now()->startOfDay();
    }

    protected function namedGroup($text)
    {
        foreach (WhatsAppGroup::orderBy('id')->get() as $group) {
            if ($group->name && stripos($text, $group->name) !== false) {
                return $group;
            }
        }

        return null;
    }

    protected function person($body)
    {
        if (preg_match('/\b([A-Z][a-z]+)\s+will\b/', $body, $m)) {
            return $m[1];
        }

        return null;
    }

    protected function due($body)
    {
        if (preg_match('/\b(tomorrow|today|by \d{1,2}(?::\d{2})?\s*(?:am|pm)?)\b/i', $body, $m)) {
            return $m[1];
        }

        return null;
    }

    protected function assignee($name, User $owner)
    {
        if ($name) {
            $user = User::where('name', $name)->first();
            if ($user) {
                return $user->id;
            }
        }

        return null;
    }

    protected function erpNote($messages)
    {
        return 'Group numbers are discussion only. Ask me to check the ERP before treating them as stock.';
    }
}
