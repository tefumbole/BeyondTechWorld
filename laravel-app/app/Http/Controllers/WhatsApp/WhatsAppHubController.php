<?php

namespace App\Http\Controllers\WhatsApp;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Http\Controllers\Controller;
use App\Services\WhatsApp\WhatsAppContextService;
use App\Services\WhatsApp\WhatsAppConversationService;
use App\Services\WhatsApp\WhatsAppHubQuery;
use App\Services\WhatsApp\WhatsAppLeadService;
use App\User;
use App\WhatsApp\Lead;
use App\WhatsApp\LeadCatalog;
use App\WhatsApp\WhatsAppCall;
use App\WhatsApp\WhatsAppContact;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppConversationEvent;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppNote;
use App\WhatsApp\WhatsAppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class WhatsAppHubController extends Controller
{
    protected $query;
    protected $conversations;
    protected $provider;
    protected $context;
    protected $leads;

    public function __construct(
        WhatsAppHubQuery $query,
        WhatsAppConversationService $conversations,
        WhatsAppProviderInterface $provider,
        WhatsAppContextService $context,
        WhatsAppLeadService $leads
    ) {
        parent::__construct();
        $this->query = $query;
        $this->conversations = $conversations;
        $this->provider = $provider;
        $this->context = $context;
        $this->leads = $leads;
    }

    public function index(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.view', 'whatsapp.manage', 'whatsapp_module'])) {
            return $deny;
        }
        $range = $this->query->range($request->get('range'), $request->get('from'), $request->get('to'));
        $stats = $this->query->commandCenter($range);
        $session = $this->provider->sessionStatus();
        $businessLine = $this->businessLine();

        return view('whatsapp_hub.command_center', compact('range', 'stats', 'session', 'businessLine'));
    }

    public function destroyConversations(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.manage', 'whatsapp.conversations'])) {
            return $deny;
        }
        $ids = array_slice(array_filter((array) $request->input('ids', [])), 0, 100);
        $removed = 0;
        foreach ($ids as $id) {
            $conversation = WhatsAppConversation::find($id);
            if (! $conversation) {
                continue;
            }
            $this->conversations->destroy($conversation);
            $removed++;
        }

        return redirect()->route('whatsapp.conversations')->with('message', $removed === 1 ? '1 conversation deleted.' : $removed.' conversations deleted.');
    }

    public function destroyConversation($id)
    {
        if ($deny = $this->denyUnless(['whatsapp.manage', 'whatsapp.conversations'])) {
            return $deny;
        }
        $conversation = WhatsAppConversation::findOrFail($id);
        $this->conversations->destroy($conversation);

        return redirect()->route('whatsapp.conversations')->with('message', 'Conversation deleted.');
    }

    public function conversations(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.conversations', 'whatsapp.view', 'whatsapp.manage'])) {
            return $deny;
        }
        $q = trim((string) $request->get('q'));
        $filter = (string) $request->get('filter', 'all');
        $mode = strtoupper((string) $request->get('mode', ''));
        if (! in_array($mode, ['AI', 'HUMAN'], true)) {
            $mode = '';
        }
        $channel = strtolower((string) $request->get('channel', 'all'));
        if (! in_array($channel, ['all', 'website', 'whatsapp'], true)) {
            $channel = 'all';
        }
        $list = $this->filteredConversations($request)->paginate(40)->appends($request->query());
        $staff = $this->staff();
        $counts = $this->inboxBadgeCounts();

        if ($request->wantsJson() || $request->get('poll')) {
            return response()->json([
                'items' => $list->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'name' => optional($c->contact)->displayName(),
                        'phone' => optional($c->contact)->display_phone,
                        'last' => $c->last_message,
                        'unread' => (int) $c->unread_count,
                        'waiting' => $c->isAwaitingStaff(),
                        'minutes' => $c->waitingMinutes(),
                        'channel' => $c->channel ?: 'whatsapp',
                    ];
                })->values(),
                'counts' => $counts,
            ]);
        }

        return view('whatsapp_hub.conversations', compact('list', 'q', 'filter', 'staff', 'counts', 'mode', 'channel'));
    }

    public function conversation($id)
    {
        if ($deny = $this->denyUnless(['whatsapp.conversations', 'whatsapp.view', 'whatsapp.manage'])) {
            return $deny;
        }
        $conversation = WhatsAppConversation::with(['contact.links', 'assignee'])->findOrFail($id);
        $this->conversations->syncIdentityLinks($conversation->contact);
        $conversation->load('contact.links');
        $this->conversations->markRead($conversation);
        $messages = WhatsAppMessage::where('conversation_id', $conversation->id)->orderBy('id')->get();
        $notes = WhatsAppNote::with('author')->where('conversation_id', $conversation->id)->orderBy('id')->get();
        $events = WhatsAppConversationEvent::with('actor')->where('conversation_id', $conversation->id)->orderByDesc('id')->limit(20)->get();
        $canReply = $this->canAny(['whatsapp.reply', 'whatsapp.manage']);
        $staff = $this->staff();
        $context = $this->context->forConversation($conversation, Auth::user());
        $lead = $context['lead'];
        $documents = $this->existingDocuments($conversation);
        $sla = $context['sla'];
        $rentalRequest = \Illuminate\Support\Facades\Schema::hasTable('whatsapp_rental_requests')
            ? \App\WhatsApp\RentalRequest::where('conversation_id', $conversation->id)->orderByDesc('id')->first()
            : null;
        $attendancePanel = \Illuminate\Support\Facades\Schema::hasTable('attendances')
            ? app(\App\Services\Attendance\AttendanceWhatsAppService::class)->panel($conversation)
            : null;
        $documentPanel = \Illuminate\Support\Facades\Schema::hasTable('whatsapp_document_requests')
            ? app(\App\Services\WhatsApp\WhatsAppDocumentService::class)->panel($conversation)
            : null;
        $internshipPanel = \Illuminate\Support\Facades\Schema::hasTable('whatsapp_internship_intakes')
            ? app(\App\Services\Internship\InternshipWhatsAppService::class)->panel($conversation)
            : null;
        $rentalDraft = null;
        if (\Illuminate\Support\Facades\Schema::hasTable('assistant_memories')) {
            $mem = \App\Assistant\AssistantMemory::where('conversation_id', $conversation->id)->first();
            if ($mem && ! empty($mem->parameters()['quotation_reference'])) {
                $rentalDraft = $mem->parameters()['quotation_reference'];
            }
        }

        if (request()->wantsJson() || request()->get('poll')) {
            return response()->json([
                'id' => $conversation->id,
                'unread' => (int) $conversation->unread_count,
                'last' => $conversation->last_message,
                'messages' => $messages->map(function ($m) {
                    return [
                        'id' => $m->id,
                        'direction' => $m->direction,
                        'body' => $m->body,
                        'type' => $m->type,
                        'status' => $m->status,
                        'created_at' => (string) $m->created_at,
                    ];
                })->values(),
            ]);
        }

        $recentChats = WhatsAppConversation::with('contact')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->limit(12)
            ->get();

        return view('whatsapp_hub.conversation', compact(
            'conversation', 'messages', 'notes', 'events', 'canReply', 'staff', 'context', 'lead', 'documents', 'sla', 'rentalDraft', 'rentalRequest', 'internshipPanel', 'attendancePanel', 'documentPanel', 'recentChats'
        ));
    }

    public function people(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.conversations', 'whatsapp.view', 'whatsapp.manage'])) {
            return $deny;
        }
        $q = trim((string) $request->get('q', ''));
        $query = WhatsAppContact::query()->orderByDesc('id');
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($rows) use ($like) {
                $rows->where('wa_name', 'like', $like)
                    ->orWhere('display_phone', 'like', $like)
                    ->orWhere('normalized_phone', 'like', $like);
                if (Schema::hasColumn('whatsapp_contacts', 'call_name')) {
                    $rows->orWhere('call_name', 'like', $like)
                        ->orWhere('relationship', 'like', $like);
                }
            });
        }
        $contacts = $query->paginate(30);
        $selected = $request->filled('contact')
            ? WhatsAppContact::find($request->get('contact'))
            : null;

        return view('whatsapp_hub.people', compact('contacts', 'q', 'selected'));
    }

    public function saveContactVoice(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.conversations', 'whatsapp.view', 'whatsapp.manage'])) {
            return $deny;
        }
        $contact = WhatsAppContact::findOrFail($id);
        $data = $request->validate([
            'relationship' => 'nullable|string|max:80',
            'call_name' => 'nullable|string|max:80',
            'preferred_language' => 'nullable|string|max:80',
            'voice_note' => 'nullable|string|max:1000',
        ]);
        $saved = app(\App\Services\Assistant\AssistantContactVoice::class)->save($contact, $data);
        if (! $saved) {
            return redirect()->back()->with('not_permitted', 'The contact notes are not ready on this server yet.');
        }

        return redirect()->back()->with('message', 'The assistant will use this when talking to '.($contact->call_name ?: $contact->displayName()).'.');
    }

    public function reply(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.reply', 'whatsapp.manage'])) {
            return $deny;
        }
        $conversation = WhatsAppConversation::with('contact')->findOrFail($id);
        $result = $this->conversations->reply($conversation, $request->input('body'), Auth::id());
        if (empty($result['success'])) {
            return redirect()->route('whatsapp.conversation', $conversation->id)
                ->with('not_permitted', $result['error'] ?? 'Send failed.');
        }
        if (isset($result['mode']) && $result['mode'] === WhatsAppConversation::MODE_HUMAN) {
            $notice = 'Human mode is on for this chat. AI will stay quiet until you send AI On.';
        } elseif (isset($result['mode']) && $result['mode'] === WhatsAppConversation::MODE_AI) {
            $notice = 'AI mode is on for this chat. It will continue from the messages already here.';
        } else {
            $notice = 'Message sent.';
        }

        return redirect()->route('whatsapp.conversation', $conversation->id)
            ->with('message', $notice);
    }

    public function tracking(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.view', 'whatsapp.conversations', 'whatsapp.manage'])) {
            return $deny;
        }
        $range = $this->query->range($request->get('range', '30d'), $request->get('from'), $request->get('to'));
        $messages = WhatsAppMessage::with(['contact', 'conversation'])
            ->whereBetween('created_at', [$range['from'], $range['to']])
            ->when($request->get('direction'), function ($q, $dir) {
                $q->where('direction', strtoupper($dir));
            })
            ->when($request->get('status'), function ($q, $status) {
                $q->where('status', strtoupper($status));
            })
            ->when($request->get('q'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('body', 'like', '%'.$term.'%')
                        ->orWhere('provider_message_id', 'like', '%'.$term.'%');
                });
            })
            ->orderByDesc('id')
            ->paginate(40)
            ->appends($request->query());

        return view('whatsapp_hub.tracking', compact('messages', 'range'));
    }

    public function calls(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.calls', 'whatsapp.manage'])) {
            return $deny;
        }
        $calls = WhatsAppCall::with(['contact', 'assignee'])->orderByDesc('called_at')->paginate(40);
        $callRequests = \Illuminate\Support\Facades\Schema::hasTable('whatsapp_call_requests')
            ? \App\WhatsApp\WhatsAppCallRequest::with(['contact', 'assignee'])->orderByDesc('id')->limit(40)->get()
            : collect();
        $staff = User::query()
            ->where(function ($q) {
                $q->where('is_deleted', false)->orWhereNull('is_deleted');
            })
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name']);

        return view('whatsapp_hub.calls', compact('calls', 'staff', 'callRequests'));
    }

    public function updateCall(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.calls', 'whatsapp.manage'])) {
            return $deny;
        }
        $call = WhatsAppCall::findOrFail($id);
        $status = strtoupper((string) $request->input('status', $call->status));
        $allowed = [
            WhatsAppCall::RECEIVED, WhatsAppCall::MISSED, WhatsAppCall::FOLLOW_UP,
            WhatsAppCall::CONTACTED, WhatsAppCall::RESOLVED,
        ];
        if (in_array($status, $allowed, true)) {
            $call->status = $status;
        }
        if ($request->has('assigned_user_id')) {
            $call->assigned_user_id = $request->input('assigned_user_id') ?: null;
        }
        if ($request->has('notes')) {
            $call->notes = $request->input('notes');
        }
        $call->save();

        return redirect()->route('whatsapp.calls')->with('message', 'Call updated.');
    }

    public function diagnostics()
    {
        if ($deny = $this->denyUnless(['whatsapp.view', 'whatsapp.settings', 'whatsapp.manage'])) {
            return $deny;
        }
        $session = $this->provider->sessionStatus();
        $diag = $this->query->diagnostics();
        $businessLine = $this->businessLine();

        return view('whatsapp_hub.diagnostics', compact('session', 'diag', 'businessLine'));
    }

    public function testOpenAi()
    {
        if ($deny = $this->denyUnless(['whatsapp.ai.manage', 'whatsapp.settings', 'whatsapp.manage'])) {
            return $deny;
        }
        $message = trim((string) request('message', ''));
        if ($message === '') {
            $message = 'What is the difference between a line array and a point source speaker?';
        }
        $withTools = request()->has('with_tools') && request('with_tools') !== '0' && request('with_tools') !== '';
        $result = app(\App\Services\Assistant\AssistantDirectConversationTester::class)
            ->run($message, ['with_tools' => $withTools]);

        $ok = ! empty($result['http_success']) && ($result['response_source'] ?? '') === 'OPENAI_DIRECT';
        $summary = sprintf(
            'source=%s model=%s tool_choice=%s tool=%s latency=%sms · %s',
            isset($result['response_source']) ? $result['response_source'] : 'n/a',
            isset($result['model']) ? $result['model'] : 'n/a',
            isset($result['tool_choice']) ? $result['tool_choice'] : 'auto',
            ! empty($result['tool_requested']) ? $result['tool_requested'] : 'none',
            isset($result['latency_ms']) ? $result['latency_ms'] : '?',
            ! empty($result['final_text'])
                ? mb_substr($result['final_text'], 0, 280)
                : (! empty($result['error']) ? ('ERROR: '.$result['error']) : 'empty')
        );

        return redirect()->route('whatsapp.diagnostics')
            ->with('openai_test_ok', $ok)
            ->with('openai_test', $summary)
            ->with('openai_test_detail', $result);
    }

    public function settings()
    {
        if ($deny = $this->denyUnless(['whatsapp.settings', 'whatsapp.manage'])) {
            return $deny;
        }
        $session = $this->provider->sessionStatus();
        $mode = WhatsAppSetting::getValue('default_conversation_mode', config('services.whatsapp.default_conversation_mode', 'HUMAN'));
        $webhookUrl = url('/api/webhooks/wasender');
        $sla = [
            'sla_normal_minutes' => (int) WhatsAppSetting::getValue('sla_normal_minutes', 30),
            'sla_warning_minutes' => (int) WhatsAppSetting::getValue('sla_warning_minutes', 60),
            'sla_critical_minutes' => (int) WhatsAppSetting::getValue('sla_critical_minutes', 240),
        ];
        $assistantEnabled = WhatsAppSetting::getValue('assistant_enabled', '0') === '1';
        $assistantEnv = (bool) config('assistant.enabled');
        $assistantConfigured = trim((string) config('assistant.api_key')) !== '';
        $aiFirst = \App\Services\Assistant\AssistantRuntimeSettings::aiFirst();
        $manualTakeover = \App\Services\Assistant\AssistantRuntimeSettings::manualReplyTakesOver();
        $collectName = \App\Services\Assistant\AssistantRuntimeSettings::collectUnknownName();
        $greetByName = \App\Services\Assistant\AssistantRuntimeSettings::greetByName();
        $handoverUserId = \App\Services\Assistant\AssistantRuntimeSettings::handoverUserId();
        $historyLimit = \App\Services\Assistant\AssistantRuntimeSettings::historyLimit();
        $clarificationLimit = \App\Services\Assistant\AssistantRuntimeSettings::maxClarifications();
        $switchPreview = app(\App\Services\WhatsApp\ConversationAiSwitchService::class)->preview();
        $staff = User::query()->orderBy('name')->limit(200)->get(['id', 'name']);
        $websiteAiEnabled = \App\Services\Assistant\AssistantRuntimeSettings::flag('website_ai_enabled', true);
        $websiteAutoGreeting = \App\Services\Assistant\AssistantRuntimeSettings::flag('website_ai_auto_greeting', true);
        $websiteHandover = \App\Services\Assistant\AssistantRuntimeSettings::flag('website_ai_handover_enabled', true);
        $websiteContinueWa = \App\Services\Assistant\AssistantRuntimeSettings::flag('website_ai_continue_whatsapp', true);
        $websiteAiName = WhatsAppSetting::getValue('website_ai_name', 'Mbole AI');
        $websiteGreetingDelay = (int) WhatsAppSetting::getValue('website_ai_greeting_delay_ms', '600');
        $websiteMetrics = [
            'conversations' => Schema::hasColumn('whatsapp_conversations', 'channel')
                ? WhatsAppConversation::where('channel', WhatsAppConversation::CHANNEL_WEBSITE)->count()
                : 0,
            'leads' => Schema::hasTable('leads')
                ? Lead::where('source', LeadCatalog::SOURCE_WEBSITE)->count()
                : 0,
        ];

        $businessLine = $this->businessLine();

        return view('whatsapp_hub.settings', compact(
            'businessLine',
            'session', 'mode', 'webhookUrl', 'sla', 'assistantEnabled', 'assistantEnv', 'assistantConfigured',
            'aiFirst', 'manualTakeover', 'collectName', 'greetByName', 'handoverUserId', 'historyLimit',
            'clarificationLimit', 'switchPreview', 'staff',
            'websiteAiEnabled', 'websiteAutoGreeting', 'websiteHandover', 'websiteContinueWa',
            'websiteAiName', 'websiteGreetingDelay', 'websiteMetrics'
        ));
    }

    public function updateSettings(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.settings', 'whatsapp.manage'])) {
            return $deny;
        }
        $mode = strtoupper((string) $request->input('default_conversation_mode', 'HUMAN'));
        if (! in_array($mode, ['AI', 'HUMAN', 'PAUSED', 'CLOSED'], true)) {
            $mode = 'HUMAN';
        }
        WhatsAppSetting::putValue('default_conversation_mode', $mode);
        foreach (['sla_normal_minutes', 'sla_warning_minutes', 'sla_critical_minutes'] as $key) {
            $val = (int) $request->input($key, 0);
            if ($val > 0 && $val <= 10080) {
                WhatsAppSetting::putValue($key, (string) $val);
            }
        }
        if ($this->canAny(['whatsapp.ai.manage', 'whatsapp.manage'])) {
            WhatsAppSetting::putValue('assistant_enabled', $request->input('assistant_enabled') ? '1' : '0');
            WhatsAppSetting::putValue('ai_first', $request->input('ai_first') ? '1' : '0');
            WhatsAppSetting::putValue('manual_reply_takes_over', $request->has('manual_reply_takes_over') ? ($request->input('manual_reply_takes_over') ? '1' : '0') : '1');
            WhatsAppSetting::putValue('assistant_collect_name', $request->input('assistant_collect_name') ? '1' : '0');
            WhatsAppSetting::putValue('assistant_greet_by_name', $request->input('assistant_greet_by_name') ? '1' : '0');
            $agent = (int) $request->input('default_handover_user_id', 0);
            WhatsAppSetting::putValue('default_handover_user_id', $agent > 0 ? (string) $agent : '0');
            $history = (int) $request->input('assistant_history_limit', 8);
            if ($history >= 2 && $history <= 20) {
                WhatsAppSetting::putValue('assistant_history_limit', (string) $history);
            }
            $clarify = (int) $request->input('assistant_max_clarifications', 4);
            if ($clarify >= 1 && $clarify <= 8) {
                WhatsAppSetting::putValue('assistant_max_clarifications', (string) $clarify);
            }
            WhatsAppSetting::putValue('website_ai_enabled', $request->input('website_ai_enabled') ? '1' : '0');
            WhatsAppSetting::putValue('website_ai_auto_greeting', $request->input('website_ai_auto_greeting') ? '1' : '0');
            WhatsAppSetting::putValue('website_ai_handover_enabled', $request->input('website_ai_handover_enabled') ? '1' : '0');
            WhatsAppSetting::putValue('website_ai_continue_whatsapp', $request->input('website_ai_continue_whatsapp') ? '1' : '0');
            $webName = trim((string) $request->input('website_ai_name', 'Mbole AI'));
            WhatsAppSetting::putValue('website_ai_name', $webName !== '' ? mb_substr($webName, 0, 80) : 'Mbole AI');
            $delay = (int) $request->input('website_ai_greeting_delay_ms', 600);
            if ($delay >= 0 && $delay <= 10000) {
                WhatsAppSetting::putValue('website_ai_greeting_delay_ms', (string) $delay);
            }
        }

        $switched = 0;
        $held = 0;
        if ($this->canAny(['whatsapp.ai.manage', 'whatsapp.manage']) && ! $request->input('assistant_enabled')) {
            WhatsAppSetting::putValue('default_conversation_mode', 'HUMAN');
            WhatsAppSetting::putValue('ai_first', '0');
            $held = app(\App\Services\WhatsApp\ConversationAiSwitchService::class)->holdOpenConversations(Auth::id());
        } elseif ($mode === 'AI' && $request->input('assistant_enabled') && $this->canAny(['whatsapp.ai.manage', 'whatsapp.manage'])) {
            $switched = app(\App\Services\WhatsApp\ConversationAiSwitchService::class)->switchEligible(Auth::id());
        }
        if ($held > 0 || ($this->canAny(['whatsapp.ai.manage', 'whatsapp.manage']) && ! $request->input('assistant_enabled'))) {
            $note = 'Settings saved. AI is off. '.$held.' chat(s) are with a person.';
        } elseif ($switched > 0) {
            $note = 'Settings saved. '.$switched.' existing chat(s) switched to AI so they get replies. Assigned, paused, and closed chats were left as they are.';
        } else {
            $note = 'Settings saved. New messages on unassigned chats will be answered by AI. Assigned, paused, and closed chats stay with staff.';
        }

        return redirect()->route('whatsapp.settings')->with('message', $note);
    }

    public function switchEligibleToAi(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.ai.manage', 'whatsapp.manage'])) {
            return $deny;
        }
        if (! $request->input('confirm')) {
            return redirect()->route('whatsapp.settings')->with('not_permitted', 'Confirm the switch before eligible conversations move to AI.');
        }
        $count = app(\App\Services\WhatsApp\ConversationAiSwitchService::class)->switchEligible(Auth::id());

        return redirect()->route('whatsapp.settings')->with('message', $count.' eligible conversation(s) switched to AI.');
    }

    public function appointments()
    {
        if ($deny = $this->denyUnless(['whatsapp.appointments', 'whatsapp.manage'])) {
            return $deny;
        }
        $rows = \App\Appointment\Appointment::orderByDesc('starts_at')->limit(100)->get();

        return view('whatsapp_hub.appointments', compact('rows'));
    }

    public function storeAvailability(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.appointments', 'whatsapp.manage'])) {
            return $deny;
        }
        $weekday = (int) $request->input('weekday');
        $start = (string) $request->input('starts_time');
        $end = (string) $request->input('ends_time');
        if ($weekday < 0 || $weekday > 6 || $start === '' || $end === '' || $start >= $end) {
            return back()->with('message', 'Enter a weekday and a window that ends after it starts.');
        }
        \App\Appointment\AppointmentAvailability::create([
            'weekday' => $weekday,
            'starts_time' => strlen($start) === 5 ? $start.':00' : $start,
            'ends_time' => strlen($end) === 5 ? $end.':00' : $end,
            'slot_minutes' => max(15, (int) $request->input('slot_minutes', 60)),
            'location' => $request->input('location'),
            'enabled' => true,
        ]);

        return back()->with('message', 'Availability window saved. WhatsApp will only offer these times.');
    }

    public function linkStatus()
    {
        if ($deny = $this->denyUnless(['whatsapp.view', 'whatsapp.manage', 'whatsapp_module', 'whatsapp.owner'])) {
            return $deny;
        }
        $link = app(\App\Services\Cloud\CloudLocalWhatsAppLink::class);
        if (! $link->applies()) {
            abort(404);
        }
        $state = $link->state(true);

        return response()->json([
            'status' => $state['status'],
            'connected' => $state['status'] === 'CONNECTED',
            'phone' => $state['phone'],
            'qr' => $state['status'] === 'AWAITING_QR' ? $state['qr'] : null,
            'label' => $link->label($state['status']),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function startLink()
    {
        if ($deny = $this->denyUnless(['whatsapp.view', 'whatsapp.manage', 'whatsapp_module', 'whatsapp.owner'])) {
            return $deny;
        }
        try {
            app(\App\Services\Cloud\CloudLocalWhatsAppLink::class)->start();
        } catch (\Exception $e) {
            return back()->with('not_permitted', $e->getMessage());
        }

        return back()->with('message', 'Scan the code with WhatsApp on your phone. Open Linked devices and choose Link a device.');
    }

    public function disconnectLink()
    {
        if ($deny = $this->denyUnless(['whatsapp.view', 'whatsapp.manage', 'whatsapp_module', 'whatsapp.owner'])) {
            return $deny;
        }
        try {
            app(\App\Services\Cloud\CloudLocalWhatsAppLink::class)->disconnect();
        } catch (\Exception $e) {
            return back()->with('not_permitted', $e->getMessage());
        }

        return back()->with('message', 'WhatsApp was disconnected for this company.');
    }

    public function groups()
    {
        if ($deny = $this->denyUnless(['whatsapp.owner', 'whatsapp.manage'])) {
            return $deny;
        }
        @set_time_limit(180);
        $summary = app(\App\Services\WhatsApp\GroupContactExportService::class)->memberships();
        $groups = isset($summary['groups']) ? $summary['groups'] : [];
        $listError = empty($summary['success']) ? (isset($summary['error']) ? $summary['error'] : 'Could not load groups.') : null;
        $ownNumber = app(\App\Services\Cloud\CloudLocalWhatsAppLink::class)->applies();
        if ($ownNumber && $listError === 'WhatsApp is not connected for this company.') {
            $listError = null;
        }
        if (! empty($summary['success']) && empty($summary['local'])) {
            app(\App\Services\WhatsApp\GroupContactExportService::class)->scheduleResolve();
        }

        return view('whatsapp_hub.groups', compact('groups', 'listError', 'ownNumber'));
    }

    public function fetchGroups()
    {
        if ($deny = $this->denyUnless(['whatsapp.owner', 'whatsapp.manage'])) {
            return $deny;
        }
        @set_time_limit(180);
        $result = app(\App\Services\WhatsApp\GroupContactExportService::class)->fetchNewGroups();
        if (empty($result['success'])) {
            return back()->with('not_permitted', isset($result['error']) ? $result['error'] : 'Could not fetch groups.');
        }
        $added = (int) $result['added'];
        $total = (int) $result['total'];
        $message = $added > 0
            ? 'Fetched '.$added.' new '.($added === 1 ? 'group' : 'groups').'. '.$total.' groups are listed.'
            : 'Fetched the latest groups. '.$total.' groups are listed.';

        return redirect()->route('whatsapp.groups')->with('message', $message);
    }

    public function fetchGroupContacts(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.owner', 'whatsapp.manage'])) {
            return $deny;
        }
        @set_time_limit(120);
        $jid = trim((string) $request->input('jid', ''));
        if (substr($jid, -5) !== '@g.us') {
            return redirect()->route('whatsapp.groups');
        }
        $result = app(\App\Services\WhatsApp\GroupContactExportService::class)->fetchGroupContacts($jid);
        if (empty($result['success'])) {
            return redirect()->route('whatsapp.groups.show', ['jid' => $jid])
                ->with('not_permitted', isset($result['error']) ? $result['error'] : 'Could not fetch contacts.');
        }
        $added = (int) $result['added'];
        $total = (int) $result['total'];
        if (! empty($result['busy'])) {
            return redirect()->route('whatsapp.groups.show', ['jid' => $jid])
                ->with('message', 'WhatsApp is busy right now. The '.$total.' contacts already saved are still listed. Fetch again in a minute for anyone new.');
        }
        $message = $added > 0
            ? 'Added '.$added.' new '.($added === 1 ? 'contact' : 'contacts').'. Numbers already in this group were skipped. This group has '.$total.' contacts.'
            : 'No new numbers. Contacts already in this group were left as they are. This group has '.$total.' contacts.';

        return redirect()->route('whatsapp.groups.show', ['jid' => $jid])->with('message', $message);
    }

    public function resolveGroup(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.owner', 'whatsapp.manage'])) {
            return $deny;
        }
        $jid = trim((string) $request->input('jid', ''));
        if (substr($jid, -5) !== '@g.us') {
            return redirect()->route('whatsapp.groups');
        }
        $pending = app(\App\Services\WhatsApp\GroupContactExportService::class)->queueResolveGroup($jid);
        $message = $pending > 0
            ? 'Resolving all '.$pending.' '.($pending === 1 ? 'number' : 'numbers').' in this group, Campay first. Names appear here as they are found.'
            : 'Every number in this group already has a Campay name, or a name you saved.';

        return redirect()->route('whatsapp.groups.show', ['jid' => $jid])->with('message', $message);
    }

    public function fetchGroupMember(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.owner', 'whatsapp.manage'])) {
            return $deny;
        }
        @set_time_limit(60);
        $jid = trim((string) $request->input('jid', ''));
        $phone = trim((string) $request->input('phone', ''));
        if (substr($jid, -5) !== '@g.us') {
            return redirect()->route('whatsapp.groups');
        }
        try {
            $result = app(\App\Services\WhatsApp\GroupContactExportService::class)->fetchMemberName($jid, $phone);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('whatsapp.groups.show', ['jid' => $jid])->with('not_permitted', $e->getMessage());
        }
        if (! empty($result['busy'])) {
            $message = 'WhatsApp is busy. Resolve that number again in a moment.';
            $key = 'not_permitted';
        } elseif (! empty($result['kept'])) {
            $message = 'That number already has a saved name, so it was left as you wrote it.';
            $key = 'message';
        } elseif (! empty($result['named'])) {
            $message = 'Resolved a name for that number.';
            $key = 'message';
        } else {
            $message = 'No name was found for that number on WhatsApp or Campay.';
            $key = 'message';
        }

        return redirect()->route('whatsapp.groups.show', ['jid' => $jid])->with($key, $message);
    }

    public function excludeGroupMember(Request $request)
    {
        return $this->changeGroupMember($request, 'exclude');
    }

    public function includeGroupMember(Request $request)
    {
        return $this->changeGroupMember($request, 'include');
    }

    public function deleteGroupMember(Request $request)
    {
        return $this->changeGroupMember($request, 'delete');
    }

    public function deleteGroupMembers(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.owner', 'whatsapp.manage'])) {
            return $deny;
        }
        $jid = trim((string) $request->input('jid', ''));
        if (substr($jid, -5) !== '@g.us') {
            return redirect()->route('whatsapp.groups');
        }
        $phones = $request->input('phones', []);
        if (! is_array($phones)) {
            $phones = [];
        }
        try {
            $deleted = app(\App\Services\WhatsApp\GroupContactExportService::class)->deleteMembers($jid, $phones);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('whatsapp.groups.show', ['jid' => $jid])->with('not_permitted', $e->getMessage());
        }
        $message = 'Deleted '.$deleted.' '.($deleted === 1 ? 'contact' : 'contacts').'. They will not come back when you fetch contacts.';

        return redirect()->route('whatsapp.groups.show', ['jid' => $jid])->with('message', $message);
    }

    public function addGroupMember(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.owner', 'whatsapp.manage'])) {
            return $deny;
        }
        $jid = trim((string) $request->input('jid', ''));
        if (substr($jid, -5) !== '@g.us') {
            return redirect()->route('whatsapp.groups');
        }
        try {
            $result = app(\App\Services\WhatsApp\GroupContactExportService::class)->addMember(
                $jid,
                $request->input('phone', ''),
                $request->input('name', '')
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('whatsapp.groups.show', ['jid' => $jid])->with('not_permitted', $e->getMessage());
        }
        $message = $result === 'already'
            ? 'That number is already in this group, so it was not added again.'
            : 'Added to this group.';

        return redirect()->route('whatsapp.groups.show', ['jid' => $jid])->with('message', $message);
    }

    protected function changeGroupMember(Request $request, $action)
    {
        if ($deny = $this->denyUnless(['whatsapp.owner', 'whatsapp.manage'])) {
            return $deny;
        }
        $jid = trim((string) $request->input('jid', ''));
        $phone = trim((string) $request->input('phone', ''));
        if (substr($jid, -5) !== '@g.us') {
            return redirect()->route('whatsapp.groups');
        }
        $groups = app(\App\Services\WhatsApp\GroupContactExportService::class);
        try {
            if ($action === 'delete') {
                $groups->deleteMember($jid, $phone);
                $message = 'Deleted. This person will not be notified and will not come back when you fetch contacts.';
            } elseif ($action === 'include') {
                $groups->includeMember($jid, $phone);
                $message = 'Included. This person will be notified again.';
            } else {
                $groups->excludeMember($jid, $phone);
                $message = 'Excluded. This person stays on the list and will not be notified in this group.';
            }
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('whatsapp.groups.show', ['jid' => $jid])->with('not_permitted', $e->getMessage());
        }

        return redirect()->route('whatsapp.groups.show', ['jid' => $jid])->with('message', $message);
    }

    public function lookupGroups(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.owner', 'whatsapp.manage'])) {
            return $deny;
        }
        @set_time_limit(90);
        $jids = preg_split('/\s*,\s*/', trim((string) $request->query('jids', '')));
        $jids = array_values(array_filter((array) $jids, function ($jid) {
            return substr((string) $jid, -5) === '@g.us';
        }));

        return response()->json(
            app(\App\Services\WhatsApp\GroupContactExportService::class)->enrich(array_slice($jids, 0, 8))
        );
    }

    public function showGroup(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.owner', 'whatsapp.manage'])) {
            return $deny;
        }
        @set_time_limit(120);
        $jid = trim((string) $request->query('jid', ''));
        if (substr($jid, -5) !== '@g.us') {
            return redirect()->route('whatsapp.groups');
        }
        $groups = app(\App\Services\WhatsApp\GroupContactExportService::class);
        $groups->scheduleContactNames($jid);
        $export = $groups->rowsForGroup($jid);
        $groupName = isset($export['name']) ? $export['name'] : 'Group';
        $contacts = isset($export['rows']) ? $export['rows'] : [];
        usort($contacts, function ($a, $b) {
            $aNamed = trim((string) $a['name']) !== '';
            $bNamed = trim((string) $b['name']) !== '';
            if ($aNamed !== $bNamed) {
                return $aNamed ? -1 : 1;
            }
            $left = $aNamed ? $a['name'] : $a['phone'];
            $right = $bNamed ? $b['name'] : $b['phone'];

            return strcasecmp((string) $left, (string) $right);
        });
        $listError = empty($export['success']) ? (isset($export['error']) ? $export['error'] : 'Could not load this group.') : null;
        $unresolved = 0;
        foreach ($contacts as $contact) {
            $label = trim((string) (isset($contact['name']) ? $contact['name'] : ''));
            $phone = isset($contact['phone']) ? (string) $contact['phone'] : '';
            if ($label === '' || preg_match('/^\+?\d[\d\s\-]+$/', $label) || strcasecmp($label, preg_replace('/\D+/', '', $phone)) === 0) {
                $unresolved++;
            }
        }

        return view('whatsapp_hub.group', compact('groupName', 'contacts', 'jid', 'listError', 'unresolved'));
    }

    public function groupContactNames(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.owner', 'whatsapp.manage'])) {
            return $deny;
        }
        $jid = trim((string) $request->query('jid', ''));
        if (substr($jid, -5) !== '@g.us') {
            return response()->json(['contacts' => []]);
        }

        return response()->json([
            'contacts' => app(\App\Services\WhatsApp\GroupContactExportService::class)->contactNameRows($jid),
        ]);
    }

    public function saveGroupDisplayName(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.owner', 'whatsapp.manage'])) {
            return $deny;
        }
        $jid = trim((string) $request->input('jid', ''));
        $phone = trim((string) $request->input('phone', ''));
        $name = trim((string) $request->input('name', ''));
        try {
            $saved = app(\App\Services\WhatsApp\GroupContactExportService::class)->saveDisplayName($jid, $phone, $name);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'name' => $saved]);
    }

    public function exportGroupContacts(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.owner', 'whatsapp.manage'])) {
            return $deny;
        }
        @set_time_limit(180);
        $service = app(\App\Services\WhatsApp\GroupContactExportService::class);
        $jid = trim((string) $request->query('jid', ''));
        $export = $jid !== '' ? $service->rowsForGroup($jid) : $service->allRows();
        if (empty($export['success'])) {
            return back()->with('not_permitted', isset($export['error']) ? $export['error'] : 'Could not export group contacts.');
        }
        $rows = $export['rows'];
        $label = isset($export['name']) ? $export['name'] : 'whatsapp-groups';
        $slug = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $label), '-');
        if ($slug === '') {
            $slug = 'whatsapp-group';
        }
        $filename = $slug.'-contacts.csv';

        return response()->stream(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Group', 'Phone', 'Name', 'Role', 'WhatsApp ID']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['group'],
                    $row['phone'],
                    $row['name'],
                    $row['role'],
                    $row['whatsapp_id'],
                ]);
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function updateGroupMode(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.owner', 'whatsapp.manage'])) {
            return $deny;
        }
        $group = \App\WhatsApp\WhatsAppGroup::findOrFail($id);
        $mode = strtoupper((string) $request->input('mode', \App\WhatsApp\WhatsAppGroup::MONITOR));
        $user = Auth::user();
        if (! $user) {
            return redirect()->guest(url('/login'));
        }
        app(\App\Services\WhatsApp\GroupRegistryService::class)->enable($group, $mode, $user);

        return back()->with('message', 'Group mode updated. Newly discovered groups stay off until enabled.');
    }

    public function assignConversation(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.assign', 'whatsapp.takeover', 'whatsapp.manage'])) {
            return $deny;
        }
        $conversation = WhatsAppConversation::findOrFail($id);
        $userId = $request->input('assigned_user_id');
        if ($request->input('me')) {
            $userId = Auth::id();
        }
        $this->conversations->assign($conversation, $userId, Auth::id());

        return back()->with('message', 'Conversation assigned.');
    }

    public function takeover($id)
    {
        if ($deny = $this->denyUnless(['whatsapp.takeover', 'whatsapp.manage'])) {
            return $deny;
        }
        $this->conversations->takeover(WhatsAppConversation::findOrFail($id), Auth::id());

        return back()->with('message', 'You took this conversation. AI will stay quiet here until you hand it back.');
    }

    public function release($id)
    {
        if ($deny = $this->denyUnless(['whatsapp.takeover', 'whatsapp.manage'])) {
            return $deny;
        }
        $this->conversations->release(WhatsAppConversation::findOrFail($id), Auth::id());

        return back()->with('message', 'Conversation released.');
    }

    public function pause($id)
    {
        if ($deny = $this->denyUnless(['whatsapp.takeover', 'whatsapp.manage'])) {
            return $deny;
        }
        $this->conversations->pause(WhatsAppConversation::findOrFail($id), Auth::id());

        return back()->with('message', 'Conversation paused.');
    }

    public function close($id)
    {
        if ($deny = $this->denyUnless(['whatsapp.takeover', 'whatsapp.manage'])) {
            return $deny;
        }
        $this->conversations->close(WhatsAppConversation::findOrFail($id), Auth::id());

        return back()->with('message', 'Conversation closed.');
    }

    public function reopen($id)
    {
        if ($deny = $this->denyUnless(['whatsapp.takeover', 'whatsapp.manage'])) {
            return $deny;
        }
        $this->conversations->reopen(WhatsAppConversation::findOrFail($id), Auth::id());

        return back()->with('message', 'Conversation reopened.');
    }

    public function note(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.notes', 'whatsapp.manage'])) {
            return $deny;
        }
        $conversation = WhatsAppConversation::findOrFail($id);
        $lead = Lead::where('conversation_id', $conversation->id)->orderByDesc('id')->first();
        $this->conversations->addNote($conversation, $request->input('body'), Auth::id(), $lead ? $lead->id : null);

        return back()->with('message', 'Internal note saved. It was not sent on WhatsApp.');
    }

    public function sendDocument(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.documents', 'whatsapp.manage'])) {
            return $deny;
        }
        $conversation = WhatsAppConversation::with('contact')->findOrFail($id);
        $path = $request->input('path');
        $name = $request->input('name') ?: basename((string) $path);
        $result = $this->conversations->sendExistingDocument($conversation, $path, $name, $request->input('caption'), Auth::id());
        if (empty($result['success'])) {
            return back()->with('not_permitted', $result['error'] ?? 'Send failed.');
        }

        return back()->with('message', 'Document sent.');
    }

    public function followUpCall(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.calls', 'whatsapp.manage'])) {
            return $deny;
        }
        $call = WhatsAppCall::with('contact')->findOrFail($id);
        $call->status = WhatsAppCall::FOLLOW_UP;
        if ($request->input('assigned_user_id') || $request->input('me')) {
            $call->assigned_user_id = $request->input('me') ? Auth::id() : $request->input('assigned_user_id');
        }
        $call->save();
        if ($call->contact && $request->input('create_lead') && $this->canAny(['whatsapp.leads.manage', 'whatsapp.manage'])) {
            $conversation = $this->conversations->openConversation($call->contact);
            $lead = $this->leads->createManual($call->contact, $conversation, Auth::id(), ['summary' => 'Follow-up from missed call']);
            if ($lead && \Illuminate\Support\Facades\Schema::hasColumn('whatsapp_calls', 'lead_id')) {
                $call->lead_id = $lead->id;
                $call->save();
            }
        }

        return back()->with('message', 'Call marked for follow-up.');
    }

    protected function filteredConversations(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $filter = (string) $request->get('filter', 'all');
        $query = WhatsAppConversation::with(['contact', 'assignee']);
        if ($q !== '') {
            $query->where(function ($outer) use ($q) {
                $outer->where('last_message', 'like', '%'.$q.'%')
                    ->orWhereHas('contact', function ($c) use ($q) {
                        $c->where('wa_name', 'like', '%'.$q.'%')
                            ->orWhere('normalized_phone', 'like', '%'.$q.'%')
                            ->orWhere('display_phone', 'like', '%'.$q.'%');
                    });
            });
        }
        if ($request->get('assigned_user_id')) {
            $query->where('assigned_user_id', $request->get('assigned_user_id'));
        }
        $from = (string) $request->get('from', '');
        $to = (string) $request->get('to', '');
        if ($from !== '' && $to !== '' && $from !== $to) {
            $query->whereBetween('last_activity_at', [$from.' 00:00:00', $to.' 23:59:59']);
        }
        $mode = strtoupper((string) $request->get('mode', ''));
        if ($mode === 'AI') {
            $query->where('mode', WhatsAppConversation::MODE_AI);
        } elseif ($mode === 'HUMAN') {
            $query->where('mode', '!=', WhatsAppConversation::MODE_AI);
        }
        $channel = strtolower((string) $request->get('channel', 'all'));
        if (Schema::hasColumn('whatsapp_conversations', 'channel')) {
            if ($channel === 'website') {
                $query->where('channel', WhatsAppConversation::CHANNEL_WEBSITE);
            } elseif ($channel === 'whatsapp') {
                $query->where(function ($q2) {
                    $q2->whereNull('channel')
                        ->orWhere('channel', WhatsAppConversation::CHANNEL_WHATSAPP)
                        ->orWhere('channel', '');
                });
            }
        }
        if ($filter === 'unread') {
            $query->where('unread_count', '>', 0);
        } elseif ($filter === 'awaiting') {
            $query->where(function ($q2) {
                $q2->whereColumn('last_incoming_at', '>', 'last_outgoing_at')
                    ->orWhere(function ($inner) {
                        $inner->whereNotNull('last_incoming_at')->whereNull('last_outgoing_at');
                    });
            })->where('status', '!=', WhatsAppConversation::STATUS_CLOSED);
        } elseif ($filter === 'mine') {
            $query->where('assigned_user_id', Auth::id());
        } elseif ($filter === 'unassigned') {
            $query->whereNull('assigned_user_id');
        } elseif ($filter === 'closed') {
            $query->where('status', WhatsAppConversation::STATUS_CLOSED);
        } elseif ($filter === 'website' && Schema::hasColumn('whatsapp_conversations', 'channel')) {
            $query->where('channel', WhatsAppConversation::CHANNEL_WEBSITE);
        } elseif (in_array($filter, ['customers', 'employees', 'interns'], true)) {
            $role = $filter === 'interns' ? 'intern' : substr($filter, 0, -1);
            $query->whereHas('contact.links', function ($l) use ($role) {
                $l->where('role', $role);
            });
        } elseif ($filter === 'leads') {
            $query->whereHas('contact', function ($c) {
                $c->whereHas('links', function ($l) {
                    $l->whereRaw('1=0');
                })->orWhereDoesntHave('links');
            });
        }

        return $query->orderByDesc('last_activity_at');
    }

    protected function inboxBadgeCounts()
    {
        return [
            'unread' => WhatsAppConversation::where('unread_count', '>', 0)->count(),
            'awaiting' => WhatsAppConversation::where(function ($q) {
                $q->whereColumn('last_incoming_at', '>', 'last_outgoing_at')
                    ->orWhere(function ($inner) {
                        $inner->whereNotNull('last_incoming_at')->whereNull('last_outgoing_at');
                    });
            })->where('status', '!=', WhatsAppConversation::STATUS_CLOSED)->count(),
        ];
    }

    protected function staff()
    {
        return User::query()
            ->where(function ($q) {
                $q->where('is_deleted', false)->orWhereNull('is_deleted');
            })
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name']);
    }

    protected function existingDocuments(WhatsAppConversation $conversation)
    {
        $out = [];
        $contact = $conversation->contact;
        if (! $contact) {
            return $out;
        }
        $dir = public_path('quotation');
        if (is_dir($dir)) {
            foreach (glob($dir.'/quotation_*_invoice.pdf') ?: [] as $file) {
                $out[] = ['path' => $file, 'name' => basename($file), 'label' => 'Quotation '.basename($file)];
            }
        }
        $dir2 = public_path('uploads/quotations');
        if (is_dir($dir2)) {
            foreach (glob($dir2.'/*.pdf') ?: [] as $file) {
                $out[] = ['path' => $file, 'name' => basename($file), 'label' => 'Upload '.basename($file)];
            }
        }

        return array_slice($out, 0, 20);
    }

    public function attendance()
    {
        if ($deny = $this->denyUnless(['whatsapp.attendance', 'whatsapp.attendance.view', 'whatsapp.manage'])) {
            return $deny;
        }
        $metrics = app(\App\Services\Attendance\AttendanceWhatsAppService::class)->metrics();
        $open = \Illuminate\Support\Facades\Schema::hasTable('attendances')
            ? \App\Attendance::where(function ($q) {
                $q->whereNull('checkout')->orWhere('checkout', '');
            })->orderByDesc('id')->limit(30)->get()
            : collect();
        $corrections = \Illuminate\Support\Facades\Schema::hasTable('attendance_correction_requests')
            ? \App\AttendanceCorrection::orderByDesc('id')->limit(20)->get()
            : collect();
        $canLocation = $this->canAny(['whatsapp.attendance.location', 'whatsapp.manage']);
        $canCorrect = $this->canAny(['whatsapp.attendance.corrections']);

        return view('whatsapp_hub.attendance', compact('metrics', 'open', 'corrections', 'canLocation', 'canCorrect'));
    }

    public function approveCorrection(\Illuminate\Http\Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.attendance.corrections'])) {
            return $deny;
        }
        $result = app(\App\Services\Attendance\AttendanceWhatsAppService::class)->approveCorrection(
            $id,
            Auth::user(),
            $request->input('checkout')
        );
        if (empty($result['success'])) {
            return redirect()->back()->with('not_permitted', 'That correction was not applied.');
        }

        return redirect()->back()->with('message', 'Attendance updated from the approved correction.');
    }

    public function internship()
    {
        if ($deny = $this->denyUnless(['whatsapp.internship', 'whatsapp.internship.view', 'whatsapp.manage'])) {
            return $deny;
        }
        $metrics = app(\App\Services\Internship\InternshipWhatsAppService::class)->metrics();
        $intakes = \Illuminate\Support\Facades\Schema::hasTable('whatsapp_internship_intakes')
            ? \App\WhatsApp\InternshipIntake::orderByDesc('id')->limit(30)->get()
            : collect();
        $failures = \Illuminate\Support\Facades\Schema::hasTable('whatsapp_internship_intake_files')
            ? \App\WhatsApp\InternshipIntakeFile::whereIn('status', ['failed', 'rejected'])->orderByDesc('id')->limit(20)->get()
            : collect();

        return view('whatsapp_hub.internship', compact('metrics', 'intakes', 'failures'));
    }

    public function tenants()
    {
        if ($deny = $this->denyUnless(['whatsapp.tenants.view', 'whatsapp.tenants.manage', 'tenancies.view'])) {
            return $deny;
        }
        $metrics = app(\App\Services\Property\PropertyMetrics::class)->all();
        $maintenance = \Illuminate\Support\Facades\Schema::hasTable('property_maintenance_requests')
            ? \App\Property\MaintenanceRequest::orderByDesc('id')->limit(30)->get()
            : collect();

        return view('whatsapp_hub.tenants', compact('metrics', 'maintenance'));
    }

    public function bills()
    {
        if ($deny = $this->denyUnless(['whatsapp.bills.view', 'whatsapp.bills.manage', 'billpayments.view'])) {
            return $deny;
        }
        $metrics = app(\App\Services\Property\PropertyMetrics::class)->all();
        $requests = \Illuminate\Support\Facades\Schema::hasTable('bill_payment_requests')
            ? \App\Property\BillPaymentRequest::orderByDesc('id')->limit(50)->get()
            : collect();

        return view('whatsapp_hub.bills', compact('metrics', 'requests'));
    }

    public function documents()
    {
        if ($deny = $this->denyUnless(['whatsapp.documents', 'whatsapp.documents.view', 'whatsapp.manage'])) {
            return $deny;
        }
        $metrics = app(\App\Services\WhatsApp\WhatsAppDocumentService::class)->metrics();
        $requests = \Illuminate\Support\Facades\Schema::hasTable('whatsapp_document_requests')
            ? \App\WhatsApp\WhatsAppDocumentRequest::with('contact')->orderByDesc('id')->limit(100)->get()
            : collect();

        return view('whatsapp_hub.documents', compact('metrics', 'requests'));
    }

    public function retryDocument($id)
    {
        if ($deny = $this->denyUnless(['whatsapp.documents.retry', 'whatsapp.documents.manage', 'whatsapp.manage'])) {
            return $deny;
        }
        $request = \App\WhatsApp\WhatsAppDocumentRequest::findOrFail($id);
        app(\App\Services\WhatsApp\WhatsAppDocumentService::class)->retrySend($request);

        return back()->with('message', 'Document send was retried.');
    }

    public function invalidateVerification($id)
    {
        if ($deny = $this->denyUnless(['whatsapp.verification.invalidate', 'whatsapp.manage'])) {
            return $deny;
        }
        $session = \App\WhatsApp\WhatsAppVerificationSession::findOrFail($id);
        app(\App\Services\WhatsApp\WhatsAppVerificationService::class)->invalidateContact($session->whatsapp_contact_id);

        return back()->with('message', 'Verification was invalidated.');
    }

    protected function templateRows()
    {
        $rows = [];
        $seen = [];
        foreach (array_merge(\App\Support\ApprovedWhatsAppTemplates::forHub(), \App\Support\TwilioEquivalence::catalog()) as $row) {
            $name = $row['name'] ?? '';
            if ($name === '' || isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $rows[] = $row;
        }

        return $rows;
    }

    protected function businessLine()
    {
        $twilio = app(\App\Services\TwilioWhatsAppService::class);

        return [
            'configured' => $twilio->isConfigured(),
            'display' => $twilio->displayNumber(),
            'templates' => $this->templateRows(),
        ];
    }

    protected function denyUnless(array $names)
    {
        if ($this->canAny($names)) {
            return null;
        }

        return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access WhatsApp Hub.');
    }

    protected function canAny(array $names)
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }
        $role = Role::find($user->role_id);
        if (! $role) {
            return false;
        }
        foreach ($names as $name) {
            try {
                if ($role->hasPermissionTo($name)) {
                    return true;
                }
            } catch (\Exception $e) {
            }
        }

        return false;
    }
}
