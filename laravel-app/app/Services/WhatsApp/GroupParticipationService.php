<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\WhatsApp\WhatsAppContact;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppGroup;
use App\WhatsApp\WhatsAppGroupMessage;

class GroupParticipationService
{
    public function maybeReply(WhatsAppGroup $group, WhatsAppGroupMessage $message, array $parsed)
    {
        $body = trim((string) $message->body);
        if ($group->mode === WhatsAppGroup::OFF || $group->mode === WhatsAppGroup::MONITOR) {
            return null;
        }
        $mentioned = $this->mentioned($body);
        $question = substr(rtrim($body), -1) === '?';
        if ($group->mode === WhatsAppGroup::MENTION && ! $mentioned) {
            return null;
        }
        if ($group->mode === WhatsAppGroup::ACTIVE && ! $mentioned && ! $question) {
            return null;
        }
        if ($this->sensitive($body)) {
            app(OwnerCommandService::class)->audit($group->id, null, 'sensitive_denied', 'private follow-up');
            $this->privateFollowUp($message);

            return $this->say($group, 'I can help with that privately.');
        }
        if (! $mentioned && ! $question) {
            return null;
        }
        $reply = $this->answer($group, $body, $message);
        if ($reply === null) {
            return null;
        }
        app(OwnerCommandService::class)->audit($group->id, null, 'ai_group_response', mb_substr($reply, 0, 180));

        return $this->say($group, $reply);
    }

    protected function answer(WhatsAppGroup $group, $body, WhatsAppGroupMessage $message)
    {
        $lower = strtolower($body);
        if (preg_match('/\b(salary|payslip|otp|contract|tenant balance|how much does .* earn)\b/', $lower)) {
            return null;
        }
        if (preg_match('/\b(available|how many|in stock)\b/', $lower)) {
            if (! $group->allow_inventory) {
                app(OwnerCommandService::class)->audit($group->id, null, 'sensitive_denied', 'inventory not allowed in this group');

                return 'I cannot share inventory in this group.';
            }
            $query = '';
            if (preg_match_all('/\b([A-Za-z0-9-]{3,})\b/', $body, $all)) {
                foreach ($all[1] as $word) {
                    if (in_array(strtolower($word), ['beyond', 'how', 'many', 'are', 'available', 'what', 'the'], true)) {
                        continue;
                    }
                    $query = $word;
                    break;
                }
            }
            $result = app(\App\Services\Assistant\AssistantToolExecutor::class)->execute('search_rental_products', [
                'query' => $query,
            ], ['roles' => ['staff']]);
            app(OwnerCommandService::class)->audit($group->id, null, 'erp_tool_from_group', 'search_rental_products');
            if (empty($result['success'])) {
                return 'I could not confirm that from the ERP.';
            }
            $products = isset($result['products']) ? $result['products'] : [];
            if ($products === []) {
                return 'The ERP catalogue has no matching rental item.';
            }
            $first = $products[0];
            $product = \App\Product::find($first['id']);
            $qty = $product && isset($product->qty) ? $product->qty : 'unknown';

            return 'ERP catalogue: '.$first['name'].'. Available quantity on record: '.$qty.'. A group message is not inventory.';
        }
        if (strpos($lower, 'summarize') !== false) {
            return app(GroupIntelligenceService::class)->summary($group, \Carbon\Carbon::now()->startOfDay(), $body);
        }

        return null;
    }

    protected function mentioned($body)
    {
        return (bool) preg_match('/@beyond\b/i', $body);
    }

    protected function sensitive($body)
    {
        return (bool) preg_match('/\b(salary|payslip|otp|my contract|tenant balance|bank account)\b/i', $body);
    }

    protected function privateFollowUp(WhatsAppGroupMessage $message)
    {
        $phone = $message->participant_phone;
        if (! $phone) {
            return;
        }
        $contact = WhatsAppContact::where('normalized_phone', $phone)->first();
        if (! $contact) {
            app(WhatsAppProviderInterface::class)->sendText($phone, 'I can help with that privately. Tell me what you need in this chat.');

            return;
        }
        $conversation = WhatsAppConversation::where('contact_id', $contact->id)->orderByDesc('id')->first();
        if ($conversation && $conversation->mode === WhatsAppConversation::MODE_AI) {
            app(WhatsAppConversationService::class)->assistantReply($conversation, 'I can help with that privately. Tell me what you need in this chat.');

            return;
        }
        app(WhatsAppProviderInterface::class)->sendText($phone, 'I can help with that privately. Tell me what you need in this chat.');
        if ($conversation) {
            \App\WhatsApp\WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'contact_id' => $contact->id,
                'direction' => \App\WhatsApp\WhatsAppMessage::DIR_OUT,
                'type' => 'TEXT',
                'body' => 'I can help with that privately. Tell me what you need in this chat.',
                'status' => \App\WhatsApp\WhatsAppMessage::STATUS_SENT,
                'sender_type' => 'ASSISTANT',
                'sent_at' => now(),
            ]);
        }
    }

    protected function say(WhatsAppGroup $group, $text)
    {
        $provider = app(WhatsAppProviderInterface::class);
        if (! method_exists($provider, 'sendGroupText')) {
            return null;
        }
        $provider->sendGroupText($group->group_jid, $text);

        return $text;
    }
}
