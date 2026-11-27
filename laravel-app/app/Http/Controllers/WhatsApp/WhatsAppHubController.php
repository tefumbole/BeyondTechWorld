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

        if (! $request->wantsJson() && ! $request->get('poll')) {
            return redirect()->route('whatsapp.chats');
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
        $messages = WhatsAppMessage::visible()->where('conversation_id', $conversation->id)->orderBy('id')->get();
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
                        'body' => $m->hubText(),
                        'aside' => $m->hubAside(),
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

        $translatorOn = app(\App\Services\WhatsApp\WhatsAppTranslator::class)->activeFor($conversation->id);

        return view('whatsapp_hub.conversation', compact(
            'conversation', 'messages', 'notes', 'events', 'canReply', 'staff', 'context', 'lead', 'documents', 'sla', 'rentalDraft', 'rentalRequest', 'internshipPanel', 'attendancePanel', 'documentPanel', 'recentChats', 'translatorOn'
        ));
    }

    public function linkedSend(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.reply', 'whatsapp.view', 'whatsapp.manage'])) {
            return $deny;
        }
        $phone = $this->linkedMaiPhone();
        $to = trim((string) $request->input('to'));
        if (\App\Services\Mai\MaiBusinessLine::is($phone) || \App\Services\Mai\MaiBusinessLine::is($to)) {
            return redirect()->to(route('whatsapp.chats'))->with('not_permitted', 'That number stays on the business WhatsApp line.');
        }
        $text = trim((string) $request->input('text'));
        $back = route('whatsapp.chats', ['jid' => $to]);
        if ($text === '' || ! preg_match('/^([1-9][0-9]{6,16}@s\.whatsapp\.net|[0-9]+(-[0-9]+)?@g\.us|[1-9][0-9]{7,14})$/', $to)) {
            return redirect()->to($back)->with('not_permitted', 'Enter a message.');
        }
        $result = app(\App\Services\Cloud\MaiWhatsAppEngine::class)->send($phone, $to, $text);
        if (empty($result['success'])) {
            return redirect()->to($back)->with('not_permitted', isset($result['error']) ? $result['error'] : 'The message was not sent.');
        }

        return redirect()->to($back)->with('message', 'Sent from +'.$phone.'.');
    }

    protected function linkedMaiPhone()
    {
        return '250794006160';
    }

    protected function linkedPhoneInbox(Request $request)
    {
        $phone = $this->linkedMaiPhone();
        $engine = app(\App\Services\Cloud\MaiWhatsAppEngine::class);
        $engine->open($phone);
        $state = $engine->status($phone, false);
        $chats = ($state['status'] ?? '') === 'CONNECTED' ? $engine->chats($phone) : [];
        $jid = trim((string) $request->get('jid'));
        if (! preg_match('/^([1-9][0-9]{6,16}@s\.whatsapp\.net|[0-9]+(-[0-9]+)?@g\.us)$/', $jid)) {
            $jid = '';
        }
        $messages = [];
        $open = null;
        if ($jid !== '') {
            foreach ($chats as $chat) {
                if ($chat['jid'] === $jid) {
                    $open = $chat;
                    break;
                }
            }
            if (! $open) {
                $open = ['jid' => $jid, 'name' => '+'.preg_replace('/\D/', '', strtok($jid, '@')), 'phone' => '', 'preview' => '', 'unread' => 0];
            }
            $openDigits = preg_replace('/\D/', '', (string) strtok($jid, '@'));
            foreach ($engine->messages($phone) as $row) {
                $from = (string) ($row['from'] ?? '');
                if ($from === $jid) {
                    $messages[] = $row;
                    continue;
                }
                if (strpos($from, '@s.whatsapp.net') === false && strpos($from, '@g.us') === false) {
                    continue;
                }
                $fromDigits = preg_replace('/\D/', '', (string) strtok($from, '@'));
                if ($openDigits !== '' && $fromDigits !== '' && substr($fromDigits, -9) === substr($openDigits, -9)) {
                    $messages[] = $row;
                }
            }
        }

        return response()
            ->view('whatsapp_hub.linked_phone', [
                'phone' => $phone,
                'session' => $state,
                'chats' => $chats,
                'open' => $open,
                'messages' => $messages,
            ])
            ->header('Cache-Control', 'no-store');
    }

    public function chats(Request $request)
    {
        if (\App\Support\MaiHost::is($request)) {
            if (! Auth::check()) {
                return redirect()->to(url('/login?mai=1'));
            }
            $personal = $this->personalHub();
            if (! $personal) {
                return redirect()->route('whatsapp.mai')->with('not_permitted', 'Use your personal WhatsApp number.');
            }
            $engine = app(\App\Services\Cloud\MaiWhatsAppEngine::class);
            $linked = $engine->status($personal->phone_number, false);
            if (($linked['status'] ?? '') !== 'CONNECTED') {
                return redirect()->route('whatsapp.mai.link');
            }
            if ($personal->status !== 'CONNECTED') {
                $personal->status = 'CONNECTED';
                $personal->connected_at = $personal->connected_at ?: \Carbon\Carbon::now();
                $personal->save();
            }
            app(\App\Services\Mai\PersonalHub::class)->sync($personal);
        }
        if ($deny = $this->denyUnless(['whatsapp.conversations', 'whatsapp.view', 'whatsapp.manage'])) {
            return $deny;
        }
        $this->keepSignedIn();
        // Staff chats stay on the WaSender business line. ?line=main is the same
        // page. A line, provider, or phone parameter cannot switch this inbox.
        if ($request->get('open')) {
            return response()->json($this->chatThread((int) $request->get('chat')))->header('Cache-Control', 'no-store');
        }
        if ($request->get('poll')) {
            if ($request->get('names')) {
                $named = $this->inboxConversations();
                try {
                    $this->conversations->preferPhoneNames($named, true);
                } catch (\Throwable $e) {
                }
                $names = [];
                foreach ($named as $row) {
                    $names[] = [
                        'id' => (int) $row->id,
                        'name' => optional($row->contact)->displayName() ?: 'WhatsApp',
                    ];
                }

                return response()->json(['ok' => true, 'names' => $names])->header('Cache-Control', 'no-store');
            }

            return response()->json($this->chatsFeed($request))->header('Cache-Control', 'no-store');
        }
        $conversations = $this->inboxConversations();
        $personal = $this->personalHub();
        $groups = [];
        if ($personal) {
            $groups = app(\App\Services\Mai\PersonalHub::class)->groups($personal);
        } else {
            try {
                $groups = app(\App\Services\WhatsApp\GroupContactExportService::class)->namedGroups();
            } catch (\Throwable $e) {
                $groups = [];
            }
        }
        $open = null;
        $messages = collect();
        $hasEarlier = false;
        $group = null;
        $groupMessages = collect();
        $jid = trim((string) $request->get('group', ''));
        if ($jid !== '') {
            foreach ($groups as $row) {
                if ((string) $row['jid'] === $jid) {
                    $group = $row;
                    break;
                }
            }
            if (! $group && substr($jid, -5) === '@g.us') {
                $group = ['jid' => $jid, 'name' => 'WhatsApp group', 'members' => null];
            }
            if ($personal && $group) {
                $groupMessages = $this->personalGroupMessages($personal, $group['jid']);
            } elseif ($group && Schema::hasTable('whatsapp_group_messages')) {
                $record = \App\WhatsApp\WhatsAppGroup::where('group_jid', $group['jid'])->first();
                if ($record) {
                    $groupMessages = \App\WhatsApp\WhatsAppGroupMessage::where('group_id', $record->id)
                        ->orderByDesc('id')
                        ->limit(200)
                        ->get()
                        ->reverse()
                        ->values();
                }
            }
        } elseif ($request->filled('chat')) {
            $open = $this->inboxConversation($request->get('chat'));
            if ($open) {
                $this->conversations->readOnWeb($open);
                $messages = WhatsAppMessage::visible()->where('conversation_id', $open->id)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->limit(60)
                    ->get()
                    ->reverse()
                    ->values();
                $first = $messages->first();
                $hasEarlier = $first
                    ? WhatsAppMessage::visible()->where('conversation_id', $open->id)->where('id', '<', $first->id)->exists()
                    : false;
            }
        }
        $canReply = $personal ? true : $this->canAny(['whatsapp.reply', 'whatsapp.manage']);
        $pane = (string) $request->get('pane', 'chats');
        if (! in_array($pane, ['chats', 'updates', 'calls', 'media', 'more', 'announcements', 'help'], true)) {
            $pane = 'chats';
        }
        $calls = collect();
        $callChats = collect();
        $mediaItems = collect();
        $updates = collect();
        if ($pane === 'more' || $pane === 'updates' || $pane === 'media') {
            if (Schema::hasTable('wa_announcements')) {
                $updates = \App\WaAnnouncement::orderByDesc('id')->limit(40)->get();
            }
            $mediaQuery = WhatsAppMessage::with('contact')
                ->whereIn('type', ['IMAGE', 'VIDEO', 'AUDIO', 'DOCUMENT']);
            if ($personal) {
                $mediaQuery->whereIn('conversation_id', WhatsAppConversation::where('mai_session_id', $personal->id)->pluck('id'));
            }
            $mediaItems = $mediaQuery->orderByDesc('id')->limit(40)->get();
        }
        if ($pane === 'calls' && Schema::hasTable('whatsapp_calls')) {
            $calls = WhatsAppCall::with('contact')->orderByDesc('called_at')->orderByDesc('id')->limit(80)->get();
            if ($personal) {
                $mine = WhatsAppConversation::where('mai_session_id', $personal->id)->pluck('contact_id')->all();
                $calls = $calls->whereIn('contact_id', $mine)->values();
            }
            $contactIds = $calls->pluck('contact_id')->filter()->unique()->values();
            if ($contactIds->count()) {
                $callChats = WhatsAppConversation::whereIn('contact_id', $contactIds)
                    ->when($personal, function ($query) use ($personal) {
                        $query->where('mai_session_id', $personal->id);
                    })
                    ->orderByDesc('id')
                    ->get()
                    ->groupBy('contact_id')
                    ->map(function ($rows) {
                        return $rows->first()->id;
                    });
            }
        }

        $phoneLink = $this->phoneLinkState();
        $listFilter = (string) $request->get('filter', $group ? 'groups' : 'all');
        if (! in_array($listFilter, ['all', 'unread', 'read', 'groups'], true)) {
            $listFilter = 'all';
        }

        $translator = app(\App\Services\WhatsApp\WhatsAppTranslator::class);
        $translatorOn = $translator->isOn();
        $translatorLanguages = $translator->languages();
        $destination = $open ? $translator->destinationFor($open->id) : null;
        $staffLanguage = $translator->staffLanguage();
        $defaultReply = $translator->defaultReply();
        $enterSends = $translator->enterSends();
        $sessionPhone = '';
        if ($personal) {
            $sessionPhone = '+'.preg_replace('/\D/', '', (string) $personal->phone_number);
            $phoneLink = ['disconnected' => false, 'status' => 'connected', 'at' => null];
        } else {
            try {
                $sessionPhone = app(\App\Services\BeyondWasenderService::class)->sessionPhone();
            } catch (\Throwable $e) {
                $sessionPhone = '';
            }
            if ($sessionPhone === '') {
                $sessionPhone = '+237 675 321 739';
            }
        }

        $personalHub = (bool) $personal;

        return view('whatsapp_hub.chats', compact(
            'conversations', 'groups', 'open', 'messages', 'hasEarlier', 'group', 'groupMessages', 'canReply',
            'pane', 'calls', 'callChats', 'mediaItems', 'updates', 'phoneLink', 'listFilter', 'translatorOn', 'sessionPhone',
            'translatorLanguages', 'destination', 'staffLanguage', 'defaultReply', 'enterSends', 'personalHub'
        ));
    }

    protected function phoneLinkState()
    {
        try {
            $live = $this->provider->sessionStatus();
            if (is_array($live) && ! empty($live['connected'])) {
                return ['disconnected' => false, 'status' => 'connected', 'at' => null];
            }
        } catch (\Throwable $e) {
        }

        $cached = \Illuminate\Support\Facades\Cache::get('whatsapp.phone_link');
        $status = is_array($cached) && isset($cached['status']) ? strtolower((string) $cached['status']) : '';
        $at = is_array($cached) && ! empty($cached['at']) ? (string) $cached['at'] : null;
        if ($status === '') {
            $row = \App\WhatsApp\WhatsAppWebhookEvent::where('event_type', 'session.status')->orderByDesc('id')->first();
            if ($row) {
                $payload = json_decode((string) $row->payload, true);
                $data = is_array($payload) && isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : [];
                $status = isset($data['status']) ? strtolower((string) $data['status']) : '';
                $at = $row->received_at ? (string) $row->received_at : null;
            }
        }
        $disconnected = in_array($status, ['logged_out', 'expired', 'disconnected', 'conflict'], true);

        return [
            'disconnected' => $disconnected,
            'status' => $status,
            'at' => $at ? \Carbon\Carbon::parse($at)->format('j M Y, H:i') : null,
        ];
    }

    protected function chatThread($chatId)
    {
        $conversation = $this->inboxConversation($chatId);
        if (! $conversation) {
            return ['ok' => false];
        }
        $this->conversations->readOnWeb($conversation);
        $messages = WhatsAppMessage::visible()->where('conversation_id', $conversation->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(60)
            ->get()
            ->reverse()
            ->values();
        $first = $messages->first();
        $contact = $conversation->contact;
        $name = $contact ? $contact->displayName() : 'WhatsApp';
        $digits = preg_replace('/\D/', '', (string) ($contact ? $contact->normalized_phone : ''));
        $destination = app(\App\Services\WhatsApp\WhatsAppTranslator::class)->destinationFor($conversation->id);
        $staff = app(\App\Services\WhatsApp\WhatsAppTranslator::class)->staffLanguage();
        $defaultReply = app(\App\Services\WhatsApp\WhatsAppTranslator::class)->defaultReply();

        return [
            'ok' => true,
            'chat' => [
                'id' => (int) $conversation->id,
                'name' => $name ?: 'WhatsApp',
                'phone' => $contact ? (string) $contact->display_phone : '',
                'digits' => $digits,
                'tone' => (int) (sprintf('%u', crc32(strtolower($name ?: 'whatsapp'))) % 8),
                'mode' => (string) $conversation->mode,
                'translate' => $destination && ! empty($destination['enabled']),
                'destination' => $destination,
                'staff' => $staff,
                'default_reply' => $defaultReply,
                'reply' => route('whatsapp.chats.reply', $conversation->id),
                'translator' => route('whatsapp.chats.translator', $conversation->id),
                'language' => route('whatsapp.chats.language', $conversation->id),
                'ai' => route('whatsapp.conversation.enable_ai', $conversation->id),
                'human' => route('whatsapp.conversation.takeover', $conversation->id),
            ],
            'messages' => $messages->map(function ($message) {
                return $this->chatFeedMessage($message);
            })->values(),
            'has_earlier' => $first
                ? WhatsAppMessage::visible()->where('conversation_id', $conversation->id)->where('id', '<', $first->id)->exists()
                : false,
        ];
    }

    protected function chatsFeed(Request $request)
    {
        $chatId = (int) $request->get('chat');
        $after = (int) $request->get('after');
        $before = (int) $request->get('before');
        $payload = ['items' => [], 'messages' => [], 'ticks' => [], 'earlier' => [], 'has_earlier' => false, 'typing' => '', 'csrf' => csrf_token()];

        if ($chatId > 0 && $before > 0) {
            $older = WhatsAppMessage::visible()->where('conversation_id', $chatId)
                ->where('id', '<', $before)
                ->orderByDesc('id')
                ->limit(40)
                ->get()
                ->reverse()
                ->values();
            $payload['earlier'] = $older->map(function ($message) {
                return $this->chatFeedMessage($message);
            })->values();
            $first = $older->first();
            $payload['has_earlier'] = $first
                ? WhatsAppMessage::visible()->where('conversation_id', $chatId)->where('id', '<', $first->id)->exists()
                : false;
        } elseif ($chatId > 0) {
            $current = $this->inboxConversation($chatId);
            if ($current) {
                $this->conversations->touchStaffView($current);
                if ((int) $current->unread_count > 0) {
                    $this->conversations->readOnWeb($current);
                }
                $payload['typing'] = app(\App\Services\WhatsApp\WhatsAppTyping::class)->state(
                    optional($current->contact)->normalized_phone
                );
            }
            $fresh = WhatsAppMessage::visible()->where('conversation_id', $chatId)
                ->where('id', '>', $after)
                ->orderBy('id')
                ->limit(30)
                ->get();
            $translator = app(\App\Services\WhatsApp\WhatsAppTranslator::class);
            if ($translator->activeFor($chatId)) {
                $latestIn = null;
                foreach ($fresh as $row) {
                    if ($row->direction === WhatsAppMessage::DIR_IN) {
                        $latestIn = $row;
                    }
                }
                if ($latestIn && $translator->needsTranslation($latestIn)) {
                    $translator->decorate($latestIn, false);
                    $payload['translated'] = [$this->chatFeedMessage($latestIn)];
                }
            }
            $payload['messages'] = $fresh->map(function ($message) {
                return $this->chatFeedMessage($message);
            })->values();
            $ticks = WhatsAppMessage::where('conversation_id', $chatId)
                ->where('direction', WhatsAppMessage::DIR_OUT)
                ->orderByDesc('id')
                ->limit(20)
                ->get();
            $payload['ticks'] = $ticks->map(function ($message) {
                return ['id' => (int) $message->id, 'tick' => $message->ticks()];
            })->values();
        }

        $jid = trim((string) $request->get('group', ''));
        $afterGroup = (int) $request->get('after_group');
        if ($jid !== '' && Schema::hasTable('whatsapp_group_messages') && Schema::hasTable('whatsapp_groups')) {
            $record = \App\WhatsApp\WhatsAppGroup::where('group_jid', $jid)->first();
            if ($record) {
                $rows = \App\WhatsApp\WhatsAppGroupMessage::where('group_id', $record->id)
                    ->where('id', '>', $afterGroup)
                    ->orderBy('id')
                    ->limit(30)
                    ->get();
                $payload['group_messages'] = $rows->map(function ($message) {
                    return $this->groupFeedMessage($message);
                })->values();
            }
        }

        list($conversations, $stamp) = $this->inboxSnapshot();
        $payload['stamp'] = $stamp;
        if ((string) $request->get('stamp') === $stamp) {
            $payload['list'] = false;

            return $payload;
        }
        $payload['list'] = true;
        try {
            $this->conversations->preferPhoneNames($conversations, false);
        } catch (\Throwable $e) {
        }
        $payload['items'] = $conversations->map(function ($row) {
            $person = optional($row->contact)->displayName() ?: 'WhatsApp';
            $when = $row->last_activity_at ?: $row->updated_at;
            $preview = trim((string) $row->last_message);

            return [
                'id' => (int) $row->id,
                'name' => $person,
                'tone' => (int) (sprintf('%u', crc32(strtolower($person))) % 8),
                'phone' => (string) optional($row->contact)->display_phone,
                'preview' => $this->chatPreview($preview),
                'time' => $when ? $when->format($when->isToday() ? 'H:i' : 'M j') : '',
                'at' => $when ? $when->toIso8601String() : '',
                'unread' => (int) $row->unread_count,
                'mode' => (string) $row->mode,
            ];
        })->values();

        return $payload;
    }

    /**
     * The chat list is the linked WhatsApp line. A selected company must not
     * hide those people while the group directory still shows.
     */
    /**
     * Staff chats are the platform WhatsApp line. A selected company must not
     * hide the chat or refuse the send.
     */
    protected function onStaffLine(callable $callback)
    {
        $context = app(\App\Services\Cloud\CloudTenantContext::class);
        $previous = $context->tenant();
        $context->clear();
        try {
            return $context->withoutIsolation($callback);
        } finally {
            if ($previous) {
                $context->set($previous);
            }
        }
    }

    protected function inboxConversations()
    {
        list($rows) = $this->inboxSnapshot();

        return $rows;
    }

    protected function inboxSnapshot()
    {
        $load = function () {
            $rows = WhatsAppConversation::with('contact');
            $personal = $this->personalHub();
            if ($personal) {
                $rows->where('mai_session_id', $personal->id);
            } elseif (Schema::hasColumn('whatsapp_conversations', 'mai_session_id')) {
                $rows->whereNull('mai_session_id');
            }
            $rows = $rows->orderByDesc('last_activity_at')
                ->orderByDesc('id')
                ->limit(150)
                ->get();
            $stamp = (string) WhatsAppConversation::max('updated_at').'|'.(int) WhatsAppConversation::sum('unread_count');

            return [$rows, $stamp];
        };
        $loaded = $load();
        if ($loaded[0]->isNotEmpty() || ! config('cloud.isolate_queries')) {
            return $loaded;
        }

        return app(\App\Services\Cloud\CloudTenantContext::class)->withoutIsolation($load);
    }

    protected function inboxConversation($id)
    {
        $row = $this->staffConversation($id);
        if ($row || ! $id || ! config('cloud.isolate_queries')) {
            return $row;
        }

        return app(\App\Services\Cloud\CloudTenantContext::class)->withoutIsolation(function () use ($id) {
            return $this->staffConversation($id);
        });
    }

    /**
     * Customer session threads stay out of the business inbox.
     */
    protected function staffConversation($id)
    {
        $query = WhatsAppConversation::with('contact')->where('id', (int) $id);
        $personal = $this->personalHub();
        if ($personal) {
            $query->where('mai_session_id', $personal->id);
        } elseif (Schema::hasColumn('whatsapp_conversations', 'mai_session_id')) {
            $query->whereNull('mai_session_id');
        }

        return $query->first();
    }

    protected function chatPreview($preview)
    {
        return \App\WhatsApp\WhatsAppMessage::listPreview($preview);
    }

    protected function chatFeedMessage(WhatsAppMessage $message)
    {
        $message = app(\App\Services\WhatsApp\WhatsAppMediaLibrary::class)->hydrate($message);
        $when = $message->created_at;
        $media = $message->media();

        return [
            'id' => (int) $message->id,
            'out' => $message->direction === WhatsAppMessage::DIR_OUT,
            'text' => $message->hubText(),
            'aside' => $message->hubAside(),
            'time' => $when ? $when->format('H:i') : '',
            'at' => $when ? $when->toIso8601String() : '',
            'day' => $when ? $when->format('Y-m-d') : '',
            'day_label' => $this->chatDayLabel($when),
            'tick' => $message->ticks(),
            'type' => (string) $message->type,
            'view_once' => ! empty($media['view_once']),
            'media' => $message->chatMediaUrl(),
            'name' => $message->chatMediaName(),
            'needs_voice' => strtoupper((string) $message->type) === 'AUDIO'
                && $message->direction === WhatsAppMessage::DIR_IN
                && trim((string) (isset($message->translationMeta()['english']) ? $message->translationMeta()['english'] : '')) === '',
            'quote' => $message->quoteText(),
            'starred' => $message->isStarred(),
            'edited' => $message->wasEdited(),
            'deleted' => $message->wasDeleted(),
            'forwarded' => $message->isForwarded(),
            'can_edit' => $message->canEditOnWhatsApp(),
            'can_drop' => $message->canDeleteForEveryone(),
        ];
    }

    protected function groupFeedMessage($message)
    {
        $media = [];
        if ($message->media_json) {
            $decoded = json_decode((string) $message->media_json, true);
            $media = is_array($decoded) ? $decoded : [];
        }
        $when = $message->message_at ?: $message->created_at;
        $mediaUrl = isset($media['url']) ? (string) $media['url'] : '';
        if ($mediaUrl !== '' && strpos($mediaUrl, 'whatsapp-chat/') === 0) {
            $mediaUrl = asset('public/'.$mediaUrl);
        } elseif ($mediaUrl !== '' && ! preg_match('#^https?://#i', $mediaUrl)) {
            $mediaUrl = '';
        }

        return [
            'id' => (int) $message->id,
            'out' => ! empty($media['outgoing']),
            'who' => empty($media['outgoing']) ? ($message->participant_name ?: 'Member') : '',
            'text' => (string) $message->body,
            'time' => $when ? $when->format('H:i') : '',
            'at' => $when ? $when->toIso8601String() : '',
            'day' => $when ? $when->format('Y-m-d') : '',
            'day_label' => $this->chatDayLabel($when),
            'tick' => ! empty($media['outgoing']) ? 'sent' : '',
            'type' => isset($media['type']) ? (string) $media['type'] : 'TEXT',
            'media' => $mediaUrl,
            'name' => isset($media['file_name']) ? (string) $media['file_name'] : '',
        ];
    }

    protected function chatDayLabel($when)
    {
        if (! $when) {
            return '';
        }
        if ($when->isToday()) {
            return 'Today';
        }
        if ($when->isYesterday()) {
            return 'Yesterday';
        }

        return $when->format('M j, Y');
    }

    public function chatsReply(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.reply', 'whatsapp.manage'])) {
            return $deny;
        }
        $json = $request->ajax() || $request->wantsJson() || $request->filled('send_token');
        $conversation = $this->inboxConversation($id);
        if (! $conversation) {
            if ($json) {
                return response()->json(['ok' => false, 'error' => 'That chat is not open.'], 404);
            }
            abort(404);
        }
        $back = route('whatsapp.chats', ['chat' => $conversation->id]);
        $token = substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $request->input('send_token')), 0, 40);
        $tokenKey = $token !== '' ? 'wa.send.'.$conversation->id.'.'.$token : '';
        if ($tokenKey !== '') {
            if (! \Illuminate\Support\Facades\Cache::add($tokenKey, 'pending', now()->addMinutes(3))) {
                $held = \Illuminate\Support\Facades\Cache::get($tokenKey);
                $existing = is_numeric($held) ? WhatsAppMessage::find($held) : null;

                return response()->json([
                    'ok' => true,
                    'duplicate' => true,
                    'message' => $existing ? $this->chatFeedMessage($existing) : null,
                ]);
            }
        }
        $voice = $request->file('voice');
        if ($voice) {
            $allowedVoice = ['webm', 'ogg', 'opus', 'mp3', 'm4a', 'mp4', 'wav', 'aac'];
            $ext = strtolower((string) $voice->getClientOriginalExtension());
            if (! in_array($ext, $allowedVoice, true) || $voice->getSize() > 8 * 1024 * 1024) {
                if ($tokenKey !== '') {
                    \Illuminate\Support\Facades\Cache::forget($tokenKey);
                }
                if ($json) {
                    return response()->json(['ok' => false, 'error' => 'Record a voice note up to 8 MB.'], 422);
                }

                return redirect()->to($back)->with('not_permitted', 'Record a voice note up to 8 MB.');
            }
            @set_time_limit(90);
            $result = $this->onStaffLine(function () use ($conversation, $voice) {
                return $this->conversations->replyVoice(
                    $conversation,
                    $voice->getRealPath(),
                    $voice->getClientOriginalName(),
                    (string) $voice->getMimeType(),
                    Auth::id()
                );
            });
        } else {
        $file = $request->file('file');
        if ($file) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
            $ext = strtolower((string) $file->getClientOriginalExtension());
            if (! in_array($ext, $allowed, true) || $file->getSize() > 8 * 1024 * 1024) {
                if ($tokenKey !== '') {
                    \Illuminate\Support\Facades\Cache::forget($tokenKey);
                }
                if ($json) {
                    return response()->json(['ok' => false, 'error' => 'Send a photo or document up to 8 MB.'], 422);
                }

                return redirect()->to($back)->with('not_permitted', 'Send a photo or document up to 8 MB.');
            }
            $result = $this->onStaffLine(function () use ($conversation, $file, $request) {
                return $this->conversations->replyFile(
                    $conversation,
                    $file->getRealPath(),
                    $file->getClientOriginalName(),
                    (string) $file->getMimeType(),
                    $request->input('body'),
                    Auth::id()
                );
            });
        } else {
            $quote = null;
            $quoteId = (int) $request->input('quote_id');
            if ($quoteId > 0) {
                $quote = WhatsAppMessage::visible()->where('conversation_id', $conversation->id)->where('id', $quoteId)->first();
            }
            $result = $this->onStaffLine(function () use ($conversation, $request, $quote) {
                return $this->conversations->reply($conversation, $request->input('body'), Auth::id(), $quote);
            });
        }
        }
        if (empty($result['success'])) {
            if ($tokenKey !== '') {
                \Illuminate\Support\Facades\Cache::forget($tokenKey);
            }
            $failed = isset($result['message']) ? $result['message'] : null;
            if ($json) {
                return response()->json([
                    'ok' => false,
                    'error' => isset($result['error']) ? $result['error'] : 'Send failed.',
                    'message' => $failed ? $this->chatFeedMessage($failed) : null,
                ], 422);
            }

            return redirect()->to($back)->with('not_permitted', isset($result['error']) ? $result['error'] : 'Send failed.');
        }
        $saved = isset($result['message']) ? $result['message'] : null;
        if ($tokenKey !== '' && $saved) {
            \Illuminate\Support\Facades\Cache::put($tokenKey, (int) $saved->id, now()->addMinutes(3));
        }
        if ($json) {
            return response()->json([
                'ok' => true,
                'notice' => isset($result['notice']) ? $result['notice'] : '',
                'message' => $saved ? $this->chatFeedMessage($saved) : null,
            ]);
        }
        if (! empty($result['notice'])) {
            return redirect()->to($back)->with('message', $result['notice']);
        }

        return redirect()->to($back);
    }

    public function toggleTranslator($id)
    {
        if ($deny = $this->denyUnless(['whatsapp.reply', 'whatsapp.manage', 'whatsapp.conversations', 'whatsapp.view'])) {
            return $deny;
        }
        $translator = app(\App\Services\WhatsApp\WhatsAppTranslator::class);
        $turningOn = ! $translator->activeFor($id);
        $notice = $translator->setChat($id, $turningOn);
        if ($turningOn) {
            try {
                $translator->backfillChat($id, 20);
            } catch (\Throwable $e) {
            }
        }

        return redirect()->route('whatsapp.chats', ['chat' => $id])->with('message', $notice);
    }

    public function setTranslatorLanguage(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.reply', 'whatsapp.manage', 'whatsapp.conversations', 'whatsapp.view'])) {
            return $deny;
        }
        $translator = app(\App\Services\WhatsApp\WhatsAppTranslator::class);
        $name = $translator->setDestination($id, $request->input('language'));
        if ($name === false) {
            $notice = 'Replies in this chat stay in the language you type. Other chats are unchanged.';
        } elseif ($name === '' || $name === null) {
            $notice = 'This chat will detect their language. Other chats are unchanged.';
        } else {
            $notice = 'Everything you send in this chat goes out in '.$name.', until you change it. If they write in your language, you still see it that way.';
        }

        return redirect()->route('whatsapp.chats', ['chat' => $id])->with('message', $notice);
    }

    public function editChatMessage(Request $request, $id, $message)
    {
        return $this->messageAction($request, $id, $message, 'edit');
    }

    public function deleteChatMessage(Request $request, $id, $message)
    {
        return $this->messageAction($request, $id, $message, 'delete');
    }

    public function starChatMessage(Request $request, $id, $message)
    {
        return $this->messageAction($request, $id, $message, 'star');
    }

    public function forwardChatMessage(Request $request, $id, $message)
    {
        return $this->messageAction($request, $id, $message, 'forward');
    }

    protected function messageAction(Request $request, $id, $message, $action)
    {
        if ($deny = $this->denyUnless(['whatsapp.reply', 'whatsapp.manage'])) {
            return $deny;
        }
        $conversation = $this->inboxConversation($id);
        if (! $conversation) {
            return response()->json(['ok' => false, 'error' => 'That chat is not open.'], 404);
        }
        $row = WhatsAppMessage::where('conversation_id', $conversation->id)->where('id', (int) $message)->first();
        if (! $row) {
            return response()->json(['ok' => false, 'error' => 'That message is not in this chat.'], 404);
        }
        if ($action === 'edit') {
            $result = $this->conversations->editOutgoing($conversation, $row, $request->input('text'));
        } elseif ($action === 'delete') {
            $result = $this->conversations->deleteMessage($conversation, $row, $request->input('everyone') === '1');
        } elseif ($action === 'star') {
            $result = $this->conversations->starMessage($conversation, $row);
        } else {
            $target = $this->inboxConversation((int) $request->input('to'));
            if (! $target || (int) $target->id === (int) $conversation->id) {
                return response()->json(['ok' => false, 'error' => 'Choose a different chat to forward to.'], 422);
            }
            $result = $this->conversations->forwardTo($target, $row, Auth::id());
        }
        if (empty($result['success'])) {
            return response()->json([
                'ok' => false,
                'error' => isset($result['error']) ? $result['error'] : 'That did not work.',
            ], 422);
        }
        $saved = isset($result['message']) ? $result['message'] : null;
        $name = $action === 'forward' && isset($target) && $target->contact ? $target->contact->displayName() : '';

        return response()->json([
            'ok' => true,
            'removed' => ! empty($result['removed']),
            'notice' => $action === 'forward' ? ('Forwarded to '.($name ?: 'that chat').'.') : '',
            'message' => $saved ? $this->chatFeedMessage($saved->fresh() ?: $saved) : null,
        ]);
    }

    public function saveLanguageSettings(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.reply', 'whatsapp.manage', 'whatsapp.conversations', 'whatsapp.view'])) {
            return $deny;
        }
        $translator = app(\App\Services\WhatsApp\WhatsAppTranslator::class);
        $staff = $translator->setStaffLanguage($request->input('staff_language', 'en'));
        $reply = $translator->setDefaultReply($request->input('default_reply', ''));
        $translator->setEnterSends($request->input('enter_sends') === '1');
        $notice = 'You write in '.$staff.'.';
        $notice .= $request->input('enter_sends') === '1'
            ? ' Enter sends the message.'
            : ' Enter starts a new line.';
        if ($reply) {
            $notice .= ' Chats without their own reply language are sent in '.$reply.'.';
        } else {
            $notice .= ' Each chat uses the reply language you pick there.';
        }
        $chat = (int) $request->input('chat');
        $query = $chat > 0 ? ['chat' => $chat] : [];

        return redirect()->route('whatsapp.chats', $query)->with('message', $notice);
    }

    public function showTyping(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.reply', 'whatsapp.manage', 'whatsapp.conversations', 'whatsapp.view'])) {
            return $deny;
        }
        $state = (string) $request->input('state');
        if (! in_array($state, ['composing', 'recording'], true)) {
            return response()->json(['ok' => true]);
        }
        if (! \Illuminate\Support\Facades\Cache::add('wa.out.typing.'.$id, 1, now()->addSeconds(4))) {
            return response()->json(['ok' => true]);
        }
        $conversation = WhatsAppConversation::with('contact')->find($id);
        $phone = $conversation && $conversation->contact ? $conversation->contact->normalized_phone : '';
        if ($phone !== '' && ! $conversation->isWebsite()) {
            app(\App\Services\BeyondWasenderService::class)->sendPresence($phone, $state);
        }

        return response()->json(['ok' => true]);
    }

    public function hearVoice(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.conversations', 'whatsapp.view', 'whatsapp.manage', 'whatsapp.reply'])) {
            return $deny;
        }
        @set_time_limit(90);
        $message = WhatsAppMessage::where('conversation_id', $id)->where('id', (int) $request->input('message'))->first();
        if (! $message || strtoupper((string) $message->type) !== 'AUDIO') {
            return response()->json(['ok' => false, 'error' => 'That message is not a voice note.'], 422);
        }
        $translator = app(\App\Services\WhatsApp\WhatsAppTranslator::class);
        $message = $translator->hearVoiceNote($message);
        if ($translator->voiceTranscript($message) === '') {
            return response()->json(['ok' => false, 'error' => 'Could not hear that voice note.'], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => $this->chatFeedMessage($message),
        ]);
    }

    public function askChat(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.conversations', 'whatsapp.view', 'whatsapp.manage', 'whatsapp.reply'])) {
            return $deny;
        }
        @set_time_limit(90);
        $instruction = trim((string) $request->input('instruction'));
        $hasFile = $request->hasFile('file');
        if ($instruction === '' && ! $hasFile) {
            return response()->json(['ok' => false, 'error' => 'Write what you want the assistant to do.'], 422);
        }
        if ($instruction === '') {
            $instruction = 'Read the attached file and say what it means for this chat.';
        }
        if (mb_strlen($instruction) > 2000) {
            $instruction = mb_substr($instruction, 0, 2000);
        }
        $material = $this->attachedAskMaterial($request);
        if (! empty($material['error'])) {
            return response()->json(['ok' => false, 'error' => $material['error']], 422);
        }
        $messageId = (int) $request->input('message');
        if ($messageId > 0) {
            $row = WhatsAppMessage::where('conversation_id', $id)->where('id', $messageId)->first();
            if (! $row) {
                return response()->json(['ok' => false, 'error' => 'That message is not in this chat.'], 404);
            }

            return $this->answerAboutMessage($row, $instruction, false, $material);
        }
        $rows = WhatsAppMessage::visible()->where('conversation_id', $id)->orderByDesc('id')->limit(120)->get()->reverse()->values();
        $translator = app(\App\Services\WhatsApp\WhatsAppTranslator::class);
        $latestAudio = null;
        foreach ($rows as $row) {
            if (strtoupper((string) $row->type) === 'AUDIO' && $translator->voiceTranscript($row) === '') {
                $latestAudio = $row;
            }
        }
        if ($latestAudio) {
            $translator->hearVoiceNote($latestAudio);
            $rows = WhatsAppMessage::visible()->where('conversation_id', $id)->orderByDesc('id')->limit(120)->get()->reverse()->values();
        }
        $lines = [];
        foreach ($rows as $row) {
            $line = trim($row->hubText());
            $aside = trim($row->hubAside());
            if ($aside !== '') {
                $line = trim($line."\n".$aside);
            }
            $type = strtoupper((string) $row->type);
            if ($line === '' || $translator->isVoicePlaceholder($line)) {
                if ($type === 'AUDIO') {
                    $line = 'Voice note';
                } elseif ($type === 'IMAGE' || $type === 'STICKER') {
                    $line = 'Photo';
                } elseif ($type === 'VIDEO') {
                    $line = 'Video';
                } elseif ($type === 'DOCUMENT') {
                    $line = 'Document';
                } else {
                    $line = '';
                }
            }
            if ($line === '') {
                continue;
            }
            $who = $row->direction === WhatsAppMessage::DIR_OUT ? 'You' : 'Them';
            $lines[] = $who.': '.$line;
        }
        $text = trim(implode("\n", $lines));
        if (mb_strlen($text) > 12000) {
            $text = mb_substr($text, -12000);
        }
        $material['images'] = $this->chatAskImages($rows);
        if ($text === '' && $material['text'] === '' && empty($material['image']) && empty($material['images'])) {
            return response()->json(['ok' => false, 'error' => 'This chat has no text to work from yet.'], 422);
        }
        if ($text === '') {
            $text = '(No text in this chat yet.)';
        }

        return $this->answerAbout($text, $instruction, 'You answer only about the current WhatsApp chat. You cannot see the rest of the company system, other chats, accounts, or records. Use the messages from this chat, the photos from this chat, and a file only when one is attached to this question. Read amounts, names, and dates that are visible in those photos. If the answer is not in this chat or that file, say it is not in this chat. Do not invent facts, names, prices, or medical details. When the request asks for a reply, write only the message that can be sent. When it asks for a summary or a list, write that clearly.', true, $material);
    }

    public function askChatMessage(Request $request, $id, $message)
    {
        if ($deny = $this->denyUnless(['whatsapp.conversations', 'whatsapp.view', 'whatsapp.manage', 'whatsapp.reply'])) {
            return $deny;
        }
        @set_time_limit(90);
        $row = WhatsAppMessage::where('conversation_id', $id)->where('id', (int) $message)->first();
        if (! $row) {
            return response()->json(['ok' => false, 'error' => 'That message is not in this chat.'], 404);
        }
        $instruction = trim((string) $request->input('instruction'));
        if ($instruction === '') {
            return response()->json(['ok' => false, 'error' => 'Write what you want to ask about this message.'], 422);
        }
        if (mb_strlen($instruction) > 2000) {
            $instruction = mb_substr($instruction, 0, 2000);
        }

        return $this->answerAboutMessage($row, $instruction, false, ['text' => '', 'image' => null, 'name' => '']);
    }

    protected function answerAboutMessage(WhatsAppMessage $row, $instruction, $keepChatLabel, array $material = [])
    {
        $translator = app(\App\Services\WhatsApp\WhatsAppTranslator::class);
        if (strtoupper((string) $row->type) === 'AUDIO') {
            $row = $translator->hearVoiceNote($row);
        }
        $text = trim($row->hubText());
        $aside = trim($row->hubAside());
        if ($aside !== '') {
            $text = trim($text."\n".$aside);
        }
        if ($text === '' || $translator->isVoicePlaceholder($text)) {
            $text = '';
        }
        $fileText = isset($material['text']) ? (string) $material['text'] : '';
        if ($text === '' && $fileText === '' && empty($material['image'])) {
            return response()->json(['ok' => false, 'error' => 'That message has no text to work from yet.'], 422);
        }
        if ($text === '') {
            $text = '(This message has no text yet.)';
        }

        return $this->answerAbout($text, $instruction, 'You answer only about this one message from the current WhatsApp chat. You cannot see the rest of the company system, other chats, accounts, or records. Use this message, and a file only when one is attached to this question. If the answer is not in this message or that file, say so. Do not invent facts, names, prices, or medical details. When the request asks for a reply, write only the message that can be sent. Otherwise answer in plain language.', $keepChatLabel, $material);
    }

    protected function answerAbout($text, $instruction, $system, $hideSource, array $material = [])
    {
        $provider = app(\App\Contracts\Ai\AiProviderInterface::class);
        if (! $provider->isConfigured()) {
            return response()->json(['ok' => false, 'error' => 'The assistant is not available.'], 422);
        }
        $fileText = isset($material['text']) ? trim((string) $material['text']) : '';
        $fileName = isset($material['name']) ? trim((string) $material['name']) : '';
        $body = "Current chat:\n".$text."\n\nRequest:\n".$instruction;
        if ($fileText !== '') {
            $body .= "\n\nAttached file".($fileName !== '' ? ' ('.$fileName.')' : '').":\n".$fileText;
        }
        $images = [];
        if (! empty($material['image']) && is_string($material['image'])) {
            $images[] = $material['image'];
        }
        if (! empty($material['images']) && is_array($material['images'])) {
            foreach ($material['images'] as $url) {
                if (is_string($url) && $url !== '') {
                    $images[] = $url;
                }
            }
        }
        $content = $body;
        if ($images) {
            $content = [
                ['type' => 'text', 'text' => $body."\n\nPhotos from this chat are attached in order. Read the text and amounts in those photos."],
            ];
            foreach ($images as $url) {
                $content[] = ['type' => 'image_url', 'image_url' => ['url' => $url]];
            }
        }
        $result = $provider->complete([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $content],
        ], [
            'temperature' => 0.4,
            'max_tokens' => 700,
        ]);
        if (empty($result['ok']) || trim((string) (isset($result['content']) ? $result['content'] : '')) === '') {
            $error = isset($result['error']) ? (string) $result['error'] : '';
            if ($error === '' || stripos($error, 'sk-') !== false || strlen($error) > 180) {
                $error = 'The assistant could not answer that.';
            }

            return response()->json(['ok' => false, 'error' => $error], 422);
        }

        return response()->json([
            'ok' => true,
            'text' => $hideSource ? '' : $text,
            'answer' => trim((string) $result['content']),
        ]);
    }

    protected function chatAskImages($rows)
    {
        $images = [];
        foreach ($rows as $row) {
            $type = strtoupper((string) $row->type);
            if ($type !== 'IMAGE' && $type !== 'STICKER') {
                continue;
            }
            $data = $this->askImageData($row);
            if ($data !== '') {
                $images[] = $data;
            }
        }
        if (count($images) > 4) {
            $images = array_slice($images, -4);
        }

        return $images;
    }

    protected function askImageData(WhatsAppMessage $row)
    {
        $media = $row->media();
        $stored = isset($media['stored']) ? str_replace('\\', '/', trim((string) $media['stored'])) : '';
        if ($stored === '' || strpos($stored, '..') !== false || strpos($stored, 'whatsapp-media/') !== 0) {
            return '';
        }
        $path = storage_path('app/'.$stored);
        if (! is_file($path)) {
            return '';
        }
        $raw = file_get_contents($path);
        if (! is_string($raw) || strlen($raw) < 32) {
            return '';
        }
        if (function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring($raw);
            if ($img) {
                $width = imagesx($img);
                $height = imagesy($img);
                $max = 1280;
                if ($width > $max && $width > 0) {
                    $nextHeight = (int) max(1, round($height * ($max / $width)));
                    $copy = imagecreatetruecolor($max, $nextHeight);
                    imagecopyresampled($copy, $img, 0, 0, 0, 0, $max, $nextHeight, $width, $height);
                    imagedestroy($img);
                    $img = $copy;
                }
                ob_start();
                imagejpeg($img, null, 72);
                $jpeg = ob_get_clean();
                imagedestroy($img);
                if (is_string($jpeg) && $jpeg !== '') {
                    return 'data:image/jpeg;base64,'.base64_encode($jpeg);
                }
            }
        }
        if (strlen($raw) > 1500000) {
            return '';
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : ($ext === 'gif' ? 'image/gif' : 'image/jpeg'));

        return 'data:'.$mime.';base64,'.base64_encode($raw);
    }

    protected function attachedAskMaterial(Request $request)
    {
        $empty = ['text' => '', 'image' => null, 'name' => '', 'error' => ''];
        $file = $request->file('file');
        if (! $file) {
            return $empty;
        }
        if (! $file->isValid()) {
            $empty['error'] = 'That file could not be read.';

            return $empty;
        }
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $name = trim((string) $file->getClientOriginalName());
        if (strlen($name) > 120) {
            $name = substr($name, 0, 120);
        }
        $images = ['png', 'jpg', 'jpeg', 'webp', 'gif'];
        $docs = ['txt', 'csv', 'md', 'json', 'pdf', 'docx'];
        if (! in_array($ext, $images, true) && ! in_array($ext, $docs, true)) {
            $empty['error'] = 'Add a text file, PDF, Word document, or a photo.';

            return $empty;
        }
        $max = in_array($ext, $images, true) ? 1500000 : 4000000;
        if ((int) $file->getSize() > $max) {
            $empty['error'] = 'That file is too large.';

            return $empty;
        }
        $path = $file->getRealPath();
        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            $empty['error'] = 'That file could not be read.';

            return $empty;
        }
        if (in_array($ext, $images, true)) {
            $raw = file_get_contents($path);
            if (! is_string($raw) || $raw === '') {
                $empty['error'] = 'That photo could not be read.';

                return $empty;
            }
            $mime = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : ($ext === 'gif' ? 'image/gif' : 'image/jpeg'));

            return ['text' => '', 'image' => 'data:'.$mime.';base64,'.base64_encode($raw), 'name' => $name, 'error' => ''];
        }
        $text = trim($this->readAskFileText($path, $ext));
        if ($text === '') {
            $empty['error'] = 'That file has no readable text. Try a photo of the page.';

            return $empty;
        }
        if (mb_strlen($text) > 8000) {
            $text = mb_substr($text, 0, 8000);
        }

        return ['text' => $text, 'image' => null, 'name' => $name, 'error' => ''];
    }

    protected function readAskFileText($path, $ext)
    {
        if (in_array($ext, ['txt', 'csv', 'md', 'json'], true)) {
            return $this->plainAskText(file_get_contents($path));
        }
        if ($ext === 'docx') {
            if (! class_exists('ZipArchive')) {
                return '';
            }
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) {
                return '';
            }
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            if (! is_string($xml) || $xml === '') {
                return '';
            }
            $xml = str_replace(['</w:p>', '</w:tr>'], "\n", $xml);
            $xml = preg_replace('/<w:tab[^>]*\/>/', "\t", $xml);

            return $this->plainAskText(strip_tags($xml));
        }
        if ($ext === 'pdf') {
            return $this->plainAskText($this->pdfAskText($path));
        }

        return '';
    }

    protected function plainAskText($raw)
    {
        if (! is_string($raw) || $raw === '') {
            return '';
        }
        if (function_exists('mb_convert_encoding')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }
        $clean = preg_replace('/[^\P{C}\n\t]+/u', ' ', $raw);

        return trim(is_string($clean) ? $clean : $raw);
    }

    protected function pdfAskText($path)
    {
        $raw = file_get_contents($path);
        if (! is_string($raw) || $raw === '') {
            return '';
        }
        $parts = [];
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $raw, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = @gzuncompress($stream);
                if ($decoded === false) {
                    $decoded = @gzinflate($stream);
                }
                $chunk = is_string($decoded) ? $decoded : $stream;
                $parts[] = $this->pdfOperatorsToText($chunk);
            }
        }
        $joined = trim(implode("\n", $parts));
        if ($joined !== '') {
            return $joined;
        }

        return $this->pdfOperatorsToText($raw);
    }

    protected function pdfOperatorsToText($chunk)
    {
        $out = [];
        if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)\s*Tj/s', $chunk, $found)) {
            foreach ($found[0] as $hit) {
                if (preg_match('/\((.*)\)/s', $hit, $inner)) {
                    $out[] = $this->pdfUnescape($inner[1]);
                }
            }
        }
        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $chunk, $arrays)) {
            foreach ($arrays[1] as $array) {
                if (! preg_match_all('/\((?:\\\\.|[^\\\\)])*\)/s', $array, $bits)) {
                    continue;
                }
                $line = '';
                foreach ($bits[0] as $bit) {
                    $line .= $this->pdfUnescape(substr($bit, 1, -1));
                }
                $out[] = $line;
            }
        }

        return trim(implode("\n", $out));
    }

    protected function pdfUnescape($text)
    {
        $text = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)', '\\\\'], ["\n", '', "\t", '(', ')', '\\'], $text);

        return preg_replace_callback('/\\\\([0-7]{1,3})/', function ($match) {
            return chr(octdec($match[1]));
        }, $text);
    }

    public function sendLetter(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.reply', 'whatsapp.manage'])) {
            return $deny;
        }
        $subject = trim((string) $request->input('subject'));
        $body = trim((string) $request->input('body'));
        $letter = $subject;
        if ($body !== '') {
            $letter = $letter === '' ? $body : ($subject."\n\n".$body);
        }
        $file = $request->file('file');
        $back = route('whatsapp.chats', ['pane' => 'announcements']);
        if ($file) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
            $ext = strtolower((string) $file->getClientOriginalExtension());
            if (! in_array($ext, $allowed, true) || $file->getSize() > 8 * 1024 * 1024) {
                return redirect()->to($back)->with('not_permitted', 'Attach a photo or document up to 8 MB.');
            }
        }
        if ($letter === '' && ! $file) {
            return redirect()->to($back)->with('not_permitted', 'Write the letter, or attach the file.');
        }
        $audience = (string) $request->input('audience');
        if ($audience === 'group') {
            $jid = trim((string) $request->input('group'));
            if (substr($jid, -5) !== '@g.us') {
                return redirect()->to($back)->with('not_permitted', 'Choose a WhatsApp group.');
            }
            if ($this->personalHub()) {
                if ($file) {
                    return redirect()->to($back)->with('not_permitted', 'Send the letter as text. Photos go out from the phone.');
                }
                $result = $this->personalText($jid, $letter);
                if (empty($result['success'])) {
                    return redirect()->to($back)->with('not_permitted', isset($result['error']) ? $result['error'] : 'The letter was not sent.');
                }

                return redirect()->to($back)->with('message', 'The letter was sent to the group.');
            }
            if ($file) {
                $result = $this->provider->sendGroupDocument(
                    $jid,
                    $file->getRealPath(),
                    $file->getClientOriginalName(),
                    $letter !== '' ? $letter : $file->getClientOriginalName()
                );
            } else {
                $result = $this->provider->sendGroupText($jid, $letter);
            }
            if (empty($result['success'])) {
                return redirect()->to($back)->with('not_permitted', isset($result['error']) ? $result['error'] : 'The letter was not sent.');
            }

            return redirect()->to($back)->with('message', 'The letter was sent to the group.');
        }

        $conversation = WhatsAppConversation::with('contact')->find((int) $request->input('person'));
        if (! $conversation) {
            return redirect()->to($back)->with('not_permitted', 'Choose a person.');
        }
        if ($file) {
            $result = $this->conversations->replyFile(
                $conversation,
                $file->getRealPath(),
                $file->getClientOriginalName(),
                (string) $file->getMimeType(),
                $letter,
                Auth::id()
            );
        } else {
            $result = $this->conversations->reply($conversation, $letter, Auth::id());
        }
        if (empty($result['success'])) {
            return redirect()->to($back)->with('not_permitted', isset($result['error']) ? $result['error'] : 'The letter was not sent.');
        }

        return redirect()->route('whatsapp.chats', ['chat' => $conversation->id])->with('message', 'The letter was sent.');
    }

    public function chatMedia($id)
    {
        if ($deny = $this->denyUnless(['whatsapp.conversations', 'whatsapp.view', 'whatsapp.manage'])) {
            return $deny;
        }
        $message = WhatsAppMessage::findOrFail($id);
        if ($personal = $this->personalHub()) {
            $owned = WhatsAppConversation::where('id', $message->conversation_id)->where('mai_session_id', $personal->id)->exists();
            if (! $owned) {
                abort(404);
            }
        }
        $file = app(\App\Services\WhatsApp\WhatsAppMediaLibrary::class)->file($message);
        if (! $file || ! is_file($file['path'])) {
            abort(404);
        }

        return response()->file($file['path'], [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'inline; filename="'.$file['name'].'"',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function chatsGroupReply(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.reply', 'whatsapp.manage'])) {
            return $deny;
        }
        $jid = trim((string) $request->input('jid'));
        $body = trim((string) $request->input('body'));
        $back = route('whatsapp.chats', ['group' => $jid]);
        if ($this->personalHub()) {
            $json = $request->ajax() || $request->wantsJson() || $request->filled('send_token');
            if ($request->file('file') || $request->file('voice')) {
                $error = 'Send the text. Photos and voice notes go out from the phone.';
                if ($json) {
                    return response()->json(['ok' => false, 'error' => $error], 422);
                }

                return redirect()->to($back)->with('not_permitted', $error);
            }
            if (substr($jid, -5) !== '@g.us' || $body === '') {
                if ($json) {
                    return response()->json(['ok' => false, 'error' => 'Type a message for the group.'], 422);
                }

                return redirect()->to($back)->with('not_permitted', 'Type a message for the group.');
            }
            $result = $this->personalText($jid, $body);
            if (empty($result['success'])) {
                $error = isset($result['error']) ? $result['error'] : 'Send failed.';
                if ($json) {
                    return response()->json(['ok' => false, 'error' => $error], 422);
                }

                return redirect()->to($back)->with('not_permitted', $error);
            }
            if ($json) {
                return response()->json(['ok' => true]);
            }

            return redirect()->to($back)->with('message', 'Sent from your WhatsApp.');
        }
        $json = $request->ajax() || $request->wantsJson() || $request->filled('send_token');
        $voice = $request->file('voice');
        if ($voice && substr($jid, -5) === '@g.us') {
            @set_time_limit(90);
            $heard = app(\App\Services\WhatsApp\WhatsAppVoiceTranscriber::class)->transcribePath(
                $voice->getRealPath(),
                (string) $voice->getMimeType()
            );
            if ($heard === '') {
                if ($json) {
                    return response()->json(['ok' => false, 'error' => 'Could not hear that voice note.'], 422);
                }

                return redirect()->to($back)->with('not_permitted', 'Could not hear that voice note.');
            }
            $body = $body === '' ? $heard : ($body."\n".$heard);
            $playable = $this->conversations->voiceFileForWhatsApp($voice->getRealPath());
            if ($playable) {
                $this->provider->sendGroupAudio($jid, $playable);
            }
        }
        $file = $request->file('file');
        $sentFile = false;
        if ($file && substr($jid, -5) === '@g.us') {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
            $ext = strtolower((string) $file->getClientOriginalExtension());
            if (! in_array($ext, $allowed, true) || $file->getSize() > 8 * 1024 * 1024) {
                if ($json) {
                    return response()->json(['ok' => false, 'error' => 'Send a photo or document up to 8 MB.'], 422);
                }

                return redirect()->to($back)->with('not_permitted', 'Send a photo or document up to 8 MB.');
            }
            $caption = $body !== '' ? $body : (string) $file->getClientOriginalName();
            $result = $this->provider->sendGroupDocument($jid, $file->getRealPath(), $file->getClientOriginalName(), $caption);
            if (empty($result['success'])) {
                if ($json) {
                    return response()->json(['ok' => false, 'error' => isset($result['error']) ? $result['error'] : 'Send failed.'], 422);
                }

                return redirect()->to($back)->with('not_permitted', isset($result['error']) ? $result['error'] : 'Send failed.');
            }
            $body = $caption;
            $sentFile = true;
        }
        if (! $sentFile && (substr($jid, -5) !== '@g.us' || $body === '')) {
            if ($json) {
                return response()->json(['ok' => false, 'error' => 'Type a message for the group.'], 422);
            }

            return redirect()->to($back)->with('not_permitted', 'Type a message for the group.');
        }
        if (! $sentFile) {
        $result = $this->provider->sendGroupText($jid, $body);
        if (empty($result['success'])) {
            if ($json) {
                return response()->json(['ok' => false, 'error' => isset($result['error']) ? $result['error'] : 'Send failed.'], 422);
            }

            return redirect()->to($back)->with('not_permitted', isset($result['error']) ? $result['error'] : 'Send failed.');
        }
        }
        if (Schema::hasTable('whatsapp_groups') && Schema::hasTable('whatsapp_group_messages')) {
            $record = \App\WhatsApp\WhatsAppGroup::firstOrCreate(
                ['group_jid' => $jid],
                ['name' => 'WhatsApp group', 'enabled' => false, 'mode' => \App\WhatsApp\WhatsAppGroup::OFF]
            );
            $msgId = isset($result['msg_id']) ? (string) $result['msg_id'] : ('staff:'.uniqid('', true));
            if (! \App\WhatsApp\WhatsAppGroupMessage::where('provider_message_id', $msgId)->exists()) {
                \App\WhatsApp\WhatsAppGroupMessage::create([
                    'group_id' => $record->id,
                    'provider_message_id' => $msgId,
                    'participant_name' => Auth::user() ? Auth::user()->name : 'Beyond Enterprise',
                    'body' => $body,
                    'media_json' => json_encode(['outgoing' => true]),
                    'message_at' => now(),
                ]);
            }
        }

        $savedId = isset($msgId) ? $msgId : ('staff:'.uniqid('', true));
        if ($json) {
            return response()->json([
                'ok' => true,
                'message' => [
                    'id' => $savedId,
                    'out' => true,
                    'text' => $body,
                    'time' => now()->format('H:i'),
                    'at' => now()->toIso8601String(),
                    'tick' => 'sent',
                    'type' => 'TEXT',
                    'media' => '',
                    'name' => '',
                    'aside' => '',
                ],
            ]);
        }

        return redirect()->to($back);
    }

    public function people(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.conversations', 'whatsapp.view', 'whatsapp.manage'])) {
            return $deny;
        }
        $q = trim((string) $request->get('q', ''));
        $contacts = $this->peopleQuery($q)->paginate(30);
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

    public function deletePeople(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.manage', 'whatsapp.conversations'])) {
            return $deny;
        }
        $q = trim((string) $request->input('q', ''));
        if ($request->boolean('all')) {
            $ids = $this->peopleQuery($q)->pluck('id')->all();
        } else {
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('ids', [])))));
        }
        $removed = 0;
        foreach (array_chunk($ids, 40) as $chunk) {
            $contacts = WhatsAppContact::whereIn('id', $chunk)->get();
            foreach ($contacts as $contact) {
                $this->deleteKnownContact($contact);
                $removed++;
            }
        }

        return redirect()->route('whatsapp.people', array_filter(['q' => $q]))
            ->with('message', $removed === 1 ? '1 contact deleted.' : $removed.' contacts deleted.');
    }

    protected function peopleQuery($q)
    {
        $query = WhatsAppContact::query()->orderByDesc('id');
        if ($session = $this->personalHub()) {
            $ids = WhatsAppConversation::where('mai_session_id', $session->id)->pluck('contact_id')->all();
            $query->whereIn('id', $ids ?: [0]);
        }
        $q = trim((string) $q);
        if ($q === '') {
            return $query;
        }
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

        return $query;
    }

    protected function deleteKnownContact(WhatsAppContact $contact)
    {
        if ($session = $this->personalHub()) {
            $conversations = WhatsAppConversation::where('contact_id', $contact->id)->where('mai_session_id', $session->id)->get();
            foreach ($conversations as $conversation) {
                $this->conversations->destroy($conversation);
            }

            return;
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($contact) {
            $conversations = WhatsAppConversation::where('contact_id', $contact->id)->get();
            foreach ($conversations as $conversation) {
                $this->conversations->destroy($conversation);
            }
            $id = (int) $contact->id;
            foreach ([
                'whatsapp_contact_links' => 'contact_id',
                'whatsapp_messages' => 'contact_id',
                'whatsapp_verification_challenges' => 'whatsapp_contact_id',
                'whatsapp_verification_sessions' => 'whatsapp_contact_id',
            ] as $table => $column) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    \Illuminate\Support\Facades\DB::table($table)->where($column, $id)->delete();
                }
            }
            if (Schema::hasTable('whatsapp_document_requests') && Schema::hasColumn('whatsapp_document_requests', 'whatsapp_contact_id')) {
                \Illuminate\Support\Facades\DB::table('whatsapp_document_requests')->where('whatsapp_contact_id', $id)->update(['whatsapp_contact_id' => null]);
            }
            foreach (['leads', 'whatsapp_calls', 'whatsapp_call_requests', 'whatsapp_rental_requests', 'appointments'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'contact_id')) {
                    \Illuminate\Support\Facades\DB::table($table)->where('contact_id', $id)->update(['contact_id' => null]);
                }
            }
            $contact->delete();
        });
    }

    public function reply(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.reply', 'whatsapp.manage'])) {
            return $deny;
        }
        $conversation = $this->inboxConversation($id);
        if (! $conversation) {
            abort(404);
        }
        $result = $this->conversations->reply($conversation, $request->input('body'), Auth::id());
        if (empty($result['success'])) {
            return redirect()->route('whatsapp.conversation', $conversation->id)
                ->with('not_permitted', $result['error'] ?? 'Send failed.');
        }
        if (! empty($result['notice'])) {
            $notice = $result['notice'];
        } elseif (isset($result['mode']) && $result['mode'] === WhatsAppConversation::MODE_HUMAN) {
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

    public function twilioLog(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.view', 'whatsapp.settings', 'whatsapp.manage'])) {
            return $deny;
        }
        $service = app(\App\Services\TwilioWhatsAppService::class);
        $inbox = $service->whatsAppInbox();
        $chat = preg_replace('/\D/', '', (string) $request->query('chat', ''));
        $open = null;
        $tail = strlen($chat) >= 9 ? substr($chat, -9) : $chat;
        foreach ($inbox['chats'] as $row) {
            if ($row['digits'] === $chat || ($tail !== '' && substr($row['digits'], -9) === $tail)) {
                $open = $row;
                break;
            }
        }
        $canReply = $this->canAny(['whatsapp.reply', 'whatsapp.manage']);
        $justSent = session('twilio_just_sent');

        return view('whatsapp_hub.twilio_log', compact('inbox', 'open', 'canReply', 'justSent'));
    }

    public function twilioReply(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.reply', 'whatsapp.manage'])) {
            return $deny;
        }
        $chat = preg_replace('/\D/', '', (string) $request->input('chat', $request->input('to')));
        $back = redirect()->route('whatsapp.twilio_log', $chat !== '' ? ['chat' => $chat] : []);

        $body = trim((string) $request->input('body', ''));
        if ($body === '') {
            return $back->with('not_permitted', 'Write a reply before sending.');
        }
        if (mb_strlen($body) > 1500) {
            return $back->with('not_permitted', 'Keep the reply under 1,500 characters.');
        }

        try {
            $to = \App\Support\WhatsAppPhone::normalize($request->input('to'));
        } catch (\InvalidArgumentException $e) {
            return $back->with('not_permitted', 'That number cannot be used for a reply.');
        }

        $service = app(\App\Services\TwilioWhatsAppService::class);
        $own = preg_replace('/\D/', '', $service->displayNumber());
        if ($to === '' || $to === $own) {
            return $back->with('not_permitted', 'That number cannot be used for a reply.');
        }

        $result = $service->sendText($to, $body);
        $service->forgetWhatsAppInbox();
        if (empty($result['success'])) {
            return $back->with('not_permitted', $this->publicTwilioError(isset($result['error']) ? $result['error'] : ''));
        }

        return $back->with('twilio_just_sent', [
            'digits' => $to,
            'body' => $body,
            'time' => date('H:i'),
            'day' => date('Y-m-d'),
        ]);
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
        $escalationNumber = app(\App\Services\Assistant\AssistantHandoverService::class)->escalationNumber();
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
            'aiFirst', 'manualTakeover', 'collectName', 'greetByName', 'handoverUserId', 'escalationNumber', 'historyLimit',
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
            $escalation = preg_replace('/[^\d+]/', '', (string) $request->input('ai_escalation_whatsapp_number', ''));
            WhatsAppSetting::putValue('AI_ESCALATION_WHATSAPP_NUMBER', $escalation);
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
            WhatsAppSetting::putValue('website_ai_enabled', '1');
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
        $linkedPhone = $ownNumber ? '' : app(\App\Services\BeyondWasenderService::class)->sessionPhone();
        if ($ownNumber && $listError === 'WhatsApp is not connected for this company.') {
            $listError = null;
        }
        if (! empty($summary['success']) && empty($summary['local'])) {
            app(\App\Services\WhatsApp\GroupContactExportService::class)->scheduleResolve();
        }

        return view('whatsapp_hub.groups', compact('groups', 'listError', 'ownNumber', 'linkedPhone'));
    }

    public function importGroup(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.owner', 'whatsapp.manage'])) {
            return $deny;
        }
        @set_time_limit(120);
        $result = app(\App\Services\WhatsApp\GroupContactExportService::class)->importInvite($request->input('invite', ''));
        if (empty($result['success'])) {
            return back()->with('not_permitted', isset($result['error']) ? $result['error'] : 'Could not add that group.');
        }
        $name = trim((string) $result['name']);
        $total = (int) $result['total'];
        $message = $name.' is now in the group list.';
        if ($total > 0) {
            $message .= ' '.$total.' '.($total === 1 ? 'contact is' : 'contacts are').' saved, so a personalized message can go to each member.';
        }

        return redirect()->route('whatsapp.groups.show', ['jid' => $result['jid']])->with('message', $message);
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

    /**
     * The home-screen app drops a short session cookie when it is closed.
     * A remember-me cookie keeps the same login the next time it opens.
     */
    protected function keepSignedIn()
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }
        $guard = Auth::guard('web');
        if (request()->cookies->has($guard->getRecallerName()) || session('hub_remember_set')) {
            return;
        }
        // login() migrates the session and rotates the CSRF token, so every
        // other open chat tab then fails its next send with 419.
        if (empty($user->getRememberToken())) {
            $user->setRememberToken(\Illuminate\Support\Str::random(60));
            $user->save();
        }
        cookie()->queue(cookie()->forever(
            $guard->getRecallerName(),
            $user->getAuthIdentifier().'|'.$user->getRememberToken().'|'.$user->getAuthPassword()
        ));
        session(['hub_remember_set' => 1]);
    }

    protected function denyUnless(array $names)
    {
        if ($this->personalHub() && $this->personalChatRoute()) {
            return null;
        }
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

    protected $personalHubSession;

    protected function personalHub()
    {
        if ($this->personalHubSession !== null) {
            return $this->personalHubSession ?: null;
        }
        $this->personalHubSession = false;
        if (! \App\Support\MaiHost::is(request())) {
            return null;
        }
        $user = Auth::user();
        if (! $user || \App\Services\Mai\MaiBusinessLine::is($user->phone)) {
            return null;
        }
        $session = app(\App\Services\Mai\PersonalHub::class)->ensure($user);
        $this->personalHubSession = $session ?: false;

        return $session;
    }

    protected function personalChatRoute()
    {
        $name = (string) optional(request()->route())->getName();
        $blocked = ['whatsapp.settings', 'whatsapp.tenants', 'whatsapp.bills', 'whatsapp.twilio', 'whatsapp.diagnostics', 'whatsapp.index'];
        foreach ($blocked as $prefix) {
            if (strpos($name, $prefix) === 0) {
                return false;
            }
        }

        return strpos($name, 'whatsapp.') === 0;
    }

    protected function personalText($to, $text)
    {
        $session = $this->personalHub();
        if (! $session) {
            return null;
        }

        return app(\App\Services\Cloud\MaiWhatsAppEngine::class)->send($session->phone_number ?: $session->expected_phone, $to, $text);
    }

    protected function personalGroupMessages($session, $jid)
    {
        $rows = collect();
        $index = 1;
        foreach (app(\App\Services\Cloud\MaiWhatsAppEngine::class)->messages($session->phone_number ?: $session->expected_phone) as $row) {
            if ((string) $row['from'] !== $jid) {
                continue;
            }
            $item = new \App\WhatsApp\WhatsAppGroupMessage();
            $item->id = $index++;
            $item->body = $row['text'];
            $item->media_json = json_encode(['outgoing' => ! empty($row['from_me'])]);
            $stamp = is_numeric($row['at']) ? (int) $row['at'] : strtotime((string) $row['at']);
            $item->message_at = $stamp ? \Carbon\Carbon::createFromTimestamp($stamp) : \Carbon\Carbon::now();
            $item->participant_name = ! empty($row['from_me']) ? '' : 'WhatsApp';
            $rows->push($item);
        }

        return $rows;
    }

    protected function publicTwilioError($error)
    {
        $error = trim((string) $error);
        $service = app(\App\Services\TwilioWhatsAppService::class);
        $error = str_replace([$service->accountSid(), $service->authToken()], '', $error);
        if (strpos($error, '63016') !== false || stripos($error, 'allowed window') !== false) {
            return 'WhatsApp only accepts a free reply within 24 hours of their last message. This chat is outside that window.';
        }
        if ($error === '') {
            return 'Twilio did not send the reply.';
        }

        return mb_substr($error, 0, 240);
    }
}
