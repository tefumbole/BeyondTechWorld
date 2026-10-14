<?php

namespace App\Services;

use App\Support\WhatsAppPhone;
use Illuminate\Support\Facades\Cache;
use Twilio\Rest\Client;

class TwilioWhatsAppService
{
    /** Cache: yes = balance remains, no = credit is finished, unknown = balance check failed. */
    const CREDIT_CACHE_KEY = 'twilio_whatsapp_credit_state';

    public function isConfigured()
    {
        $sid = $this->accountSid();
        $token = $this->authToken();
        $from = $this->fromNumber();

        return $sid !== '' && $token !== '' && $from !== ''
            && strpos($sid, 'your_') !== 0;
    }

    public function accountSid()
    {
        return trim((string) (
            config('services.whatsapp.twilio_sid')
            ?: config('services.sms.account_sid')
            ?: env('TWILIO_SID')
            ?: env('ACCOUNT_SID')
        ));
    }

    public function authToken()
    {
        return trim((string) (
            config('services.whatsapp.twilio_token')
            ?: config('services.sms.auth_token')
            ?: env('TWILIO_AUTH_TOKEN')
            ?: env('AUTH_TOKEN')
        ));
    }

    public function fromNumber()
    {
        $from = trim((string) config('services.whatsapp.twilio_whatsapp_from', env('TWILIO_WHATSAPP_FROM', '')));
        if ($from === '') {
            return '';
        }

        if (stripos($from, 'whatsapp:') === 0) {
            return $from;
        }

        $digits = preg_replace('/\D/', '', $from);

        return $digits !== '' ? 'whatsapp:+'.$digits : $from;
    }

    public function displayNumber()
    {
        $from = $this->fromNumber();
        $digits = preg_replace('/\D/', '', $from);

        return $digits !== '' ? '+'.$digits : '';
    }

    /**
     * WhatsApp approval status for a Content SID: approved, received, rejected, or empty.
     */
    public function contentApprovalStatus($contentSid)
    {
        $contentSid = trim((string) $contentSid);
        $sid = $this->accountSid();
        $token = $this->authToken();
        if ($contentSid === '' || $sid === '' || $token === '') {
            return '';
        }

        try {
            return \Illuminate\Support\Facades\Cache::remember('twilio_content_approval_'.$contentSid, 180, function () use ($contentSid, $sid, $token) {
                $ch = curl_init('https://content.twilio.com/v1/Content/'.$contentSid.'/ApprovalRequests');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_USERPWD => $sid.':'.$token,
                    CURLOPT_TIMEOUT => 15,
                ]);
                $raw = curl_exec($ch);
                curl_close($ch);
                $json = json_decode((string) $raw, true);
                $wa = is_array($json) && isset($json['whatsapp']) ? $json['whatsapp'] : [];

                return strtolower((string) ($wa['status'] ?? ''));
            });
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * @param  string  $to
     * @param  string  $contentSid
     * @param  array<int|string,string>  $variables  keyed 1..n or "1".."n"
     * @param  string|null  $mediaUrl
     * @return array{success:bool,sid?:string,error?:string}
     */
    public function sendContentTemplate($to, $contentSid, array $variables = [], $mediaUrl = null)
    {
        $contentSid = trim((string) $contentSid);
        if ($contentSid === '') {
            return ['success' => false, 'error' => 'Twilio Content SID is missing.'];
        }

        if (! filter_var(config('services.whatsapp.twilio_templates', true), FILTER_VALIDATE_BOOLEAN)) {
            return ['success' => false, 'error' => 'Twilio WhatsApp templates are paused.'];
        }

        if (! $this->isConfigured()) {
            \Log::warning('[twilio-whatsapp] not configured (SID/token/from)');

            return ['success' => false, 'error' => 'Twilio WhatsApp is not configured.'];
        }

        try {
            $recipient = $this->formatWhatsAppAddress($to);
            $payload = [
                'from' => $this->fromNumber(),
                'contentSid' => $contentSid,
            ];

            $vars = $this->normalizeVariables($variables);
            if (! empty($vars)) {
                $payload['contentVariables'] = json_encode($vars);
            }

            if (! empty($mediaUrl)) {
                $payload['mediaUrl'] = [$mediaUrl];
            }

            $client = new Client($this->accountSid(), $this->authToken());
            $message = $client->messages->create($recipient, $payload);
            \App\Support\TwilioAdminCopy::mirrorTemplate($to, $contentSid, $variables, $mediaUrl);

            return ['success' => true, 'sid' => $message->sid];
        } catch (\Throwable $e) {
            \Log::warning('[twilio-whatsapp] send failed', [
                'error' => $e->getMessage(),
                'content_sid' => $contentSid,
            ]);

            return $this->failure($e);
        }
    }

    /**
     * Free-form body (only works inside an open customer care session).
     *
     * @return array{success:bool,sid?:string,error?:string}
     */
    public function sendText($to, $body)
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Twilio WhatsApp is not configured.'];
        }

        try {
            $client = new Client($this->accountSid(), $this->authToken());
            $message = $client->messages->create($this->formatWhatsAppAddress($to), [
                'from' => $this->fromNumber(),
                'body' => (string) $body,
            ]);

            return ['success' => true, 'sid' => $message->sid];
        } catch (\Throwable $e) {
            \Log::warning('[twilio-whatsapp] free-form send failed', ['error' => $e->getMessage()]);

            return $this->failure($e);
        }
    }

    /**
     * True when the Twilio balance is empty or a send was refused for lack of credit.
     * A failed balance lookup does not count as empty, so a later send can still try Twilio.
     */
    public function creditIsExhausted()
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $cached = Cache::get(self::CREDIT_CACHE_KEY);
        if ($cached === 'no') {
            return true;
        }
        if ($cached === 'yes' || $cached === 'unknown') {
            return false;
        }

        $balance = $this->fetchAccountBalance();
        if ($balance === null) {
            Cache::put(self::CREDIT_CACHE_KEY, 'unknown', now()->addMinute());

            return false;
        }
        if ($balance <= 0) {
            $this->markCreditExhausted('Twilio balance is '.$balance);

            return true;
        }

        Cache::put(self::CREDIT_CACHE_KEY, 'yes', now()->addMinutes(2));

        return false;
    }

    public function markCreditExhausted($reason = '')
    {
        Cache::put(self::CREDIT_CACHE_KEY, 'no', now()->addMinutes(20));
        if (Cache::add(self::CREDIT_CACHE_KEY.'_logged', 1, now()->addMinutes(20))) {
            \Log::warning('[twilio-whatsapp] credit is finished — remaining WhatsApp continues on Wasender', [
                'reason' => trim((string) $reason) !== '' ? $reason : 'balance',
            ]);
        }
    }

    public function isCreditError($error, $code = 0)
    {
        $code = (int) $code;
        if (in_array($code, [20005, 30002], true)) {
            return true;
        }

        $text = strtolower((string) $error);
        if (preg_match('/\b(20005|30002)\b/', $text)) {
            return true;
        }

        foreach ([
            'insufficient',
            'sufficient fund',
            'account balance',
            'low balance',
            'no funds',
            'out of credit',
            'account not active',
            'account is not active',
            'account suspended',
            'account is suspended',
        ] as $needle) {
            if (strpos($text, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{success:bool,error:string,code:int}
     */
    protected function failure(\Throwable $e)
    {
        $code = (int) $e->getCode();
        $error = $e->getMessage();
        if ($this->isCreditError($error, $code)) {
            $this->markCreditExhausted($error);
        }

        return ['success' => false, 'error' => $error, 'code' => $code];
    }

    /**
     * @return float|null
     */
    protected function fetchAccountBalance()
    {
        $sid = $this->accountSid();
        $token = $this->authToken();
        if ($sid === '' || $token === '') {
            return null;
        }

        try {
            $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode($sid).'/Balance.json');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_USERPWD => $sid.':'.$token,
                CURLOPT_TIMEOUT => 12,
            ]);
            $raw = curl_exec($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($http < 200 || $http >= 300) {
                return null;
            }
            $json = json_decode((string) $raw, true);
            if (! is_array($json) || ! isset($json['balance']) || ! is_numeric($json['balance'])) {
                return null;
            }

            return (float) $json['balance'];
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function formatWhatsAppAddress($phone)
    {
        $normalized = WhatsAppPhone::normalize($phone);

        return 'whatsapp:+'.$normalized;
    }

    /**
     * Recent WhatsApp messages for the configured sender. Read-only.
     *
     * @return array{ok:bool,error:string,number:string,messages:array<int,array<string,string>>,next:string}
     */
    public function whatsAppLog($direction = 'replies', $pageToken = '')
    {
        $sid = $this->accountSid();
        $token = $this->authToken();
        $from = $this->fromNumber();
        $empty = [
            'ok' => false,
            'error' => 'Twilio WhatsApp is not configured.',
            'number' => $this->displayNumber(),
            'messages' => [],
            'next' => '',
        ];
        if ($sid === '' || $token === '' || $from === '') {
            return $empty;
        }

        $direction = in_array($direction, ['replies', 'sent', 'all'], true) ? $direction : 'replies';
        $tokenOk = is_string($pageToken) && preg_match('/^[A-Za-z0-9\-_]{8,200}$/', $pageToken);

        if ($direction === 'all') {
            $incoming = $this->fetchMessagePage($sid, $token, ['To' => $from, 'PageSize' => 30]);
            $outgoing = $this->fetchMessagePage($sid, $token, ['From' => $from, 'PageSize' => 30]);
            if (! $incoming['ok'] && ! $outgoing['ok']) {
                $incoming['number'] = $this->displayNumber();

                return $incoming;
            }
            $rows = $this->orientMessages(array_merge($incoming['messages'], $outgoing['messages']));
            usort($rows, function ($a, $b) {
                return strcmp($b['sort'], $a['sort']);
            });

            return [
                'ok' => true,
                'error' => '',
                'number' => $this->displayNumber(),
                'messages' => array_slice($rows, 0, 50),
                'next' => '',
            ];
        }

        $query = ['PageSize' => 40];
        if ($direction === 'sent') {
            $query['From'] = $from;
        } else {
            $query['To'] = $from;
        }
        if ($tokenOk) {
            $query['PageToken'] = $pageToken;
        }
        $page = $this->fetchMessagePage($sid, $token, $query);
        $page['messages'] = $this->orientMessages($page['messages']);
        $page['number'] = $this->displayNumber();

        return $page;
    }

    /**
     * Conversations for the chat screen, newest chat first.
     *
     * @return array{ok:bool,error:string,number:string,chats:array<int,array<string,mixed>>}
     */
    public function whatsAppInbox()
    {
        $cached = Cache::get('twilio_whatsapp_inbox');
        if (is_array($cached) && ! empty($cached['ok']) && ! empty($cached['sent'])) {
            return $cached;
        }

        $built = $this->buildWhatsAppInbox();
        if (! empty($built['ok']) && (! empty($built['sent']) || ! empty($built['chats']))) {
            Cache::put('twilio_whatsapp_inbox', $built, now()->addSeconds(20));
        }

        return $built;
    }

    public function forgetWhatsAppInbox()
    {
        Cache::forget('twilio_whatsapp_inbox');
    }

    /**
     * @return array{ok:bool,error:string,number:string,chats:array<int,array<string,mixed>>}
     */
    protected function buildWhatsAppInbox()
    {
        $sid = $this->accountSid();
        $token = $this->authToken();
        $from = $this->fromNumber();
        $empty = [
            'ok' => false,
            'error' => 'Twilio WhatsApp is not configured.',
            'number' => $this->displayNumber(),
            'chats' => [],
            'sent' => [],
        ];
        if ($sid === '' || $token === '' || $from === '') {
            return $empty;
        }

        $lines = $this->lineDigits();
        $collected = [];
        $anyOk = false;
        $error = '';
        $pull = function ($address, array $sides, $pages) use ($sid, $token, &$collected, &$anyOk, &$error) {
            foreach ($sides as $side) {
                $page = $this->fetchMessagePages($sid, $token, [$side => $address, 'PageSize' => 100], $pages);
                if (empty($page['ok'])) {
                    if ($error === '' && ! empty($page['error'])) {
                        $error = $page['error'];
                    }
                    continue;
                }
                $anyOk = true;
                foreach ($page['messages'] as $row) {
                    if (! empty($row['sid'])) {
                        $collected[$row['sid']] = $row;
                    }
                }
            }
        };

        $digits = preg_replace('/\D/', '', $from);
        if ($digits !== '') {
            $pull('whatsapp:+'.$digits, ['From', 'To'], 6);
            if (count($collected) === 0) {
                $pull('+'.$digits, ['From', 'To'], 4);
            }
        }
        $sms = preg_replace('/\D/', '', (string) config('services.sms.twilio_number', ''));
        if ($sms !== '' && $sms !== $digits && count($collected) === 0) {
            $pull('+'.$sms, ['From', 'To'], 3);
            $pull('whatsapp:+'.$sms, ['From', 'To'], 3);
        } elseif ($sms !== '' && $sms !== $digits) {
            $pull('+'.$sms, ['From'], 2);
        }

        if (count($collected) === 0 && $anyOk) {
            $scan = $this->fetchMessagePages($sid, $token, ['PageSize' => 100], 8);
            if (! empty($scan['ok'])) {
                $anyOk = true;
                $lines = $this->inferBusinessLines($scan['messages'], $lines);
                foreach ($scan['messages'] as $row) {
                    if (! empty($row['sid'])) {
                        $collected[$row['sid']] = $row;
                    }
                }
            } elseif ($error === '' && ! empty($scan['error'])) {
                $error = $scan['error'];
            }
        }

        if (! $anyOk && count($collected) === 0) {
            return [
                'ok' => false,
                'error' => $error !== '' ? $error : 'Twilio log could not be loaded.',
                'number' => $this->displayNumber(),
                'chats' => [],
                'sent' => [],
            ];
        }

        $oriented = [];
        foreach ($collected as $row) {
            $ready = $this->orientRow($row, $lines);
            if ($ready === null) {
                continue;
            }
            $oriented[] = $ready;
        }

        $chats = $this->groupChats($oriented);
        $sent = [];
        foreach ($oriented as $message) {
            if ($message['out'] === '1') {
                $sent[] = $message;
            }
        }
        usort($sent, function ($a, $b) {
            return strcmp($b['sort'], $a['sort']);
        });

        return [
            'ok' => true,
            'error' => '',
            'number' => $this->displayNumber(),
            'chats' => $chats,
            'sent' => $sent,
        ];
    }

    /**
     * @return array<int,string>
     */
    protected function lineDigits()
    {
        $lines = [];
        foreach ([$this->fromNumber(), (string) config('services.sms.twilio_number', '')] as $value) {
            $digits = preg_replace('/\D/', '', $value);
            if ($digits !== '' && ! in_array($digits, $lines, true)) {
                $lines[] = $digits;
            }
        }

        return $lines;
    }

    /**
     * When the saved sender does not appear on any row, use the WhatsApp number
     * that sent the most messages in this account.
     *
     * @param  array<int,array<string,string>>  $rows
     * @param  array<int,string>  $configured
     * @return array<int,string>
     */
    protected function inferBusinessLines(array $rows, array $configured)
    {
        foreach ($rows as $row) {
            if (in_array($row['from_digits'], $configured, true) || in_array($row['to_digits'], $configured, true)) {
                return $configured;
            }
        }

        $counts = [];
        foreach ($rows as $row) {
            if (($row['channel'] ?? '') !== 'whatsapp' || $row['from_digits'] === '') {
                continue;
            }
            $counts[$row['from_digits']] = (isset($counts[$row['from_digits']]) ? $counts[$row['from_digits']] : 0) + 1;
        }
        if (count($counts) === 0) {
            return $configured;
        }
        arsort($counts);

        return [key($counts)];
    }

    /**
     * @param  array<int,array<string,string>>  $messages
     * @return array<int,array<string,mixed>>
     */
    protected function groupChats(array $messages)
    {
        $grouped = [];
        foreach ($messages as $row) {
            $digits = isset($row['digits']) ? $row['digits'] : '';
            if ($digits === '') {
                continue;
            }
            if (! isset($grouped[$digits])) {
                $grouped[$digits] = [];
            }
            $grouped[$digits][] = $row;
        }

        $chats = [];
        $today = date('Y-m-d');
        foreach ($grouped as $digits => $rows) {
            usort($rows, function ($a, $b) {
                return strcmp($a['sort'], $b['sort']);
            });
            $last = $rows[count($rows) - 1];
            $preview = $this->displayBody($last);
            $chats[] = [
                'digits' => $digits,
                'phone' => $last['who'],
                'preview' => $preview,
                'list_time' => $last['day'] === $today ? $last['time'] : ($last['sort'] !== '' ? date('M j', strtotime($last['sort'])) : ''),
                'sort' => $last['sort'],
                'unread' => $last['direction'] === 'Reply' ? 1 : 0,
                'messages' => $rows,
            ];
        }
        usort($chats, function ($a, $b) {
            return strcmp($b['sort'], $a['sort']);
        });

        return $chats;
    }

    /**
     * @param  array<string,string>  $row
     */
    protected function displayBody(array $row)
    {
        $body = trim((string) (isset($row['body']) ? $row['body'] : ''));
        if ($body !== '') {
            return $body;
        }
        if ((int) (isset($row['media']) ? $row['media'] : 0) > 0) {
            return 'Attachment';
        }

        return 'Template message';
    }

    /**
     * @param  array<int,array<string,string>>  $rows
     * @return array<int,array<string,string>>
     */
    protected function orientMessages(array $rows)
    {
        $ready = [];
        foreach ($rows as $row) {
            $oriented = $this->orientRow($row, $this->lineDigits());
            if ($oriented !== null) {
                $ready[] = $oriented;
            }
        }

        return $ready;
    }

    /**
     * @param  array<string,string>  $row
     * @param  array<int,string>  $lines
     * @return array<string,string>|null
     */
    protected function orientRow(array $row, array $lines)
    {
        $ours = array_flip($lines);
        $fromOurs = $row['from_digits'] !== '' && isset($ours[$row['from_digits']]);
        $toOurs = $row['to_digits'] !== '' && isset($ours[$row['to_digits']]);
        if (! $fromOurs && ! $toOurs) {
            return null;
        }

        $inbound = $toOurs && ! $fromOurs;
        $otherDigits = $inbound ? $row['from_digits'] : $row['to_digits'];
        $otherDisplay = $inbound ? $row['from_display'] : $row['to_display'];
        if ($otherDigits === '') {
            return null;
        }

        $status = $row['status'];
        $error = $row['error_code'];
        $tick = '';
        if (! $inbound) {
            if (in_array($status, ['failed', 'undelivered'], true) || $error !== '') {
                $tick = 'failed';
            } elseif ($status === 'read') {
                $tick = 'read';
            } elseif ($status === 'delivered') {
                $tick = 'delivered';
            } else {
                $tick = 'sent';
            }
        }

        return [
            'sid' => $row['sid'],
            'sort' => $row['sort'],
            'when' => $row['when'],
            'time' => $row['time'],
            'day' => $row['day'],
            'direction' => $inbound ? 'Reply' : 'Sent',
            'out' => $inbound ? '0' : '1',
            'who' => $otherDisplay !== '' ? $otherDisplay : '+'.$otherDigits,
            'digits' => $otherDigits,
            'status' => $status,
            'tick' => $tick,
            'body' => $row['body'],
            'text' => $this->displayBody($row),
            'error_code' => $error,
            'media' => $row['media'],
            'channel' => $row['channel'],
        ];
    }

    /**
     * @param  array<string,int|string>  $query
     * @return array{ok:bool,error:string,messages:array<int,array<string,string>>,next_uri:string}
     */
    protected function fetchMessagePages($sid, $token, array $query, $maxPages)
    {
        $page = $this->fetchMessagePage($sid, $token, $query);
        if (empty($page['ok'])) {
            return $page;
        }

        $rows = $page['messages'];
        $next = isset($page['next_uri']) ? $page['next_uri'] : '';
        $seen = 1;
        while ($next !== '' && $seen < $maxPages && count($rows) < 800) {
            $more = $this->fetchMessageUri($sid, $token, $next);
            if (empty($more['ok']) || count($more['messages']) === 0) {
                break;
            }
            foreach ($more['messages'] as $row) {
                $rows[] = $row;
            }
            $next = isset($more['next_uri']) ? $more['next_uri'] : '';
            $seen++;
        }
        $page['messages'] = $rows;

        return $page;
    }

    /**
     * @param  array<string,int|string>  $query
     * @return array{ok:bool,error:string,number:string,messages:array<int,array<string,string>>,next:string,next_uri:string}
     */
    protected function fetchMessagePage($sid, $token, array $query)
    {
        $url = 'https://api.twilio.com/2010-04-01/Accounts/'.rawurlencode($sid).'/Messages.json?'.$this->twilioQuery($query);

        return $this->fetchTwilioUrl($sid, $token, $url);
    }

    /**
     * @return array{ok:bool,error:string,number:string,messages:array<int,array<string,string>>,next:string,next_uri:string}
     */
    protected function fetchMessageUri($sid, $token, $path)
    {
        $prefix = '/2010-04-01/Accounts/'.$sid.'/Messages.json';
        if (! is_string($path) || strpos($path, $prefix) !== 0) {
            return [
                'ok' => false,
                'error' => '',
                'number' => '',
                'messages' => [],
                'next' => '',
                'next_uri' => '',
            ];
        }

        return $this->fetchTwilioUrl($sid, $token, 'https://api.twilio.com'.$path);
    }

    /**
     * @param  array<string,int|string>  $query
     */
    protected function twilioQuery(array $query)
    {
        $parts = [];
        foreach ($query as $key => $value) {
            $parts[] = rawurlencode((string) $key).'='.rawurlencode((string) $value);
        }

        return implode('&', $parts);
    }

    /**
     * @return array{ok:bool,error:string,number:string,messages:array<int,array<string,string>>,next:string,next_uri:string}
     */
    protected function fetchTwilioUrl($sid, $token, $url)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_USERPWD => $sid.':'.$token,
            CURLOPT_TIMEOUT => 20,
        ]);
        $raw = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $json = json_decode((string) $raw, true);
        if ($http < 200 || $http >= 300 || ! is_array($json)) {
            $message = is_array($json) ? (string) ($json['message'] ?? '') : '';
            if ($message === '') {
                $message = 'Twilio log could not be loaded.';
            }
            $message = str_replace([$sid, $token], '', $message);

            return [
                'ok' => false,
                'error' => trim($message),
                'number' => '',
                'messages' => [],
                'next' => '',
                'next_uri' => '',
            ];
        }

        $rows = [];
        foreach (($json['messages'] ?? []) as $item) {
            if (is_array($item)) {
                $rows[] = $this->presentLogRow($item);
            }
        }
        $next = '';
        $nextUri = (string) ($json['next_page_uri'] ?? '');
        if ($nextUri !== '' && preg_match('/(?:\?|&)PageToken=([A-Za-z0-9\-_]{8,200})/', $nextUri, $match)) {
            $next = $match[1];
        }
        $prefix = '/2010-04-01/Accounts/'.$sid.'/Messages.json';
        if (strpos($nextUri, $prefix) !== 0) {
            $nextUri = '';
        }

        return [
            'ok' => true,
            'error' => '',
            'number' => '',
            'messages' => $rows,
            'next' => $next,
            'next_uri' => $nextUri,
        ];
    }

    /**
     * @param  array<string,mixed>  $item
     * @return array<string,string>
     */
    protected function presentLogRow(array $item)
    {
        $fromAddr = (string) ($item['from'] ?? '');
        $toAddr = (string) ($item['to'] ?? '');
        $date = (string) ($item['date_sent'] ?? ($item['date_created'] ?? ''));
        $stamp = $date !== '' ? strtotime($date) : false;
        $error = (string) ($item['error_code'] ?? '');
        $sid = (string) ($item['sid'] ?? '');
        if ($sid === '') {
            $sid = md5($fromAddr.'|'.$toAddr.'|'.$date.'|'.(string) ($item['body'] ?? ''));
        }

        return [
            'sid' => $sid,
            'sort' => $stamp ? date('Y-m-d H:i:s', $stamp) : '',
            'when' => $stamp ? date('Y-m-d H:i', $stamp) : $date,
            'time' => $stamp ? date('H:i', $stamp) : '',
            'day' => $stamp ? date('Y-m-d', $stamp) : '',
            'from_digits' => preg_replace('/\D/', '', $fromAddr),
            'to_digits' => preg_replace('/\D/', '', $toAddr),
            'from_display' => preg_replace('/^whatsapp:/i', '', $fromAddr),
            'to_display' => preg_replace('/^whatsapp:/i', '', $toAddr),
            'channel' => (stripos($fromAddr, 'whatsapp:') === 0 || stripos($toAddr, 'whatsapp:') === 0) ? 'whatsapp' : 'sms',
            'status' => strtolower((string) ($item['status'] ?? '')),
            'body' => (string) ($item['body'] ?? ''),
            'error_code' => $error === '0' ? '' : $error,
            'media' => (string) ($item['num_media'] ?? '0'),
        ];
    }

    /**
     * @param  array<int|string,string>  $variables
     * @return array<string,string>
     */
    protected function normalizeVariables(array $variables)
    {
        $out = [];
        foreach ($variables as $key => $value) {
            $k = (string) $key;
            $v = trim((string) $value);
            if ($v === '') {
                $v = '-';
            }
            $out[$k] = $v;
        }

        return $out;
    }
}
