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
            'timestamp' => isset($payload['timestamp']) ? $payload['timestamp'] : null,
            'raw' => $payload,
        ];

        if (in_array($event, ['messages-group.received', 'messages.group.received', 'message-group.received'], true)) {
            return $this->parseGroup($parsed, $data, $payload);
        }
        if ($event === 'poll.results') {
            return $this->parsePoll($parsed, $data, $payload);
        }
        if (in_array($event, ['messages.received', 'message.received', 'messages.upsert', 'message.upsert'], true)) {
            return $this->parseIncoming($parsed, $data, $payload);
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
        $parsed['timestamp'] = isset($payload['timestamp']) ? $payload['timestamp'] : (isset($message['messageTimestamp']) ? $message['messageTimestamp'] : null);

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
        foreach (['cleanedSenderPn', 'senderPn'] as $field) {
            if (! empty($key[$field])) {
                $phone = $this->jidToPhone($key[$field]);
                if ($phone) {
                    return $phone;
                }
            }
        }
        if (! empty($key['remoteJid'])) {
            return $this->jidToPhone($key['remoteJid']);
        }
        if (! empty($message['from'])) {
            return $this->jidToPhone($message['from']);
        }

        return null;
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
        $inner = isset($message['message']) && is_array($message['message']) ? $message['message'] : $message;
        if (! empty($inner['conversation'])) {
            return (string) $inner['conversation'];
        }
        if (! empty($inner['extendedTextMessage']['text'])) {
            return (string) $inner['extendedTextMessage']['text'];
        }
        foreach (['imageMessage', 'documentMessage', 'videoMessage', 'audioMessage'] as $kind) {
            if (! empty($inner[$kind]['caption'])) {
                return (string) $inner[$kind]['caption'];
            }
        }

        return null;
    }

    protected function messageType(array $message)
    {
        $inner = isset($message['message']) && is_array($message['message']) ? $message['message'] : $message;
        if (! empty($inner['conversation']) || ! empty($inner['extendedTextMessage'])) {
            return 'TEXT';
        }
        if (! empty($inner['imageMessage'])) {
            return 'IMAGE';
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
        $inner = isset($message['message']) && is_array($message['message']) ? $message['message'] : $message;
        foreach (['imageMessage' => 'IMAGE', 'documentMessage' => 'DOCUMENT', 'audioMessage' => 'AUDIO', 'videoMessage' => 'VIDEO'] as $key => $type) {
            if (! empty($inner[$key]) && is_array($inner[$key])) {
                $block = $inner[$key];

                return [
                    'type' => $type,
                    'mimetype' => isset($block['mimetype']) ? $block['mimetype'] : null,
                    'file_name' => isset($block['fileName']) ? $block['fileName'] : null,
                    'file_length' => isset($block['fileLength']) ? $block['fileLength'] : null,
                    'url' => isset($block['url']) ? $block['url'] : null,
                ];
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
