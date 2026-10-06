<?php

namespace App\Support;

use App\Services\Messaging\TwilioTemplateSender;

/**
 * Maps this application's WhatsApp texts onto generic Twilio templates.
 * Another application can send the same templates with its own organisation name.
 *
 * shared_notice       subject, header, name, body, footer, reference
 * shared_status       name, organisation, record, reference, status, detail
 * shared_action       name, organisation, action, reference, link
 * shared_access       name, organisation, username, password, sign-in link
 * shared_otp          code, minutes
 * shared_confirmation name, organisation, record, reference, date, details, amount
 * reminder            name, organisation, subject, reference, when
 * shared_document     name, organisation, file name, reference, public file path
 * shared_image        name, organisation, file name, reference, public file path
 * shared_choice       name, organisation, options, reference
 */
class TwilioEquivalence
{
    public static function catalog()
    {
        return [
            ['name' => 'shared_notice', 'category' => 'Marketing', 'sends' => 'Announcements, letters, tasks, quotations, and any other notice. Fields: subject, header, name, body, footer, reference.'],
            ['name' => 'shared_status', 'category' => 'Utility', 'sends' => 'Application updates, signed documents, and other record status. Fields: name, organisation, record, reference, status, detail.'],
            ['name' => 'shared_action', 'category' => 'Utility', 'sends' => 'Signature, review, upload, and other links. Fields: name, organisation, action, reference, link.'],
            ['name' => 'shared_access', 'category' => 'Utility', 'sends' => 'New account and password messages. Fields: name, organisation, username, password, sign-in link.'],
            ['name' => 'shared_otp', 'category' => 'Utility', 'sends' => 'Login and signup codes. Fields: code, minutes.'],
            ['name' => 'shared_confirmation', 'category' => 'Utility', 'sends' => 'Booking, quotation, and payment confirmations. Fields: name, organisation, record, reference, date, details, amount.'],
            ['name' => 'reminder', 'category' => 'Marketing', 'sends' => 'Booking, event, rental, and appointment reminders. Fields: name, organisation, subject, reference, when.'],
            ['name' => 'shared_document', 'category' => 'Utility', 'sends' => 'Invoices, signed agreements, letters, and other files. Fields: name, organisation, file name, reference, public file path.'],
            ['name' => 'shared_image', 'category' => 'Utility', 'sends' => 'QR codes and other images. Fields: name, organisation, file name, reference, public file path.'],
            ['name' => 'shared_choice', 'category' => 'Utility', 'sends' => 'A question with options, in place of a poll. Fields: name, organisation, options, reference.'],
        ];
    }

    /**
     * @return array{success:bool,sid?:string,error?:string,provider?:string}
     */
    public function send($phone, $body, array $statusVars = [])
    {
        $sender = app(TwilioTemplateSender::class);
        $org = WhatsAppMessage::companyName();
        $name = $this->name($body, $statusVars);
        $reference = $this->reference($body, $statusVars);
        $url = $this->firstUrl($body.' '.(string) ($statusVars['details'] ?? ''));
        $plain = $this->plain($statusVars['message'] ?? $body);

        if ($this->isAccess($body)) {
            return $sender->sendSharedAccess(
                $phone,
                $name,
                $org,
                $this->labeled($body, 'Username') ?: '-',
                $this->labeled($body, 'Password') ?: '-',
                $url !== '' ? $url : '-'
            );
        }

        if ($url !== '') {
            $action = trim((string) ($statusVars['title'] ?? ''));
            if ($action === '') {
                $action = 'open this link';
            }

            return $sender->sendSharedAction($phone, $name, $org, $action, $reference, $url);
        }

        if ($this->isConfirmation($body)) {
            return $sender->sendSharedConfirmation(
                $phone,
                $name,
                $org,
                trim((string) ($statusVars['title'] ?? 'record')) ?: 'record',
                $reference,
                trim((string) ($statusVars['details'] ?? '-')) ?: '-',
                $plain !== '' ? $plain : '-',
                '-'
            );
        }

        if (trim((string) ($statusVars['title'] ?? '')) !== '') {
            return $sender->sendSharedStatus(
                $phone,
                $name,
                $org,
                $statusVars['title'],
                $reference,
                trim((string) ($statusVars['message'] ?? 'Updated')) ?: 'Updated',
                trim((string) ($statusVars['details'] ?? '-')) ?: '-'
            );
        }

        $subject = trim((string) ($statusVars['subject'] ?? $statusVars['title'] ?? ''));
        if ($subject === '') {
            $subject = 'Notice';
        }

        return $sender->sendSharedNotice($phone, [
            'subject' => $subject,
            'header' => trim((string) ($statusVars['header'] ?? '')) !== '' ? $statusVars['header'] : $org,
            'name' => $name,
            'body' => $plain !== '' ? $plain : '-',
            'footer' => trim((string) ($statusVars['footer'] ?? '')) !== '' ? $statusVars['footer'] : $org,
            'reference' => $reference,
        ]);
    }

    protected function name($body, array $statusVars)
    {
        $name = trim((string) ($statusVars['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
        if (preg_match('/Dear\s+([^,\n]+)/i', (string) $body, $match)) {
            $name = trim($match[1], " *_\t");
            if ($name !== '') {
                return $name;
            }
        }

        return 'Friend';
    }

    protected function reference($body, array $statusVars)
    {
        $reference = trim((string) ($statusVars['reference'] ?? ''));
        if ($reference !== '' && $reference !== '-') {
            return $reference;
        }
        $found = LetterReference::extractFromText((string) $body);

        return $found ?: '-';
    }

    protected function firstUrl($text)
    {
        if (preg_match('#https?://[^\s<>]+#i', (string) $text, $match)) {
            return rtrim($match[0], '.,)');
        }

        return '';
    }

    protected function isAccess($body)
    {
        return stripos((string) $body, 'username') !== false
            && stripos((string) $body, 'password') !== false;
    }

    protected function isConfirmation($body)
    {
        $head = strtolower(substr($this->plain($body), 0, 180));

        return strpos($head, 'confirmed') !== false || strpos($head, 'has been recorded') !== false;
    }

    protected function labeled($body, $label)
    {
        if (preg_match('/'.preg_quote($label, '/').'\s*:\s*([^\n]+)/i', (string) $body, $match)) {
            return trim($match[1], " *_");
        }

        return '';
    }

    protected function plain($body)
    {
        $body = str_replace(["\r\n", "\r"], "\n", (string) $body);
        $body = preg_replace('/\*+|_+/', '', $body);

        return trim($body);
    }
}
