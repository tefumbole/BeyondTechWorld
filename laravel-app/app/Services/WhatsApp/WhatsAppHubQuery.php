<?php

namespace App\Services\WhatsApp;

use App\User;
use App\WhatsApp\Lead;
use App\WhatsApp\LeadCatalog;
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
            'new_leads' => Schema::hasTable('leads') ? Lead::where('status', LeadCatalog::STATUS_NEW)->count() : 0,
            'unassigned_leads' => Schema::hasTable('leads') ? Lead::whereNull('assigned_user_id')->whereNotIn('status', LeadCatalog::closedStatuses())->count() : 0,
            'followups_due' => Schema::hasTable('leads') ? Lead::whereNotNull('follow_up_at')->where('follow_up_at', '<=', Carbon::now())->whereNotIn('status', LeadCatalog::closedStatuses())->count() : 0,
            'awaiting_staff' => WhatsAppConversation::where(function ($q) {
                $q->whereColumn('last_incoming_at', '>', 'last_outgoing_at')
                    ->orWhere(function ($inner) {
                        $inner->whereNotNull('last_incoming_at')->whereNull('last_outgoing_at');
                    });
            })->whereNotIn('status', [WhatsAppConversation::STATUS_CLOSED])->count(),
            'unread_conversations' => WhatsAppConversation::where('unread_count', '>', 0)->count(),
            'missed_calls' => WhatsAppCall::whereIn('status', [WhatsAppCall::MISSED, WhatsAppCall::FOLLOW_UP, WhatsAppCall::RECEIVED])->count(),
            'needs_attention' => WhatsAppConversation::with('contact')
                ->where(function ($q) {
                    $q->whereColumn('last_incoming_at', '>', 'last_outgoing_at')
                        ->orWhere(function ($inner) {
                            $inner->whereNotNull('last_incoming_at')->whereNull('last_outgoing_at');
                        });
                })
                ->whereNotIn('status', [WhatsAppConversation::STATUS_CLOSED])
                ->orderBy('last_incoming_at')
                ->limit(8)
                ->get(),
            'overdue_followups' => Schema::hasTable('leads')
                ? Lead::with('assignee')->whereNotNull('follow_up_at')->where('follow_up_at', '<=', Carbon::now())->whereNotIn('status', LeadCatalog::closedStatuses())->orderBy('follow_up_at')->limit(8)->get()
                : collect(),
            'staff_workload' => $this->staffWorkload(),
        ];
    }

    public function staffWorkload()
    {
        $rows = WhatsAppConversation::query()
            ->select('assigned_user_id')
            ->whereNotNull('assigned_user_id')
            ->whereNotIn('status', [WhatsAppConversation::STATUS_CLOSED])
            ->get();
        $ids = $rows->pluck('assigned_user_id')->unique()->filter()->all();
        if ($ids === []) {
            return collect();
        }
        $users = User::whereIn('id', $ids)->get()->keyBy('id');
        $out = [];
        foreach ($ids as $id) {
            $user = $users->get($id);
            $out[] = [
                'user' => $user,
                'open' => WhatsAppConversation::where('assigned_user_id', $id)->whereNotIn('status', [WhatsAppConversation::STATUS_CLOSED])->count(),
                'waiting' => WhatsAppConversation::where('assigned_user_id', $id)->where(function ($q) {
                    $q->whereColumn('last_incoming_at', '>', 'last_outgoing_at')
                        ->orWhere(function ($inner) {
                            $inner->whereNotNull('last_incoming_at')->whereNull('last_outgoing_at');
                        });
                })->count(),
                'leads' => Schema::hasTable('leads')
                    ? Lead::where('assigned_user_id', $id)->whereNotIn('status', LeadCatalog::closedStatuses())->count()
                    : 0,
            ];
        }

        return collect($out);
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
            'assistant_enabled' => app(\App\Services\Assistant\AssistantPolicyService::class)->globallyEnabled(),
            'assistant_provider' => trim((string) config('assistant.api_key')) !== '' ? 'Configured' : 'Missing',
            'assistant_last_ok' => \Illuminate\Support\Facades\Schema::hasTable('assistant_activities')
                ? \App\Assistant\AssistantActivity::where('status', \App\Assistant\AssistantActivity::COMPLETED)->orderByDesc('id')->first()
                : null,
            'assistant_last_fail' => \Illuminate\Support\Facades\Schema::hasTable('assistant_activities')
                ? \App\Assistant\AssistantActivity::where('status', \App\Assistant\AssistantActivity::FAILED)->orderByDesc('id')->first()
                : null,
            'assistant_avg_ms' => \Illuminate\Support\Facades\Schema::hasTable('assistant_activities')
                ? (int) \App\Assistant\AssistantActivity::whereNotNull('duration_ms')->avg('duration_ms')
                : 0,
            'assistant_handovers' => \Illuminate\Support\Facades\Schema::hasTable('assistant_activities')
                ? \App\Assistant\AssistantActivity::where('status', \App\Assistant\AssistantActivity::HANDED_OVER)->count()
                : 0,
            'assistant_tool_failures' => \Illuminate\Support\Facades\Schema::hasTable('assistant_activities')
                ? \App\Assistant\AssistantActivity::whereNotNull('tool_status')->where('tool_status', '!=', 'ok')->orderByDesc('id')->limit(8)->get()
                : collect(),
        ];
    }
}
