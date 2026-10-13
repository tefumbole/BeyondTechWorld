<?php

namespace App\Services\Voice;

use App\Voice\VoiceCall;
use App\Voice\VoiceConnection;

class MaiVoiceStatus
{
    public function snapshot()
    {
        $connection = null;
        if (class_exists(VoiceConnection::class) && \Illuminate\Support\Facades\Schema::hasTable('voice_connections')) {
            $connection = VoiceConnection::where('tenant_type', 'INTERNAL')->orderBy('id')->first();
        }

        $calls = VoiceCall::query();
        $answered = (clone $calls)->where('status', 'ANSWERED');
        $ended = VoiceCall::where('status', 'ENDED');
        $avg = (int) round((float) $ended->avg('duration_seconds'));
        $todaySeconds = (int) VoiceCall::where('created_at', '>=', now()->startOfDay())->sum('duration_seconds');
        $recent = VoiceCall::orderBy('id', 'desc')->limit(15)->get(['id', 'caller_phone', 'called_number', 'status', 'status_reason', 'handling', 'transfer_status', 'duration_seconds', 'created_at']);

        return [
            'enabled' => MaiVoiceSwitch::open(),
            'recording' => false,
            'customer_voice' => false,
            'provider' => 'stub',
            'twilio_whatsapp_provider' => 'implemented',
            'whatsapp_sender' => null,
            'voice_application' => 'not_configured',
            'realtime_bridge' => false,
            'outbound_whatsapp_calls' => false,
            'live_number_connected' => false,
            'business_number' => $connection ? $connection->called_number : null,
            'ai_enabled' => $connection ? (bool) $connection->ai_enabled : false,
            'transfer_configured' => $connection && trim((string) $connection->transfer_number) !== '',
            'business_hours' => $connection ? $connection->hours() : null,
            'assistant_name' => $connection && $connection->assistant_name ? $connection->assistant_name : 'MAI',
            'language' => $connection && $connection->language ? $connection->language : 'en',
            'company_name' => $connection && $connection->company_name ? $connection->company_name : 'BeyondTechWorld',
            'active_calls' => $answered->count(),
            'failed_calls' => VoiceCall::where('status', 'REJECTED')->orWhere('status_reason', 'provider_down')->count(),
            'average_duration_seconds' => $avg,
            'transfer_requests' => VoiceCall::where('transfer_status', '!=', 'NONE')->count(),
            'daily_seconds' => $todaySeconds,
            'daily_alert' => $todaySeconds >= (int) config('voice.daily_seconds_alert', 3600),
            'recent' => $recent,
        ];
    }
}
