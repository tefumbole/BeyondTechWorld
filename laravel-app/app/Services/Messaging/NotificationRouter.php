<?php

namespace App\Services\Messaging;

use App\Services\BeyondWasenderService;
use App\Services\TwilioWhatsAppService;
use App\Support\LetterReference;
use App\Support\WhatsAppMessage;
use Clickatell\ClickatellException;
use Twilio\Rest\Client;

class NotificationRouter
{
    protected $wasender;
    protected $twilioWhatsApp;

    public function __construct(BeyondWasenderService $wasender, TwilioWhatsAppService $twilioWhatsApp)
    {
        $this->wasender = $wasender;
        $this->twilioWhatsApp = $twilioWhatsApp;
    }

    public function whatsappEnabled()
    {
        return filter_var(config('services.whatsapp.enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function smsEnabled()
    {
        return filter_var(config('services.sms.enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function whatsappProvider()
    {
        return strtoupper((string) config('services.whatsapp.service', 'WASENDER'));
    }

    public function twilioFallbackWasender()
    {
        return filter_var(config('services.whatsapp.twilio_fallback_wasender', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array{success:bool,provider?:string,error?:string,skipped?:bool,dev?:bool,sid?:string}
     */
    public function sendWhatsAppText($phone, $body, array $statusVars = [])
    {
        return $this->sendWasenderText($phone, $body);
    }

    /**
     * WhatsApp text through Wasender only. Internship, payments, quotations, and sales use this
     * so an approved Twilio template cannot take the message instead.
     *
     * @return array{success:bool,provider?:string,error?:string,skipped?:bool,dev?:bool,sid?:string}
     */
    public function sendWasenderText($phone, $body)
    {
        if (! $this->whatsappEnabled()) {
            \Log::info('[messaging] WhatsApp disabled — skip Wasender text');

            return ['success' => true, 'skipped' => true, 'provider' => 'none'];
        }

        $body = LetterReference::applyToMessage((string) $body, 'whatsapp');
        $result = $this->wasender->sendTextRaw($phone, $body);
        $result['provider'] = 'wasender';

        return $result;
    }

    /**
     * Approved beyond_status_update template. Does not use Wasender.
     *
     * @return array{success:bool,sid?:string,error?:string,provider?:string}
     */
    public function sendTwilioStatusUpdate($phone, $name, $recordType, $reference, $status)
    {
        return $this->formattedWasender($phone, WhatsAppMessage::statusBlock('📌', $recordType)
            .WhatsAppMessage::greeting($name)
            .WhatsAppMessage::bullet('Reference', $reference)
            .WhatsAppMessage::bullet('Status', $status)
            .WhatsAppMessage::footer());
    }

    /**
     * Approved beyond_review_link template. The button URL is the one saved on the template.
     *
     * @return array{success:bool,sid?:string,error?:string,provider?:string}
     */
    public function sendTwilioReviewLink($phone, $name, $recordType, $reference)
    {
        return $this->formattedWasender($phone, WhatsAppMessage::statusBlock('⭐', 'Review')
            .WhatsAppMessage::greeting($name)
            .'Please review your *'.$recordType."*.\n\n"
            .WhatsAppMessage::bullet('Reference', $reference)
            .WhatsAppMessage::footer());
    }

    /**
     * Approved sales_confirmation template from the official WhatsApp number.
     *
     * @return array{success:bool,sid?:string,error?:string,provider?:string}
     */
    public function sendSaleConfirmation($phone, $name, $company, $reference, $amount)
    {
        return $this->formattedWasender($phone, WhatsAppMessage::statusBlock('✅', 'Sale confirmation')
            .WhatsAppMessage::greeting($name)
            .'Your sale with *'.$company."* is confirmed.\n\n"
            .WhatsAppMessage::bullet('Reference', $reference)
            .WhatsAppMessage::bullet('Amount', $amount)
            .WhatsAppMessage::footer());
    }

    /**
     * @param  array{name:string,company:string,order:string,date:string,items:string,total:string,payment:string,billing:string,delivery:string,served_by:string,reference:string}  $fields
     * @return array{success:bool,sid?:string,error?:string,provider?:string}
     */
    public function sendSaleReceipt($phone, array $fields)
    {
        $name = $fields['name'] ?? 'Client';
        $lines = WhatsAppMessage::statusBlock('🧾', 'Sale receipt')
            .WhatsAppMessage::greeting($name);
        foreach ([
            'Order' => 'order',
            'Date' => 'date',
            'Items' => 'items',
            'Total' => 'total',
            'Payment' => 'payment',
            'Billing' => 'billing',
            'Delivery' => 'delivery',
            'Served by' => 'served_by',
            'Reference' => 'reference',
        ] as $label => $key) {
            $value = trim((string) ($fields[$key] ?? ''));
            if ($value !== '' && $value !== '-') {
                $lines .= WhatsAppMessage::bullet($label, $value);
            }
        }
        $lines .= WhatsAppMessage::footer();

        return $this->formattedWasender($phone, $lines);
    }

    public function saleReceiptIsApproved()
    {
        $sid = trim((string) config('services.whatsapp.content_sid_sale_receipt', ''));

        return $sid !== '' && $this->twilioWhatsApp->contentApprovalStatus($sid) === 'approved';
    }

    /**
     * Approved reminder template. Subject is the thing being reminded, not a free paragraph.
     *
     * @return array{success:bool,sid?:string,error?:string,provider?:string}
     */
    public function sendReminder($phone, $name, $company, $subject, $reference, $when)
    {
        return $this->formattedWasender($phone, WhatsAppMessage::statusBlock('⏰', 'Reminder')
            .WhatsAppMessage::greeting($name)
            .'This is a reminder from *'.$company."*.\n\n"
            .WhatsAppMessage::bullet('Subject', $subject)
            .WhatsAppMessage::bullet('Reference', $reference)
            .WhatsAppMessage::bullet('When', $when)
            .WhatsAppMessage::footer());
    }

    /**
     * Approved beyond_service_update template.
     *
     * @return array{success:bool,sid?:string,error?:string,provider?:string}
     */
    public function sendServiceUpdate($phone, $name, $recordType, $reference, $status)
    {
        return $this->formattedWasender($phone, WhatsAppMessage::statusBlock('📌', 'Update')
            .WhatsAppMessage::greeting($name)
            .WhatsAppMessage::bullet('Record', $recordType)
            .WhatsAppMessage::bullet('Reference', $reference)
            .WhatsAppMessage::bullet('Status', $status)
            .WhatsAppMessage::footer());
    }

    /**
     * beyond_announcement. Used for announcement texts and their reminders.
     *
     * @return array{success:bool,sid?:string,error?:string,provider?:string}
     */
    public function sendAnnouncement($phone, $name, $kind, $message, $reference)
    {
        return $this->formattedWasender($phone, WhatsAppMessage::statusBlock('📢', 'Announcement')
            .WhatsAppMessage::greeting($name)
            .trim((string) $message)."\n\n"
            .WhatsAppMessage::bullet('Reference', $reference)
            .WhatsAppMessage::footer());
    }

    /**
     * @param  array{subject?:string,header?:string,name?:string,body?:string,footer?:string,reference?:string}  $fields
     * @return array{success:bool,sid?:string,error?:string,provider?:string}
     */
    public function sendSharedNotice($phone, array $fields)
    {
        $name = $fields['name'] ?? 'Client';
        $subject = trim((string) ($fields['subject'] ?? $fields['header'] ?? 'Notice'));
        $body = trim((string) ($fields['body'] ?? ''));
        $text = WhatsAppMessage::statusBlock('📩', $subject !== '' ? $subject : 'Notice')
            .WhatsAppMessage::greeting($name);
        if ($body !== '') {
            $text .= $body."\n";
        }
        $reference = trim((string) ($fields['reference'] ?? ''));
        if ($reference !== '' && $reference !== '-') {
            $text .= "\n".WhatsAppMessage::bullet('Reference', $reference);
        }
        $footer = trim((string) ($fields['footer'] ?? ''));
        if ($footer !== '') {
            $text .= "\n".$footer;
        }
        $text .= WhatsAppMessage::footer();

        return $this->formattedWasender($phone, $text);
    }

    public function sharedNoticeIsApproved()
    {
        $sid = trim((string) config('services.whatsapp.content_sid_shared_notice', ''));

        return $sid !== '' && $this->twilioWhatsApp->contentApprovalStatus($sid) === 'approved';
    }

    /**
     * Free-form text that must keep a URL visible. Twilio status templates drop links.
     */
    public function sendWhatsAppTextWithLink($phone, $body)
    {
        return $this->sendWasenderText($phone, $body);
    }

    /**
     * OTP on Wasender. The code is one line so WhatsApp can copy it in one tap.
     * No admin copy and no extra purpose notice.
     *
     * @return array{success:bool,provider?:string,error?:string,skipped?:bool,dev?:bool,sid?:string}
     */
    public function sendWhatsAppOtp($phone, $otp, $purpose = 'login', $expiresMinutes = 10)
    {
        if (! $this->whatsappEnabled()) {
            \Log::info('[messaging] WhatsApp disabled — skip OTP');

            return ['success' => true, 'skipped' => true, 'provider' => 'none'];
        }

        $message = WhatsAppMessage::otpMessage($otp, $purpose, $expiresMinutes);
        $result = $this->wasender->sendTextRaw($phone, $message, false, false, 12, false);
        $result['provider'] = 'wasender';
        if (! empty($result['success'])) {
            $this->rememberOtpForSameDevice($otp);

            return $result;
        }

        $fallback = $this->sendOtpFallback($phone, $otp, $expiresMinutes);
        if (! empty($fallback['success'])) {
            $this->rememberOtpForSameDevice($otp);

            return $fallback;
        }

        return $result;
    }

    /**
     * Wasender is behind Cloudflare and can return HTTP 522. Login still needs a code,
     * so the approved Twilio OTP template is used, then SMS.
     *
     * @return array{success:bool,provider?:string,error?:string}
     */
    protected function sendOtpFallback($phone, $otp, $expiresMinutes)
    {
        $sid = trim((string) config('services.whatsapp.content_sid_shared_otp', ''));
        if ($sid !== '') {
            $twilio = $this->twilioWhatsApp->sendContentTemplate($phone, $sid, ['1' => (string) $otp]);
            if (! empty($twilio['success'])) {
                $twilio['provider'] = 'twilio';

                return $twilio;
            }
            \Log::warning('[messaging] Twilio OTP fallback failed', [
                'error' => isset($twilio['error']) ? $twilio['error'] : 'unknown',
            ]);
        }

        $sms = $this->sendSms(
            \App\Support\WhatsAppPhone::display($phone),
            'Your Beyond Tech World code is '.$otp.'. It expires in '.(int) $expiresMinutes.' minutes.'
        );
        if (! empty($sms['success']) && empty($sms['skipped'])) {
            return ['success' => true, 'provider' => 'sms'];
        }

        return ['success' => false, 'error' => 'The login code could not be sent.'];
    }

    /**
     * Send a local file as a WhatsApp document (Wasender upload + document message).
     *
     * @return array{success:bool,provider?:string,error?:string,skipped?:bool,dev?:bool}
     */
    public function sendWhatsAppDocument($phone, $localPath, $fileName = null, $caption = null)
    {
        if (! $this->whatsappEnabled()) {
            \Log::info('[messaging] WhatsApp disabled — skip document');

            return ['success' => true, 'skipped' => true, 'provider' => 'none'];
        }

        $caption = LetterReference::applyToMessage((string) ($caption ?: $fileName ?: 'Document'), 'whatsapp');
        $result = $this->wasender->sendDocument($phone, $localPath, $fileName, $caption);
        $result['provider'] = 'wasender';

        return $result;
    }

    /**
     * Announcements use the same Twilio beyond_notice template when provider is TWILIO.
     *
     * @param  array{title?:string,name?:string,message?:string,reference?:string,details?:string}  $statusVars
     * @return array{success:bool,provider?:string,error?:string,skipped?:bool,dev?:bool,sid?:string}
     */
    public function sendWhatsAppAnnouncement($phone, $body, array $statusVars = [])
    {
        return $this->sendWasenderText($phone, $body);
    }

    /**
     * Once Twilio credit is finished, the rest of an announcement stays on Wasender.
     */
    protected function announcementSkipsTwilio()
    {
        return $this->twilioFallbackWasender()
            && $this->wasender->isConfigured()
            && $this->twilioWhatsApp->creditIsExhausted();
    }

    /**
     * @return array{success:bool,provider?:string,error?:string,skipped?:bool,dev?:bool}
     */
    protected function announcementViaWasender($phone, $body)
    {
        $result = $this->wasender->sendTextRaw($phone, $body);
        $result['provider'] = 'wasender';

        return $result;
    }

    /**
     * Admission / hired notice via beyond_notice (5 vars: headline, name, message, reference, extra).
     *
     * @return array{success:bool,provider?:string,error?:string,skipped?:bool,sid?:string}
     */
    public function sendWhatsAppAdmission($phone, $programName, $departmentName, $yearName, $mediaUrl = null)
    {
        if (! $this->whatsappEnabled()) {
            return ['success' => true, 'skipped' => true, 'provider' => 'none'];
        }

        $company = WhatsAppMessage::companyName();
        $wasenderBody = "Dear Client\n\nCongratulations!\n\n"
            ."Your application to *{$programName}* has been successfully received. "
            ."You have been admitted to the *{$departmentName}* programme for the *{$yearName}* academic year.\n\n"
            .'Your admission letter is attached to this message.'."\n\n"
            ."Welcome to our institution!\n\n_{$company}_";

        $result = $this->wasender->sendTextRaw($phone, $wasenderBody);
        $result['provider'] = 'wasender';

        return $result;
    }

    /**
     * @return array{success:bool,provider?:string,error?:string,skipped?:bool}
     */
    public function sendSms($phone, $body, $fromOverride = null)
    {
        if (! $this->smsEnabled()) {
            \Log::info('[messaging] SMS disabled — skip');

            return ['success' => true, 'skipped' => true, 'provider' => 'none'];
        }

        $gateway = strtolower((string) config('services.sms.gateway', env('SMS_GATEWAY', 'twilio')));
        $phone = trim((string) $phone);
        $body = (string) $body;

        if ($phone === '' || $body === '') {
            return ['success' => false, 'error' => 'Phone or message is empty.', 'provider' => $gateway];
        }

        try {
            if ($gateway === 'twilio') {
                $sid = trim((string) (config('services.sms.account_sid') ?: env('ACCOUNT_SID') ?: env('TWILIO_SID')));
                $token = trim((string) (config('services.sms.auth_token') ?: env('AUTH_TOKEN') ?: env('TWILIO_AUTH_TOKEN')));
                $from = trim((string) $fromOverride);
                if ($from === '') {
                    $from = trim((string) (
                        config('services.sms.twilio_number')
                        ?: env('TWILIO_NUMBER')
                        ?: env('Twilio_Number')
                    ));
                }

                if ($sid === '' || $token === '' || $from === '') {
                    return ['success' => false, 'error' => 'Twilio SMS is not configured.', 'provider' => 'twilio'];
                }

                $client = new Client($sid, $token);
                $client->messages->create($phone, [
                    'from' => $from,
                    'body' => $body,
                ]);

                return ['success' => true, 'provider' => 'twilio'];
            }

            if ($gateway === 'clickatell') {
                $apiKey = trim((string) (config('services.sms.clickatell_api_key') ?: env('CLICKATELL_API_KEY')));
                if ($apiKey === '') {
                    return ['success' => false, 'error' => 'Clickatell is not configured.', 'provider' => 'clickatell'];
                }

                $clickatell = new \Clickatell\Rest($apiKey);
                $clickatell->sendMessage(['to' => [$phone], 'content' => $body]);

                return ['success' => true, 'provider' => 'clickatell'];
            }

            return ['success' => false, 'error' => 'Unknown SMS gateway.', 'provider' => $gateway];
        } catch (ClickatellException $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'provider' => 'clickatell'];
        } catch (\Throwable $e) {
            \Log::warning('[messaging] SMS send failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage(), 'provider' => $gateway];
        }
    }

    /**
     * Send via status Content SID. Returns null when caller should try Wasender.
     *
     * @param  array{title?:string,name?:string,message?:string,reference?:string,details?:string}  $statusVars
     * @return array{success:bool,provider?:string,error?:string,sid?:string}|null
     */
    protected function sendTwilioStatusTemplate($phone, $body, array $statusVars = [])
    {
        $statusSid = $this->resolveNoticeContentSid('status');
        if ($statusSid !== '') {
            $vars = $this->statusVariablesFrom($body, $statusVars);
            $result = $this->twilioWhatsApp->sendContentTemplate($phone, $statusSid, $vars);
            $result['provider'] = 'twilio';

            if (! empty($result['success']) || ! $this->twilioFallbackWasender()) {
                return $result;
            }

            \Log::warning('[messaging] Twilio status template failed — falling back to Wasender', [
                'error' => $result['error'] ?? null,
            ]);

            return null;
        }

        if ($this->twilioFallbackWasender() && $this->wasender->isConfigured()) {
            \Log::info('[messaging] TWILIO selected but no status Content SID — Wasender fallback for free-form');

            return null;
        }

        \Log::warning('[messaging] TWILIO free-form blocked (no content_sid_status and no Wasender fallback)');

        return [
            'success' => false,
            'provider' => 'twilio',
            'error' => 'Twilio Content SID for status messages is not configured.',
        ];
    }

    /**
     * beyond_notice Content SID (HX47150e…). Prefer role-specific env, else shared default.
     *
     * @param  string  $role  status|otp|admission
     */
    protected function resolveNoticeContentSid($role = 'status')
    {
        $default = 'HX47150e179fdbab79738d060fb0ac6415';
        $role = strtolower((string) $role);

        if ($role === 'otp') {
            $otp = trim((string) config('services.whatsapp.content_sid_otp', ''));
            if ($otp !== '') {
                return $otp;
            }
        }

        if ($role === 'admission') {
            $admission = trim((string) config('services.whatsapp.content_sid_admission', ''));
            if ($admission !== '') {
                return $admission;
            }
        }

        $status = trim((string) config('services.whatsapp.content_sid_status', ''));

        return $status !== '' ? $status : $default;
    }

    /**
     * Map free-form body into beyond_notice variables:
     * {{1}} headline, {{2}} name, {{3}} main message, {{4}} reference, {{5}} extra.
     *
     * @param  array{title?:string,name?:string,message?:string,reference?:string,details?:string}  $statusVars
     * @return array<string,string>
     */
    protected function statusVariablesFrom($body, array $statusVars)
    {
        $title = $statusVars['title'] ?? 'Notification';
        $name = $statusVars['name'] ?? 'Client';
        $message = $statusVars['message'] ?? $this->truncate((string) $body, 800);
        $reference = $statusVars['reference'] ?? '-';
        $details = $statusVars['details'] ?? '-';

        return [
            '1' => $title !== '' ? $title : 'Notification',
            '2' => $name !== '' ? $name : 'Client',
            '3' => $message !== '' ? $message : '-',
            '4' => $reference !== '' ? $reference : '-',
            '5' => $details !== '' ? $details : '-',
        ];
    }

    protected function formattedWasender($phone, $body)
    {
        return $this->sendWasenderText($phone, $body);
    }

    protected function rememberOtpForSameDevice($otp)
    {
        $digits = preg_replace('/\D+/', '', (string) $otp);
        if (strlen($digits) < 4) {
            return;
        }
        try {
            if (session()->isStarted()) {
                session(['wa_otp_autofill' => $digits]);
            }
        } catch (\Throwable $e) {
        }
    }

    protected function truncate($text, $max)
    {
        $text = trim(preg_replace("/[ \t]+/", ' ', str_replace(["\r\n", "\r"], "\n", $text)));
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1)).'…';
    }
}
