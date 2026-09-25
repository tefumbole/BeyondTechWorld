<?php

namespace App\Services\WhatsApp;

use App\WhatsApp\WhatsAppGroup;
use App\WhatsApp\WhatsAppGroupMessage;
use App\WhatsApp\WhatsAppGroupParticipant;
use App\WhatsApp\WhatsAppOwnerUser;
use Illuminate\Support\Facades\Schema;

class GroupIngestService
{
    public function ingest(array $parsed)
    {
        if (! Schema::hasTable('whatsapp_groups')) {
            return ['stored' => false, 'reason' => 'not_installed'];
        }
        $group = app(GroupRegistryService::class)->upsertFromMessage($parsed);
        if (! $group || ! $group->enabled || $group->mode === WhatsAppGroup::OFF) {
            return ['stored' => false, 'reason' => 'monitoring_off', 'group_id' => $group ? $group->id : null];
        }
        $providerId = isset($parsed['message_id']) ? (string) $parsed['message_id'] : '';
        if ($providerId === '') {
            return ['stored' => false, 'reason' => 'no_message_id'];
        }
        $existing = WhatsAppGroupMessage::where('provider_message_id', $providerId)->first();
        if ($existing) {
            return ['stored' => false, 'reason' => 'duplicate', 'message_id' => $existing->id];
        }
        $phone = isset($parsed['participant_phone']) ? $parsed['participant_phone'] : null;
        $media = isset($parsed['media']) ? $parsed['media'] : [];
        $derived = null;
        $label = null;
        if (isset($media['type']) && $media['type'] === 'audio') {
            $label = 'transcription_unavailable';
        }
        $message = WhatsAppGroupMessage::create([
            'group_id' => $group->id,
            'provider_message_id' => $providerId,
            'participant_phone' => $phone,
            'participant_jid' => isset($parsed['participant_jid']) ? $parsed['participant_jid'] : null,
            'participant_name' => isset($parsed['wa_name']) ? $parsed['wa_name'] : null,
            'body' => isset($parsed['body']) ? $parsed['body'] : null,
            'derived_text' => $derived,
            'derived_label' => $label,
            'reply_to_message_id' => isset($parsed['reply_to']) ? $parsed['reply_to'] : null,
            'media_json' => $media ? json_encode($media) : null,
            'message_at' => ! empty($parsed['timestamp']) ? date('Y-m-d H:i:s', (int) $parsed['timestamp']) : now(),
        ]);
        $this->participant($group, $parsed);
        app(GroupIntelligenceService::class)->extract($group, $message);
        $reply = app(GroupParticipationService::class)->maybeReply($group, $message, $parsed);

        return ['stored' => true, 'message_id' => $message->id, 'reply' => $reply];
    }

    protected function participant(WhatsAppGroup $group, array $parsed)
    {
        $phone = isset($parsed['participant_phone']) ? (string) $parsed['participant_phone'] : '';
        if ($phone === '') {
            return;
        }
        $row = WhatsAppGroupParticipant::firstOrNew([
            'group_id' => $group->id,
            'phone' => $phone,
        ]);
        $row->jid = isset($parsed['participant_jid']) ? $parsed['participant_jid'] : $row->jid;
        $row->display_name = isset($parsed['wa_name']) ? $parsed['wa_name'] : $row->display_name;
        $identity = app(WhatsAppIdentityService::class)->resolve($phone);
        foreach ($identity as $match) {
            if (isset($match['role']) && $match['role'] === 'user') {
                $row->user_id = $match['id'];
            }
        }
        $row->save();
        if (WhatsAppOwnerUser::where('normalized_phone', $phone)->exists()) {
            return;
        }
    }
}
