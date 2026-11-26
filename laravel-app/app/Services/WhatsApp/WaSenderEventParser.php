<?php

namespace App\Services\WhatsApp;

class WaSenderEventParser
{
    /**
     * @return array{type:string,provider_event_id:?string,phone:?string,message_id:?string,from_me:bool,body:?string,message_type:string,media:array,status:?string,status_code:?int,wa_name:?string,call:?array,raw:array}
     */
    public function parse(array $payload)
    {
        $event = strtolower((string) ($payload['event'] ?? $payload['type'] ?? ''));
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;
        $parsed = [
            'type' => $event !== '' ? $event : 'unknown',
            'provider_event_id' => null,
            'phone' => null,
            'message_id' => null,
            'from_me' => false,
            'body' => null,
            'message_type' => 'UNKNOWN',
            'media' => [],
            'status' => null,
            'status_code' => null,
            'wa_name' => null,
            'call' => null,
            'is_group' => false,
            'group_jid' => null,
            'participant_phone' => null,
            'participant_jid' => null,
            'reply_to' => null,
            'timestamp' => $this->unixTime(isset($payload['timestamp']) ? $payload['timestamp'] : null),
            'raw' => $payload,
        ];

        if (in_array($event, ['messages-group.received', 'messages.group.received', 'message-group.received'], true)) {
            return $this->parseGroup($parsed, $data, $payload);
        }
        if ($event === 'poll.results') {
            return $this->parsePoll($parsed, $data, $payload);
        }
        if (in_array($event, ['messages.received', 'message.received', 'messages.upsert', 'message.upsert', 'messages-personal.received', 'messages.personal.received'], true)) {
            return $this->parseIncoming($parsed, $data, $payload);
        }
        if ($event === 'message.sent') {
            return $this->parseSent($parsed, $data, $payload);
        }
        if ($event === 'chats.update' || $event === 'chats.upsert' || $event === 'chats.delete') {
            $parsed['type'] = $event;
            $parsed['provider_event_id'] = substr(sha1(json_encode(isset($payload['data']) ? $payload['data'] : [])), 0, 32);

            return $parsed;
        }
        if ($event === 'session.status') {
            $parsed['session_status'] = isset($data['status']) ? strtolower((string) $data['status']) : null;

            return $parsed;
        }
        if ($event === 'messages.update' || $event === 'message.update') {
            return $this->parseStatus($parsed, $data);
        }
        if ($event === 'message-receipt.update' || $event === 'messages.receipt' || $event === 'message.receipt') {
            return $this->parseReceipt($parsed, $data);
        }
        if ($event === 'call' || $event === 'calls.received') {
            return $this->parseCall($parsed, $data, $payload);
        }
        if (in_array($event, ['presence.update', 'presence-update', 'contacts.presence'], true)) {
            return $this->parsePresence($parsed, $data);
        }

        return $parsed;
    }

    public function fingerprint(array $payload, $rawBody = '')
    {
        $parsed = $this->parse($payload);
        $parts = [
            $parsed['type'],
            $parsed['provider_event_id'] ?: '',
            $parsed['message_id'] ?: '',
            $parsed['phone'] ?: '',
            (string) ($payload['timestamp'] ?? ''),
            (string) ($parsed['status_code'] !== null ? $parsed['status_code'] : ''),
            isset($parsed['call']['id']) ? $parsed['call']['id'] : '',
            isset($parsed['group_jid']) ? $parsed['group_jid'] : '',
        ];
        $basis = implode('|', $parts);
        if (trim($basis, '|') === '') {
            $basis = $rawBody !== '' ? $rawBody : json_encode($payload);
        }

        return hash('sha256', $basis);
    }

    protected function parsePresence(array $parsed, array $data)
    {
        $items = isset($data[0]) && is_array($data[0]) ? $data : [$data];
        $states = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $presences = isset($item['presences']) && is_array($item['presences']) ? $item['presences'] : null;
            if ($presences) {
                foreach ($presences as $jid => $row) {
                    $kind = '';
                    if (is_array($row)) {
                        $kind = isset($row['lastKnownPresence']) ? $row['lastKnownPresence'] : (isset($row['presence']) ? $row['presence'] : '');
                    }
                    $states[] = [
                        'jid' => is_string($jid) ? $jid : (isset($item['id']) ? (string) $item['id'] : ''),
                        'kind' => strtolower((string) $kind),
                    ];
                }
                continue;
            }
            $jid = isset($item['jid']) ? $item['jid'] : (isset($item['id']) ? $item['id'] : (isset($item['remoteJid']) ? $item['remoteJid'] : ''));
            $kind = isset($item['lastKnownPresence']) ? $item['lastKnownPresence'] : (isset($item['presence']) ? $item['presence'] : (isset($item['kind']) ? $item['kind'] : (isset($item['type']) ? $item['type'] : '')));
            if ($jid === '' && $kind === '') {
                continue;
            }
            $states[] = ['jid' => (string) $jid, 'kind' => strtolower((string) $kind)];
        }
        $parsed['presence'] = $states;
        $parsed['provider_event_id'] = substr(sha1(json_encode($states).'|'.(string) (isset($parsed['timestamp']) ? $parsed['timestamp'] : '')), 0, 32);

        return $parsed;
    }

    protected function parsePoll(array $parsed, array $data, array $payload)
    {
        $key = isset($data['key']) && is_array($data['key']) ? $data['key'] : [];
        $results = isset($data['pollResult']) && is_array($data['pollResult']) ? $data['pollResult'] : [];
        $choice = null;
        $voter = null;
        foreach ($results as $row) {
            if (! is_array($row) || empty($row['voters']) || ! is_array($row['voters'])) {
                continue;
            }
            $choice = isset($row['name']) ? (string) $row['name'] : null;
            $voter = (string) $row['voters'][count($row['voters']) - 1];
            break;
        }
        $digits = preg_replace('/\D/', '', (string) $voter);
        $parsed['from_me'] = false;
        $parsed['body'] = $choice;
        $parsed['phone'] = $digits !== '' ? $digits : null;
        $parsed['message_type'] = 'POLL';
        $parsed['message_id'] = (isset($key['id']) ? $key['id'] : 'poll').'-'.$digits.'-'.substr(md5((string) $choice), 0, 8);
        $parsed['provider_event_id'] = $parsed['message_id'];

        return $parsed;
    }

    protected function parseIncoming(array $parsed, array $data, array $payload)
    {
        $message = $this->firstMessage($data);
        $key = isset($message['key']) && is_array($message['key']) ? $message['key'] : [];
        $parsed['from_me'] = ! empty($key['fromMe']);
        $parsed['message_id'] = isset($key['id']) ? (string) $key['id'] : null;
        $parsed['provider_event_id'] = $parsed['message_id'] ?: (string) ($payload['timestamp'] ?? '');
        $parsed['phone'] = $this->phoneFromKey($key, $message);
        $parsed['wa_name'] = $this->pushName($message, $data);
        $parsed['body'] = $this->messageBody($message);
        $parsed['message_type'] = $this->messageType($message);
        $parsed['media'] = $this->mediaMeta($message);
        $parsed['timestamp'] = $this->unixTime(isset($message['messageTimestamp']) ? $message['messageTimestamp'] : (isset($payload['timestamp']) ? $payload['timestamp'] : null));

        return $parsed;
    }

    protected function parseGroup(array $parsed, array $data, array $payload)
    {
        $message = $this->firstMessage($data);
        $key = isset($message['key']) && is_array($message['key']) ? $message['key'] : [];
        $parsed['is_group'] = true;
        $parsed['from_me'] = ! empty($key['fromMe']);
        $parsed['message_id'] = isset($key['id']) ? (string) $key['id'] : null;
        $parsed['provider_event_id'] = $parsed['message_id'] ?: (string) ($payload['timestamp'] ?? '');
        $jid = isset($key['remoteJid']) ? (string) $key['remoteJid'] : '';
        $parsed['group_jid'] = substr($jid, -5) === '@g.us' ? $jid : $jid;
        $parsed['group_name'] = isset($message['groupName']) ? $message['groupName'] : (isset($data['groupName']) ? $data['groupName'] : null);
        $parsed['participant_jid'] = isset($key['participant']) ? (string) $key['participant'] : null;
        $participantPhone = null;
        foreach (['cleanedParticipantPn', 'participantPn', 'cleanedSenderPn'] as $field) {
            if (! empty($key[$field]) && strpos((string) $key[$field], '@g.us') === false) {
                $participantPhone = $this->jidToPhone($key[$field]);
                if ($participantPhone) {
                    break;
                }
            }
        }
        if (! $participantPhone && ! empty($parsed['participant_jid'])) {
            $participantPhone = $this->jidToPhone($parsed['participant_jid']);
        }
        $parsed['participant_phone'] = $participantPhone;
        $parsed['phone'] = $participantPhone;
        $parsed['wa_name'] = $this->pushName($message, $data);
        $parsed['body'] = $this->messageBody($message);
        $parsed['message_type'] = $this->messageType($message);
        $parsed['media'] = $this->mediaMeta($message);
        $parsed['reply_to'] = isset($message['message']['extendedTextMessage']['contextInfo']['stanzaId'])
            ? $message['message']['extendedTextMessage']['contextInfo']['stanzaId']
            : null;
        $parsed['timestamp'] = $this->unixTime(isset($message['messageTimestamp']) ? $message['messageTimestamp'] : (isset($payload['timestamp']) ? $payload['timestamp'] : null));

        return $parsed;
    }

    protected function parseStatus(array $parsed, array $data)
    {
        $key = [];
        if (isset($data['key']) && is_array($data['key'])) {
            $key = $data['key'];
        } elseif (isset($data['update']['key']) && is_array($data['update']['key'])) {
            $key = $data['update']['key'];
        }
        $code = null;
        if (isset($data['update']['status'])) {
            $code = (int) $data['update']['status'];
        } elseif (isset($data['status'])) {
            $code = (int) $data['status'];
        }
        $parsed['message_id'] = isset($key['id']) ? (string) $key['id'] : null;
        $parsed['provider_event_id'] = $parsed['message_id'] ? $parsed['message_id'].':'.$code : null;
        $parsed['phone'] = $this->phoneFromKey($key, $data);
        $parsed['from_me'] = ! empty($key['fromMe']);
        $parsed['status_code'] = $code;
        $parsed['status'] = $this->mapStatusCode($code);

        return $parsed;
    }

    protected function parseReceipt(array $parsed, array $data)
    {
        $message = isset($data['message']) && is_array($data['message']) ? $data['message'] : $data;
        $key = isset($message['key']) && is_array($message['key']) ? $message['key'] : [];
        $receipt = isset($message['receipt']) && is_array($message['receipt']) ? $message['receipt'] : [];
        $parsed['message_id'] = isset($key['id']) ? (string) $key['id'] : null;
        $parsed['phone'] = $this->phoneFromKey($key, $message);
        $parsed['from_me'] = ! empty($key['fromMe']);
        $receiptType = strtolower((string) ($receipt['receiptTimestamp'] ?? $data['type'] ?? 'delivered'));
        if (isset($receipt['type'])) {
            $receiptType = strtolower((string) $receipt['type']);
        }
        if (strpos($receiptType, 'read') !== false) {
            $parsed['status'] = 'READ';
            $parsed['status_code'] = 4;
        } else {
            $parsed['status'] = 'DELIVERED';
            $parsed['status_code'] = 3;
        }
        $parsed['provider_event_id'] = ($parsed['message_id'] ?: 'receipt').':'.$parsed['status'];

        return $parsed;
    }

    protected function parseCall(array $parsed, array $data, array $payload)
    {
        $call = isset($data['call']) && is_array($data['call']) ? $data['call'] : $data;
        $id = isset($call['id']) ? (string) $call['id'] : null;
        $from = isset($call['from']) ? (string) $call['from'] : null;
        $parsed['call'] = [
            'id' => $id,
            'from' => $from,
            'is_video' => ! empty($call['isVideo']),
            'status' => isset($call['status']) ? (string) $call['status'] : 'offer',
            'date' => isset($call['date']) ? $call['date'] : ($payload['timestamp'] ?? null),
        ];
        $parsed['provider_event_id'] = $id;
        $parsed['phone'] = $this->jidToPhone($from);

        return $parsed;
    }

    public function mapStatusCode($code)
    {
        $map = [
            0 => 'FAILED',
            1 => 'QUEUED',
            2 => 'SENT',
            3 => 'DELIVERED',
            4 => 'READ',
            5 => 'PLAYED',
        ];

        return isset($map[$code]) ? $map[$code] : null;
    }

    protected function firstMessage(array $data)
    {
        if (isset($data['messages']) && is_array($data['messages'])) {
            if (isset($data['messages']['key']) || isset($data['messages']['message'])) {
                return $data['messages'];
            }
            foreach ($data['messages'] as $item) {
                if (is_array($item)) {
                    return $item;
                }
            }
        }
        if (isset($data['message']) && is_array($data['message']) && isset($data['key'])) {
            return $data;
        }

        return $data;
    }

    protected function phoneFromKey(array $key, array $message)
    {
        foreach (['cleanedSenderPn', 'senderPn', 'remoteJidAlt', 'participantPn', 'remoteJid'] as $field) {
            if (! empty($key[$field])) {
                $phone = $this->phoneFromJid($key[$field]);
                if ($phone) {
                    return $phone;
                }
            }
        }
        if (! empty($message['from'])) {
            return $this->phoneFromJid($message['from']);
        }

        return null;
    }

    /**
     * A real phone number. Linked-device ids (@lid) and groups are not phone numbers.
     */
    public function phoneFromJid($jid)
    {
        $raw = (string) $jid;
        if ($raw === '' || substr($raw, -4) === '@lid' || substr($raw, -5) === '@g.us' || strpos($raw, '@broadcast') !== false) {
            return null;
        }

        return $this->jidToPhone($raw);
    }

    /**
     * @return array<int, array>
     */
    public function chatUpdates(array $payload)
    {
        $data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
        if (isset($data['chats']) && is_array($data['chats'])) {
            $chats = $data['chats'];
            if (isset($chats['id']) || array_key_exists('unreadCount', $chats) || isset($chats['messages'])) {
                $chats = [$chats];
            }
        } elseif (isset($data['id']) || array_key_exists('unreadCount', $data) || isset($data['messages'])) {
            $chats = [$data];
        } else {
            // Wasender sends data as a list: [{id, unreadCount, conversationTimestamp}].
            $chats = $data;
        }
        $rows = [];
        foreach ($chats as $chat) {
            if (! is_array($chat)) {
                continue;
            }
            $row = $this->parseChatRow($chat, $payload);
            if (! empty($row['phone']) || $row['unread_count'] !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    protected function parseChatRow(array $chat, array $payload)
    {
        $wrapped = null;
        if (isset($chat['messages'][0]['message']) && is_array($chat['messages'][0]['message'])) {
            $wrapped = $chat['messages'][0]['message'];
        } elseif (isset($chat['messages']['key']) || isset($chat['messages']['message'])) {
            $wrapped = $chat['messages'];
        }
        $key = isset($wrapped['key']) && is_array($wrapped['key']) ? $wrapped['key'] : [];
        $jid = '';
        if (! empty($chat['id'])) {
            $jid = (string) $chat['id'];
        } elseif (! empty($chat['jid'])) {
            $jid = (string) $chat['jid'];
        }
        $phone = $wrapped ? $this->phoneFromKey($key, $wrapped) : null;
        if (! $phone && $jid !== '' && substr($jid, -4) !== '@lid') {
            $phone = $this->phoneFromJid($jid);
        }
        $stamp = isset($chat['conversationTimestamp']) ? $chat['conversationTimestamp'] : (isset($wrapped['messageTimestamp']) ? $wrapped['messageTimestamp'] : (isset($payload['timestamp']) ? $payload['timestamp'] : null));

        return [
            'phone' => $phone,
            'jid' => $jid,
            'from_me' => ! empty($key['fromMe']),
            'message_id' => isset($key['id']) ? (string) $key['id'] : null,
            'wa_name' => $wrapped ? $this->pushName($wrapped, $chat) : (isset($chat['name']) ? (string) $chat['name'] : null),
            'body' => $wrapped ? $this->messageBody($wrapped) : null,
            'message_type' => $wrapped ? $this->messageType($wrapped) : 'TEXT',
            'media' => $wrapped ? $this->mediaMeta($wrapped) : [],
            'unread_count' => array_key_exists('unreadCount', $chat) ? (int) $chat['unreadCount'] : null,
            'timestamp' => $this->unixTime($stamp),
        ];
    }

    protected function parseSent(array $parsed, array $data, array $payload)
    {
        $result = isset($data['result']) && is_array($data['result']) ? $data['result'] : $data;
        $key = isset($result['key']) && is_array($result['key']) ? $result['key'] : [];
        $parsed['from_me'] = true;
        $parsed['message_id'] = isset($key['id']) ? (string) $key['id'] : (isset($data['msgId']) ? (string) $data['msgId'] : null);
        $parsed['provider_event_id'] = $parsed['message_id'];
        $parsed['phone'] = $this->phoneFromKey($key, $result);
        if (! $parsed['phone'] && ! empty($data['jid'])) {
            $parsed['phone'] = $this->phoneFromJid($data['jid']);
        }
        $parsed['body'] = $this->messageBody($result);
        $parsed['message_type'] = $this->messageType($result);
        $parsed['media'] = $this->mediaMeta($result);
        $parsed['timestamp'] = $this->unixTime(isset($result['messageTimestamp']) ? $result['messageTimestamp'] : (isset($payload['timestamp']) ? $payload['timestamp'] : null));

        return $parsed;
    }

    public function unixTime($value)
    {
        if (is_array($value) && isset($value['low'])) {
            $value = $value['low'];
        }
        if (! is_numeric($value)) {
            return null;
        }
        $stamp = (int) $value;
        if ($stamp > 1000000000000) {
            $stamp = (int) floor($stamp / 1000);
        }

        return $stamp > 1000000000 ? $stamp : null;
    }

    public function jidToPhone($jid)
    {
        $raw = (string) $jid;
        $raw = preg_replace('/@.+$/', '', $raw);
        $raw = preg_replace('/:.+$/', '', $raw);

        return $raw !== '' ? $raw : null;
    }

    protected function pushName(array $message, array $data)
    {
        foreach (['pushName', 'notifyName', 'verifiedBizName'] as $key) {
            if (! empty($message[$key])) {
                return (string) $message[$key];
            }
            if (! empty($data[$key])) {
                return (string) $data[$key];
            }
        }

        return null;
    }

    protected function messageBody(array $message)
    {
        if (! empty($message['messageBody'])) {
            return (string) $message['messageBody'];
        }
        $inner = $this->contentMessage($message);
        if (! empty($inner['conversation'])) {
            return (string) $inner['conversation'];
        }
        if (! empty($inner['extendedTextMessage']['text'])) {
            return (string) $inner['extendedTextMessage']['text'];
        }
        foreach (['imageMessage', 'documentMessage', 'videoMessage', 'audioMessage', 'stickerMessage'] as $kind) {
            if (! empty($inner[$kind]['caption'])) {
                return (string) $inner[$kind]['caption'];
            }
        }

        return null;
    }

    protected function contentMessage(array $message)
    {
        $inner = isset($message['message']) && is_array($message['message']) ? $message['message'] : $message;
        $wraps = ['viewOnceMessage', 'viewOnceMessageV2', 'ephemeralMessage', 'documentWithCaptionMessage', 'editedMessage', 'associatedChildMessage'];
        foreach ($wraps as $wrap) {
            if (isset($inner[$wrap]['message']) && is_array($inner[$wrap]['message'])) {
                return $this->contentMessage(['message' => $inner[$wrap]['message']]);
            }
        }

        return is_array($inner) ? $inner : [];
    }

    protected function messageType(array $message)
    {
        $inner = $this->contentMessage($message);
        if (! empty($inner['conversation']) || ! empty($inner['extendedTextMessage'])) {
            return 'TEXT';
        }
        if (! empty($inner['imageMessage'])) {
            return 'IMAGE';
        }
        if (! empty($inner['stickerMessage'])) {
            return 'STICKER';
        }
        if (! empty($inner['albumMessage'])) {
            return 'ALBUM';
        }
        if (! empty($inner['secretEncryptedMessage'])) {
            $kind = isset($inner['secretEncryptedMessage']['secretEncType']) ? strtoupper((string) $inner['secretEncryptedMessage']['secretEncType']) : '';
            if (strpos($kind, 'VIEW') !== false) {
                return 'IMAGE';
            }

            return 'EDIT';
        }
        if (! empty($inner['documentMessage'])) {
            return 'DOCUMENT';
        }
        if (! empty($inner['audioMessage'])) {
            return 'AUDIO';
        }
        if (! empty($inner['videoMessage'])) {
            return 'VIDEO';
        }
        if (! empty($inner['locationMessage']) || ! empty($inner['liveLocationMessage'])) {
            return 'LOCATION';
        }
        if (! empty($message['messageBody'])) {
            return 'TEXT';
        }

        return 'UNKNOWN';
    }

    protected function mediaMeta(array $message)
    {
        $inner = $this->contentMessage($message);
        foreach (['imageMessage' => 'IMAGE', 'stickerMessage' => 'STICKER', 'documentMessage' => 'DOCUMENT', 'audioMessage' => 'AUDIO', 'videoMessage' => 'VIDEO'] as $key => $type) {
            if (! empty($inner[$key]) && is_array($inner[$key])) {
                $block = $inner[$key];
                $meta = [
                    'type' => $type,
                    'mimetype' => isset($block['mimetype']) ? $block['mimetype'] : null,
                    'file_name' => isset($block['fileName']) ? $block['fileName'] : null,
                    'file_length' => isset($block['fileLength']) ? $block['fileLength'] : null,
                    'url' => isset($block['url']) ? $block['url'] : null,
                ];
                if (! empty($block['viewOnce'])) {
                    $meta['view_once'] = true;
                }

                return $meta;
            }
        }
        if (! empty($inner['locationMessage']) && is_array($inner['locationMessage'])) {
            $loc = $inner['locationMessage'];

            return [
                'type' => 'LOCATION',
                'latitude' => isset($loc['degreesLatitude']) ? $loc['degreesLatitude'] : null,
                'longitude' => isset($loc['degreesLongitude']) ? $loc['degreesLongitude'] : null,
            ];
        }

        return [];
    }
}
