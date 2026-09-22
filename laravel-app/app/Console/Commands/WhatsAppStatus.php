<?php

namespace App\Console\Commands;

use App\Services\TwilioWhatsAppService;
use Illuminate\Console\Command;

class WhatsAppStatus extends Command
{
    protected $signature = 'whatsapp:status';

    protected $description = 'Show which WhatsApp provider is active (Twilio vs Wasender)';

    public function handle()
    {
        $service = strtoupper((string) config('services.whatsapp.service', 'WASENDER'));
        $fallback = filter_var(config('services.whatsapp.twilio_fallback_wasender', true), FILTER_VALIDATE_BOOLEAN);
        $hasWasenderKey = ! empty(config('services.whatsapp.wasender_api_key'));
        $sessionId = config('services.whatsapp.wasender_session_id');
        $hasSessionId = ! empty($sessionId);
        $baseUrl = config('services.whatsapp.wasender_base_url');
        $hasUltraMsg = ! empty(config('services.whatsapp.ultramsg_instance'))
            && ! empty(config('services.whatsapp.ultramsg_token'));

        /** @var TwilioWhatsAppService $twilio */
        $twilio = app(TwilioWhatsAppService::class);
        $statusSid = trim((string) config('services.whatsapp.content_sid_status', ''));
        $otpSid = trim((string) config('services.whatsapp.content_sid_otp', ''));
        $admissionSid = trim((string) config('services.whatsapp.content_sid_admission', ''));

        $this->line('WhatsApp service setting: '.$service);
        $this->line('Twilio → Wasender fallback: '.($fallback ? 'yes' : 'no (Twilio-only)'));
        $this->line('Twilio WhatsApp configured: '.($twilio->isConfigured() ? 'yes' : 'no (SID/token/from missing)'));
        $this->line('Content SID admission: '.($admissionSid !== '' ? $admissionSid : 'missing'));
        $this->line('Content SID status: '.($statusSid !== '' ? $statusSid : 'missing'));
        $this->line('Content SID OTP: '.($otpSid !== '' ? $otpSid : 'empty (reuses status SID)'));
        $this->line('Wasender API key: '.($hasWasenderKey ? 'set' : 'missing'));
        $this->line('Wasender session ID: '.($hasSessionId ? $sessionId : 'missing'));
        $this->line('Wasender base URL: '.$baseUrl);
        $this->line('Min send interval (ms): '.config('services.whatsapp.min_send_interval_ms'));
        $this->line('Text-to-document delay (ms): '.config('services.whatsapp.text_to_document_delay_ms'));
        $companyName = config('services.whatsapp.company_name');
        $this->line('Company name: '.($companyName ?: 'not set (uses site title)'));
        $this->line('UltraMsg credentials: '.($hasUltraMsg ? 'present (legacy fallback only)' : 'not set'));

        if ($service === 'TWILIO') {
            if ($twilio->isConfigured() && $statusSid !== '') {
                $this->info('Active provider: Twilio Content Templates (OTP + status + announcements)');
            } elseif ($twilio->isConfigured()) {
                $this->warn('Active provider: Twilio — add TWILIO_WHATSAPP_CONTENT_SID_STATUS to send OTP/status texts');
            } else {
                $this->warn('Active provider: Twilio — add TWILIO_SID, TWILIO_AUTH_TOKEN, TWILIO_WHATSAPP_FROM');
            }
            if ($fallback && $hasWasenderKey && $hasSessionId) {
                $this->comment('Wasender fallback is enabled for failed Twilio sends / missing SIDs.');
            } elseif (! $fallback) {
                $this->comment('Wasender text fallback is OFF — Twilio-only testing mode.');
            }
        } elseif ($service === 'WASENDER') {
            if ($hasWasenderKey && $hasSessionId) {
                $this->info('Active provider: WasenderAPI (key + session configured)');
                $status = app(\App\Services\WhatsApp\WhatsAppSessionStatusService::class)->status(true);
                $line = 'Wasender session status: '.($status['status'] ?? 'UNKNOWN');
                if (! empty($status['connected'])) {
                    $this->info($line);
                } else {
                    $this->warn($line.' — open Wasender dashboard and reconnect session '.$sessionId);
                }
                if (! empty($status['session_name'])) {
                    $this->line('Wasender session name: '.$status['session_name']);
                }
                if (! empty($status['error'])) {
                    $this->warn($status['error']);
                }
            } elseif ($hasWasenderKey) {
                $this->warn('Active provider: WasenderAPI — add WASENDER_SESSION_ID in .env');
            } else {
                $this->warn('Active provider: WasenderAPI — add WASENDER_API_KEY in .env');
            }
        } elseif (in_array($service, ['ULTRAMSG', 'ULTRA'], true)) {
            $this->warn('Active provider: UltraMsg (legacy mode)');
        } else {
            $this->error('Active provider: unknown — set WHATSAPP_SERVICE=TWILIO or WASENDER');
        }

        if (app()->configurationIsCached()) {
            $this->comment('Config is cached. WhatsApp settings are read from bootstrap/cache/config.php.');
        } else {
            $this->comment('Config is not cached. Run php artisan config:clear after updating .env.');
        }

        return 0;
    }
}
