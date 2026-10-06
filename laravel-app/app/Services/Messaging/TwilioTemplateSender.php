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
    protected function send($configKey, $phone, array $variables)
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
            $clean[$key] = $this->cleanVariable($value);
        }

        $result = $this->twilio->sendContentTemplate($phone, $sid, $clean);
        $result['provider'] = 'twilio';

        return $result;
    }

    protected function cleanVariable($value)
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value));
        if ($value === '') {
            return '-';
        }
        if (mb_strlen($value) > 200) {
            return rtrim(mb_substr($value, 0, 199)).'…';
        }

        return $value;
    }
}
