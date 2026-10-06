<?php

namespace App\Services\Messaging;

use App\Services\TwilioWhatsAppService;

/**
 * Sends the approved Twilio Content templates.
 * Wasender remains the path for normal WhatsApp text.
 */
class TwilioTemplateSender
{
    protected $twilio;

    public function __construct(TwilioWhatsAppService $twilio)
    {
        $this->twilio = $twilio;
    }

    /**
     * beyond_status_update. Variables: name, record type, reference, status.
     *
     * @return array{success:bool,sid?:string,error?:string,provider?:string}
     */
    public function sendStatusUpdate($phone, $name, $recordType, $reference, $status)
    {
        return $this->send('content_sid_status_update', $phone, [
            '1' => $name,
            '2' => $recordType,
            '3' => $reference,
            '4' => $status,
        ]);
    }

    /**
     * beyond_review_link. The button URL is fixed in the approved template.
     *
     * @return array{success:bool,sid?:string,error?:string,provider?:string}
     */
    public function sendReviewLink($phone, $name, $recordType, $reference)
    {
        return $this->send('content_sid_review_link', $phone, [
            '1' => $name,
            '2' => $recordType,
            '3' => $reference,
        ]);
    }

    /**
     * sales_confirmation. Variables: name, company, reference, amount.
     *
     * @return array{success:bool,sid?:string,error?:string,provider?:string}
     */
    public function sendSaleConfirmation($phone, $name, $company, $reference, $amount)
    {
        return $this->send('content_sid_sale', $phone, [
            '1' => $name,
            '2' => $company,
            '3' => $reference,
            '4' => $amount,
        ]);
    }

    /**
     * Full sale receipt. Not sent until WhatsApp approves beyond_sale_receipt.
     *
     * @param  array{name:string,company:string,order:string,date:string,items:string,total:string,payment:string,billing:string,delivery:string,served_by:string,reference:string}  $fields
     * @return array{success:bool,sid?:string,error?:string,provider?:string}
     */
    public function sendSaleReceipt($phone, array $fields)
    {
        $sid = trim((string) config('services.whatsapp.content_sid_sale_receipt', ''));
        if ($sid === '' || $this->twilio->contentApprovalStatus($sid) !== 'approved') {
            return [
                'success' => false,
                'provider' => 'twilio',
                'error' => 'The full sale receipt template is not approved yet.',
            ];
        }

        return $this->send('content_sid_sale_receipt', $phone, [
            '1' => $fields['name'] ?? '',
            '2' => $fields['company'] ?? '',
            '3' => $fields['order'] ?? '',
            '4' => $fields['date'] ?? '',
            '5' => $fields['items'] ?? '',
            '6' => $fields['total'] ?? '',
            '7' => $fields['payment'] ?? '',
            '8' => $fields['billing'] ?? '',
            '9' => $fields['delivery'] ?? '',
            '10' => $fields['served_by'] ?? '',
            '11' => $fields['reference'] ?? '',
        ]);
    }

    /**
     * reminder. Variables: name, company, subject, reference, when.
     *
     * @return array{success:bool,sid?:string,error?:string,provider?:string}
     */
    public function sendReminder($phone, $name, $company, $subject, $reference, $when)
    {
        return $this->send('content_sid_reminder', $phone, [
            '1' => $name,
            '2' => $company,
            '3' => $subject,
            '4' => $reference,
            '5' => $when,
        ]);
    }

    /**
     * beyond_announcement. Kind is "an announcement" or "a reminder".
     *
     * @return array{success:bool,sid?:string,error?:string,provider?:string}
     */
    public function sendAnnouncement($phone, $name, $kind, $message, $reference)
    {
        return $this->send('content_sid_announcement', $phone, [
            '1' => $name,
            '2' => $kind,
            '3' => $message,
            '4' => $reference,
        ]);
    }

    /**
     * shared_notice. Subject, header, name, body, footer, reference. No fixed company name.
     *
     * @param  array{subject?:string,header?:string,name?:string,body?:string,footer?:string,reference?:string}  $fields
     * @return array{success:bool,sid?:string,error?:string,provider?:string}
     */
    public function sendSharedNotice($phone, array $fields)
    {
        $sid = trim((string) config('services.whatsapp.content_sid_shared_notice', ''));
        if ($sid === '' || $this->twilio->contentApprovalStatus($sid) !== 'approved') {
            return [
                'success' => false,
                'provider' => 'twilio',
                'error' => 'The shared notice template is not approved yet.',
            ];
        }

        if (mb_strlen(trim((string) ($fields['body'] ?? ''))) > 1500) {
            return [
                'success' => false,
                'provider' => 'twilio',
                'error' => 'Notice body is too long for the WhatsApp template.',
            ];
        }

        $variables = \App\Support\SharedNotice::variables(
            $fields['subject'] ?? '',
            $fields['header'] ?? '',
            $fields['name'] ?? '',
            $fields['body'] ?? '',
            $fields['footer'] ?? '',
            $fields['reference'] ?? ''
        );

        return $this->send('content_sid_shared_notice', $phone, $variables, true);
    }

    public function sendSharedStatus($phone, $name, $organisation, $recordType, $reference, $status, $detail)
    {
        return $this->approvedSend('content_sid_shared_status', $phone, [
            '1' => $name,
            '2' => $organisation,
            '3' => $recordType,
            '4' => $reference,
            '5' => $status,
            '6' => $detail,
        ], true);
    }

    public function sendSharedAction($phone, $name, $organisation, $action, $reference, $link)
    {
        $path = $this->sitePath($link);
        if ($path === '') {
            return [
                'success' => false,
                'provider' => 'twilio',
                'error' => 'The link is not on the public website.',
            ];
        }

        return $this->approvedSend('content_sid_shared_action', $phone, [
            '1' => $name,
            '2' => $organisation,
            '3' => $action,
            '4' => $reference,
            '5' => $path,
        ]);
    }

    public function sendSharedAccess($phone, $name, $organisation, $username, $password, $signIn)
    {
        $path = $this->sitePath($signIn);
        if ($path === '') {
            $path = 'login';
        }

        return $this->approvedSend('content_sid_shared_access', $phone, [
            '1' => $name,
            '2' => $organisation,
            '3' => $username,
            '4' => $password,
            '5' => $path,
        ]);
    }

    public function sendSharedOtp($phone, $code, $minutes)
    {
        return $this->approvedSend('content_sid_shared_otp', $phone, [
            '1' => $code,
        ]);
    }

    public function sendSharedDocument($phone, $name, $organisation, $title, $reference, $filePath)
    {
        return $this->approvedSend('content_sid_shared_document', $phone, [
            '1' => $name,
            '2' => $organisation,
            '3' => $title,
            '4' => $reference,
            '5' => $filePath,
        ]);
    }

    public function sendSharedImage($phone, $name, $organisation, $title, $reference, $filePath)
    {
        return $this->approvedSend('content_sid_shared_image', $phone, [
            '1' => $name,
            '2' => $organisation,
            '3' => $title,
            '4' => $reference,
            '5' => $filePath,
        ]);
    }

    public function sendSharedChoice($phone, $name, $organisation, $options, $reference)
    {
        return $this->approvedSend('content_sid_shared_choice', $phone, [
            '1' => $name,
            '2' => $organisation,
            '3' => $options,
            '4' => $reference,
        ]);
    }

    public function sendSharedConfirmation($phone, $name, $organisation, $recordType, $reference, $date, $details, $amount)
    {
        return $this->approvedSend('content_sid_shared_confirmation', $phone, [
            '1' => $name,
            '2' => $organisation,
            '3' => $recordType,
            '4' => $reference,
            '5' => $date,
            '6' => $details,
            '7' => $amount,
        ], true);
    }

    protected function approvedSend($configKey, $phone, array $variables, $keepBreaks = false)
    {
        $sid = trim((string) config('services.whatsapp.'.$configKey, ''));
        if ($sid === '' || $this->twilio->contentApprovalStatus($sid) !== 'approved') {
            return [
                'success' => false,
                'provider' => 'twilio',
                'error' => 'Template is not approved yet.',
            ];
        }

        return $this->send($configKey, $phone, $variables, $keepBreaks);
    }

    public function sendServiceUpdate($phone, $name, $recordType, $reference, $status)
    {
        return $this->send('content_sid_service_update', $phone, [
            '1' => $name,
            '2' => $recordType,
            '3' => $reference,
            '4' => $status,
        ]);
    }

    /**
     * @param  array<string,string>  $variables
     * @return array{success:bool,sid?:string,error?:string,provider?:string}
     */
    protected function send($configKey, $phone, array $variables, $keepBreaks = false)
    {
        $sid = trim((string) config('services.whatsapp.'.$configKey, ''));
        if ($sid === '') {
            return [
                'success' => false,
                'provider' => 'twilio',
                'error' => 'Twilio Content SID is not configured.',
            ];
        }

        $clean = [];
        foreach ($variables as $key => $value) {
            $clean[$key] = $keepBreaks ? $this->cleanWithBreaks($value) : $this->cleanVariable($value);
        }

        $result = $this->twilio->sendContentTemplate($phone, $sid, $clean);
        $result['provider'] = 'twilio';
        if ($keepBreaks && empty($result['success']) && $this->breaksWereRejected($result['error'] ?? '')) {
            $flat = [];
            foreach ($variables as $key => $value) {
                $flat[$key] = $this->cleanVariable($value);
            }
            $result = $this->twilio->sendContentTemplate($phone, $sid, $flat);
            $result['provider'] = 'twilio';
        }

        return $result;
    }

    protected function sitePath($link)
    {
        $link = trim((string) $link);
        if ($link === '' || $link === '-') {
            return '';
        }
        $base = rtrim(\App\Support\TwilioMedia::baseUrl(), '/');
        if ($base !== '' && stripos($link, $base) === 0) {
            return ltrim(substr($link, strlen($base)), '/');
        }
        if (! preg_match('#^https?://#i', $link)) {
            return ltrim($link, '/');
        }

        return '';
    }

    protected function breaksWereRejected($error)
    {
        $error = strtolower((string) $error);

        return strpos($error, 'newline') !== false
            || strpos($error, 'line break') !== false
            || strpos($error, 'content variable') !== false;
    }

    protected function cleanVariable($value)
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value));
        if ($value === '') {
            return '-';
        }
        if (mb_strlen($value) > 900) {
            return rtrim(mb_substr($value, 0, 899)).'…';
        }

        return $value;
    }

    protected function cleanWithBreaks($value)
    {
        $value = str_replace(["\r\n", "\r"], "\n", (string) $value);
        $value = preg_replace("/[ \t]+/", ' ', $value);
        $value = preg_replace("/\n{3,}/", "\n\n", $value);
        $value = trim($value);
        if ($value === '') {
            return '-';
        }
        if (mb_strlen($value) > 1500) {
            return rtrim(mb_substr($value, 0, 1499)).'…';
        }

        return $value;
    }
}
