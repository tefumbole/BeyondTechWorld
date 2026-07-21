<?php

namespace App\Services\Messaging;

use App\Services\BeyondWasenderService;
use App\Services\TwilioWhatsAppService;
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
        if (! $this->whatsappEnabled()) {
            \Log::info('[messaging] WhatsApp disabled — skip text');

            return ['success' => true, 'skipped' => true, 'provider' => 'none'];
        }

        $provider = $this->whatsappProvider();

        if ($provider === 'TWILIO') {
            $statusSid = trim((string) config('services.whatsapp.content_sid_status', ''));
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
            } elseif ($this->twilioFallbackWasender() && $this->wasender->isConfigured()) {
                \Log::info('[messaging] TWILIO selected but no status Content SID — Wasender fallback for free-form');
            } else {
                \Log::warning('[messaging] TWILIO free-form blocked (no content_sid_status and no Wasender fallback)');

                return [
                    'success' => false,
                    'provider' => 'twilio',
                    'error' => 'Twilio Content SID for status messages is not configured.',
                ];
            }
        }

        $result = $this->wasender->sendText($phone, $body);
        $result['provider'] = 'wasender';

        return $result;
    }

    /**
     * OTP always uses Wasender (Twilio OTP template not approved).
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
        $result = $this->wasender->sendText($phone, $message);
        $result['provider'] = 'wasender';

        return $result;
    }

    /**
     * Announcements always use Wasender (free-form / bulk; not Twilio Content Templates).
     *
     * @return array{success:bool,provider?:string,error?:string,skipped?:bool,dev?:bool}
     */
    public function sendWhatsAppAnnouncement($phone, $body)
    {
        if (! $this->whatsappEnabled()) {
            \Log::info('[messaging] WhatsApp disabled — skip announcement');

            return ['success' => true, 'skipped' => true, 'provider' => 'none'];
        }

        $result = $this->wasender->sendText($phone, $body);
        $result['provider'] = 'wasender';

        return $result;
    }

    /**
     * Approved admission Content Template (HX47150e…).
     *
     * @return array{success:bool,provider?:string,error?:string,skipped?:bool,sid?:string}
     */
    public function sendWhatsAppAdmission($phone, $programName, $departmentName, $yearName, $mediaUrl = null)
    {
        if (! $this->whatsappEnabled()) {
            return ['success' => true, 'skipped' => true, 'provider' => 'none'];
        }

        $provider = $this->whatsappProvider();
        $sid = trim((string) config(
            'services.whatsapp.content_sid_admission',
            'HX47150e179fdbab79738d060fb0ac6415'
        ));

        if ($provider === 'TWILIO') {
            $result = $this->twilioWhatsApp->sendContentTemplate($phone, $sid, [
                '1' => $programName,
                '2' => $departmentName,
                '3' => $yearName,
            ], $mediaUrl);
            $result['provider'] = 'twilio';

            if (! empty($result['success']) || ! $this->twilioFallbackWasender()) {
                return $result;
            }
        }

        // Wasender fallback: plain-text equivalent of the admission notice
        $company = WhatsAppMessage::companyName();
        $body = "Dear Client\n\nCongratulations!\n\n"
            ."Your application to *{$programName}* has been successful received. "
            ."You have been admitted to the *{$departmentName}* programme for the *{$yearName}* academic year.\n\n"
            .'Your admission letter is attached to this message.'."\n\n"
            ."Welcome to our institution!\n\n_{$company}_";

        $result = $this->wasender->sendText($phone, $body);
        $result['provider'] = 'wasender';

        return $result;
    }

    /**
     * @return array{success:bool,provider?:string,error?:string,skipped?:bool}
     */
    public function sendSms($phone, $body)
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
                $from = trim((string) (
                    config('services.sms.twilio_number')
                    ?: env('TWILIO_NUMBER')
                    ?: env('Twilio_Number')
                ));

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
     * Map free-form body into beyond_status_notice-style variables when SID is set.
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

    protected function truncate($text, $max)
    {
        $text = trim(preg_replace("/[ \t]+/", ' ', str_replace(["\r\n", "\r"], "\n", $text)));
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1)).'…';
    }
}
