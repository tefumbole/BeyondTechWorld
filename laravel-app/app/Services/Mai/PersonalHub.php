<?php

namespace App\Services\Mai;

use App\Mai\MaiSession;
use App\Services\Cloud\CloudTenantContext;
use App\Services\Cloud\MaiWhatsAppEngine;
use App\User;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use Carbon\Carbon;

/**
 * The phone linked at signup is the same hub as BeyondTechWorld.
 * Chats are stored on that session, never on the business line.
 */
class PersonalHub
{
    public function ensure(User $user)
    {
        $digits = preg_replace('/\D/', '', (string) $user->phone);
        if (! preg_match('/^[1-9][0-9]{7,14}$/', $digits) || MaiBusinessLine::is($digits)) {
            return null;
        }
        $worker = 'p'.$digits;
        $session = MaiSession::where('worker_session_id', $worker)->first();
        if ($session && (int) $session->user_id !== (int) $user->id) {
            return null;
        }
        if (! $session) {
            $session = new MaiSession();
            $session->public_id = 'c'.bin2hex(random_bytes(16));
            $session->user_id = $user->id;
            $session->provider = MaiProviderRouter::MAI;
            $session->worker_session_id = $worker;
            $session->ai_enabled = 0;
        }
        $session->expected_phone = $digits;
        $session->phone_number = $digits;
        if (! $session->exists) {
            $session->status = 'PENDING';
        }
        $session->save();

        return $session;
    }

    public function sync(MaiSession $session, $lookupNames = true)
    {
        $phone = $session->phone_number ?: $session->expected_phone;
        $engine = app(MaiWhatsAppEngine::class);
        $outbound = app(MaiOutboundService::class);
        app(CloudTenantContext::class)->withoutIsolation(function () use ($session, $phone, $engine, $outbound, $lookupNames) {
            $lookups = 0;
            foreach ($engine->chats($phone) as $chat) {
                if (substr($chat['jid'], -5) === '@g.us' || $chat['phone'] === '' || strlen($chat['phone']) < 8 || strlen($chat['phone']) > 15) {
                    continue;
                }
                $conversation = $outbound->conversation($session, $chat['phone']);
                $contact = $conversation->contact;
                $saved = $this->savedName($chat['name'], $chat['phone']);
                if ($lookupNames && $saved === '' && strpos($chat['phone'], '237') === 0 && strlen($chat['phone']) === 12 && $lookups < 8) {
                    $lookups++;
                    try {
                        $found = app(\App\Services\MobileMoneyHolderService::class)->lookup($chat['phone'], false, true);
                        if (! empty($found['name'])) {
                            $saved = $this->savedName($found['name'], $chat['phone']);
                        }
                    } catch (\Throwable $e) {
                    }
                }
                $shared = $contact && WhatsAppConversation::where('contact_id', $contact->id)->whereNull('mai_session_id')->exists();
                if ($contact && $saved !== '' && ! $shared) {
                    $current = $this->savedName($contact->wa_name, $chat['phone']);
                    if ($current === '' || $current !== $saved) {
                        $contact->wa_name = function_exists('mb_substr') ? mb_substr($saved, 0, 191) : substr($saved, 0, 191);
                    }
                }
                if ($contact) {
                    $shown = \App\Support\WhatsAppPhone::display($chat['phone']);
                    if ($shown !== '' && $contact->display_phone !== $shown) {
                        $contact->display_phone = $shown;
                    }
                    $contact->save();
                }
                if ($chat['preview'] !== '') {
                    $conversation->last_message = $chat['preview'];
                }
                $conversation->unread_count = (int) $chat['unread'];
                $at = (int) $chat['at'];
                if ($at > 1000000000000) {
                    $at = (int) floor($at / 1000);
                }
                if ($at > 0) {
                    $when = Carbon::createFromTimestamp($at);
                    if (! $conversation->last_activity_at || $when->gt($conversation->last_activity_at)) {
                        $conversation->last_activity_at = $when;
                    }
                }
                $conversation->save();
            }
            foreach ($engine->messages($phone) as $row) {
                $from = (string) $row['from'];
                if (substr($from, -5) === '@g.us') {
                    continue;
                }
                $digits = preg_replace('/\D/', '', strtok($from, '@'));
                if ($digits === '' || strlen($digits) < 8 || strlen($digits) > 15 || substr($from, -4) === '@lid') {
                    continue;
                }
                $conversation = $outbound->conversation($session, $digits);
                $key = 'mai-live:'.$session->id.':'.substr(sha1($from.'|'.$row['text'].'|'.$row['at']), 0, 40);
                if (WhatsAppMessage::where('provider_message_id', $key)->exists()) {
                    continue;
                }
                $stamp = is_numeric($row['at']) ? Carbon::createFromTimestamp((int) $row['at']) : Carbon::now();
                $message = new WhatsAppMessage();
                $message->timestamps = false;
                $message->conversation_id = $conversation->id;
                $message->contact_id = $conversation->contact_id;
                $message->direction = ! empty($row['from_me']) ? WhatsAppMessage::DIR_OUT : WhatsAppMessage::DIR_IN;
                $message->type = 'TEXT';
                $message->provider_message_id = $key;
                $message->body = $row['text'];
                $message->status = WhatsAppMessage::STATUS_DELIVERED;
                $message->sender_type = ! empty($row['from_me']) ? 'ECHO' : 'CONTACT';
                $message->created_at = $stamp;
                $message->updated_at = $stamp;
                $message->save();
            }
        });
    }

    public function groups(MaiSession $session)
    {
        $phone = $session->phone_number ?: $session->expected_phone;
        $groups = [];
        foreach (app(MaiWhatsAppEngine::class)->chats($phone) as $chat) {
            if (substr($chat['jid'], -5) !== '@g.us') {
                continue;
            }
            $groups[] = [
                'jid' => $chat['jid'],
                'name' => $chat['name'] !== '' ? $chat['name'] : 'WhatsApp group',
                'members' => null,
            ];
        }

        return $groups;
    }

    protected function savedName($name, $phone)
    {
        $name = trim((string) $name);
        $digits = preg_replace('/\D/', '', $name);
        $phone = preg_replace('/\D/', '', (string) $phone);
        if ($name === '' || $digits === $phone || $name === '+'.$phone) {
            return '';
        }

        return $name;
    }
}
