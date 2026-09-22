<?php

namespace App\Services\WhatsApp;

use App\WhatsApp\WhatsAppCall;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppWebhookEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsAppHubQuery
{
    /**
     * @return array{from:Carbon,to:Carbon,label:string,preset:string}
     */
    public function range($preset, $from = null, $to = null)
    {
        $preset = $preset ?: 'today';
        $end = Carbon::now()->endOfDay();
        if ($preset === 'yesterday') {
            $start = Carbon::yesterday()->startOfDay();
            $end = Carbon::yesterday()->endOfDay();
        } elseif ($preset === '7d') {
            $start = Carbon::now()->subDays(6)->startOfDay();
        } elseif ($preset === '30d') {
            $start = Carbon::now()->subDays(29)->startOfDay();
        } elseif ($preset === 'custom' && $from && $to) {
            $start = Carbon::parse($from)->startOfDay();
            $end = Carbon::parse($to)->endOfDay();
        } else {
            $preset = 'today';
            $start = Carbon::today()->startOfDay();
        }

        return [
            'from' => $start,
            'to' => $end,
            'label' => $start->toDateString().' – '.$end->toDateString(),
            'preset' => $preset,
        ];
    }

    public function commandCenter(array $range)
    {
        $from = $range['from'];
        $to = $range['to'];

        return [
            'messages_today' => WhatsAppMessage::whereBetween('created_at', [$from, $to])->count(),
            'incoming_today' => WhatsAppMessage::where('direction', WhatsAppMessage::DIR_IN)->whereBetween('created_at', [$from, $to])->count(),
            'outgoing_today' => WhatsAppMessage::where('direction', WhatsAppMessage::DIR_OUT)->whereBetween('created_at', [$from, $to])->count(),
            'unread' => (int) WhatsAppConversation::sum('unread_count'),
            'failed' => WhatsAppMessage::where('status', WhatsAppMessage::STATUS_FAILED)->whereBetween('created_at', [$from, $to])->count(),
            'calls' => WhatsAppCall::whereBetween('called_at', [$from, $to])->count(),
            'webhooks' => WhatsAppWebhookEvent::whereBetween('received_at', [$from, $to])->count(),
            'webhooks_failed' => WhatsAppWebhookEvent::where('status', WhatsAppWebhookEvent::FAILED)->whereBetween('received_at', [$from, $to])->count(),
            'webhooks_processed' => WhatsAppWebhookEvent::whereIn('status', [WhatsAppWebhookEvent::PROCESSED, WhatsAppWebhookEvent::IGNORED])->whereBetween('received_at', [$from, $to])->count(),
            'recent_conversations' => WhatsAppConversation::with('contact')->orderByDesc('last_activity_at')->limit(8)->get(),
            'recent_failed' => WhatsAppMessage::with('contact')->where('status', WhatsAppMessage::STATUS_FAILED)->orderByDesc('id')->limit(8)->get(),
            'recent_calls' => WhatsAppCall::with('contact')->orderByDesc('called_at')->limit(8)->get(),
            'recent_events' => WhatsAppWebhookEvent::orderByDesc('id')->limit(8)->get(),
        ];
    }

    public function diagnostics()
    {
        $today = [Carbon::today()->startOfDay(), Carbon::now()->endOfDay()];
        $failedJobs = 0;
        if (Schema::hasTable('failed_jobs')) {
            $failedJobs = DB::table('failed_jobs')->count();
        }
        $pendingJobs = 0;
        if (Schema::hasTable('jobs')) {
            $pendingJobs = DB::table('jobs')->where('queue', 'whatsapp')->count();
            $pendingJobs += DB::table('jobs')->where('queue', 'default')->count();
        }

        return [
            'last_webhook' => WhatsAppWebhookEvent::orderByDesc('id')->first(),
            'last_incoming' => WhatsAppMessage::where('direction', WhatsAppMessage::DIR_IN)->orderByDesc('id')->first(),
            'last_outgoing' => WhatsAppMessage::where('direction', WhatsAppMessage::DIR_OUT)->orderByDesc('id')->first(),
            'last_delivered' => WhatsAppMessage::whereNotNull('delivered_at')->orderByDesc('delivered_at')->first(),
            'last_read' => WhatsAppMessage::whereNotNull('read_at')->orderByDesc('read_at')->first(),
            'last_call' => WhatsAppCall::orderByDesc('id')->first(),
            'last_processed' => WhatsAppWebhookEvent::where('status', WhatsAppWebhookEvent::PROCESSED)->orderByDesc('processed_at')->first(),
            'last_failed' => WhatsAppWebhookEvent::where('status', WhatsAppWebhookEvent::FAILED)->orderByDesc('id')->first(),
            'recent_failures' => WhatsAppWebhookEvent::where('status', WhatsAppWebhookEvent::FAILED)->orderByDesc('id')->limit(10)->get(),
            'events_today' => WhatsAppWebhookEvent::whereBetween('received_at', $today)->count(),
            'processed_today' => WhatsAppWebhookEvent::whereIn('status', [WhatsAppWebhookEvent::PROCESSED, WhatsAppWebhookEvent::IGNORED])->whereBetween('received_at', $today)->count(),
            'failed_today' => WhatsAppWebhookEvent::where('status', WhatsAppWebhookEvent::FAILED)->whereBetween('received_at', $today)->count(),
            'pending_today' => WhatsAppWebhookEvent::whereIn('status', [WhatsAppWebhookEvent::RECEIVED, WhatsAppWebhookEvent::QUEUED, WhatsAppWebhookEvent::PROCESSING])->count(),
            'failed_jobs' => $failedJobs,
            'pending_jobs' => $pendingJobs,
            'queue_connection' => config('queue.default'),
            'signature_configured' => WaSenderSignature::isConfigured(),
            'wasender_key' => ! empty(config('services.whatsapp.wasender_api_key')) ? 'Configured' : 'Missing',
            'wasender_session' => ! empty(config('services.whatsapp.wasender_session_id')) ? 'Configured' : 'Missing',
            'webhook_secret' => WaSenderSignature::isConfigured() ? 'Configured' : 'Missing',
        ];
    }
}
