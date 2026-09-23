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
use App\WhatsApp\WhatsAppCall;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppConversationEvent;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppNote;
use App\WhatsApp\WhatsAppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        return view('whatsapp_hub.command_center', compact('range', 'stats', 'session'));
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
                    ];
                })->values(),
                'counts' => $counts,
            ]);
        }

        return view('whatsapp_hub.conversations', compact('list', 'q', 'filter', 'staff', 'counts', 'mode'));
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

        return view('whatsapp_hub.conversation', compact(
            'conversation', 'messages', 'notes', 'events', 'canReply', 'staff', 'context', 'lead', 'documents', 'sla', 'rentalDraft', 'rentalRequest'
        ));
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

        return redirect()->route('whatsapp.conversation', $conversation->id)
            ->with('message', 'Message sent.');
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

        return view('whatsapp_hub.calls', compact('calls', 'staff'));
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

        return view('whatsapp_hub.diagnostics', compact('session', 'diag'));
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

        return view('whatsapp_hub.settings', compact('session', 'mode', 'webhookUrl', 'sla', 'assistantEnabled', 'assistantEnv', 'assistantConfigured'));
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
        }

        return redirect()->route('whatsapp.settings')->with('message', 'Settings saved.');
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

        return back()->with('message', 'You took this conversation.');
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
        if ($request->get('from') && $request->get('to')) {
            $query->whereBetween('last_activity_at', [$request->get('from').' 00:00:00', $request->get('to').' 23:59:59']);
        }
        $mode = strtoupper((string) $request->get('mode', ''));
        if ($mode === 'AI') {
            $query->where('mode', WhatsAppConversation::MODE_AI);
        } elseif ($mode === 'HUMAN') {
            $query->where('mode', '!=', WhatsAppConversation::MODE_AI);
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
