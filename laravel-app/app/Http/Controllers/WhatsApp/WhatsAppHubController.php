<?php

namespace App\Http\Controllers\WhatsApp;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Http\Controllers\Controller;
use App\Services\WhatsApp\WhatsAppConversationService;
use App\Services\WhatsApp\WhatsAppHubQuery;
use App\User;
use App\WhatsApp\WhatsAppCall;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class WhatsAppHubController extends Controller
{
    protected $query;
    protected $conversations;
    protected $provider;

    public function __construct(WhatsAppHubQuery $query, WhatsAppConversationService $conversations, WhatsAppProviderInterface $provider)
    {
        parent::__construct();
        $this->query = $query;
        $this->conversations = $conversations;
        $this->provider = $provider;
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
        $list = WhatsAppConversation::with('contact')
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('contact', function ($c) use ($q) {
                    $c->where('wa_name', 'like', '%'.$q.'%')
                        ->orWhere('normalized_phone', 'like', '%'.$q.'%')
                        ->orWhere('display_phone', 'like', '%'.$q.'%');
                });
            })
            ->orderByDesc('last_activity_at')
            ->paginate(40);

        return view('whatsapp_hub.conversations', compact('list', 'q'));
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
        $canReply = $this->canAny(['whatsapp.reply', 'whatsapp.manage']);

        return view('whatsapp_hub.conversation', compact('conversation', 'messages', 'canReply'));
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

        return view('whatsapp_hub.settings', compact('session', 'mode', 'webhookUrl'));
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

        return redirect()->route('whatsapp.settings')->with('message', 'Settings saved.');
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
