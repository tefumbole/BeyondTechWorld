<?php

namespace App\Http\Controllers;

use App\Customer;
use App\Services\AnnouncementService;
use App\Services\PeopleDirectoryService;
use App\WaAnnouncement;
use App\WaAnnouncementReminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AnnouncementManagerController extends Controller
{
    protected $announcements;
    protected $all_permission = [];

    public function __construct(AnnouncementService $announcements)
    {
        $this->announcements = $announcements;
        $this->middleware(function ($request, $next) {
            if (Auth::check()) {
                $role = Role::find(Auth::user()->role_id);
                if ($role) {
                    foreach (Role::findByName($role->name)->permissions as $permission) {
                        $this->all_permission[] = $permission->name;
                    }
                }
            }
            View::share('all_permission', $this->all_permission);

            return $next($request);
        });
    }

    protected function authorizeAnnouncements($permission = 'announcements.view')
    {
        if (\App\Support\MaiHost::is(request()) && Auth::check()) {
            return;
        }
        if (in_array('announcements_module', $this->all_permission, true)
            || in_array($permission, $this->all_permission, true)
            || in_array('announcement_index', $this->all_permission, true)) {
            return;
        }
        abort(403, 'You are not allowed to access Announcements.');
    }

    protected function personalSessionId()
    {
        if (! \App\Support\MaiHost::is(request()) || ! Auth::check()) {
            return null;
        }
        $session = app(\App\Services\Mai\PersonalHub::class)->ensure(Auth::user());

        return $session ? (int) $session->id : null;
    }

    protected function usersJson()
    {
        $phonePeople = $this->phonePeople('');
        if ($phonePeople !== null) {
            return collect($phonePeople)->values();
        }

        return collect($this->announcements->eligibleUsers())->values();
    }

    public function searchUsers(Request $request)
    {
        $this->authorizeAnnouncements('announcements.create');
        $phonePeople = $this->phonePeople($request->query('q', ''));
        if ($phonePeople !== null) {
            return response()->json(collect($phonePeople)->values());
        }
        $users = $this->announcements->eligibleUsers(
            $request->query('filter', 'all'),
            $request->query('q', '')
        );

        return response()->json(collect($users)->values());
    }

    protected function phonePeople($search)
    {
        if (! \App\Support\MaiHost::is(request()) || ! Auth::check()) {
            return null;
        }
        $session = app(\App\Services\Mai\PersonalHub::class)->ensure(Auth::user());
        $owner = $session ? ($session->phone_number ?: $session->expected_phone) : '';
        if ($owner === '') {
            return [];
        }
        $term = strtolower(trim((string) $search));
        $out = [];
        $seen = [];
        $add = function ($phone, $name) use (&$out, &$seen, $term) {
            $digits = preg_replace('/\D/', '', (string) $phone);
            if (! preg_match('/^[1-9][0-9]{7,14}$/', $digits) || isset($seen[$digits])) {
                return;
            }
            $name = trim((string) $name);
            if ($name === '' || preg_match('/^\+?[\d\s\-()]+$/', $name)) {
                $name = '+'.$digits;
            }
            if ($term !== '' && strpos(strtolower($name.' '.$digits), $term) === false) {
                return;
            }
            $seen[$digits] = true;
            $out[] = [
                'id' => 'wa:'.$digits,
                'name' => $name,
                'email' => '',
                'phone' => $digits,
                'address' => '',
                'role' => 'contact',
                'source' => 'WhatsApp',
            ];
        };
        if ($session && \Illuminate\Support\Facades\Schema::hasTable('mai_peers')) {
            foreach (\App\Mai\MaiPeer::where('mai_session_id', $session->id)->get() as $peer) {
                if (substr((string) $peer->remote_jid, -15) !== '@s.whatsapp.net') {
                    continue;
                }
                $saved = trim((string) $peer->saved_name);
                $add($peer->phone_digits, $saved !== '' ? $saved : $peer->phone_digits);
            }
        }
        foreach (app(\App\Services\Cloud\MaiWhatsAppEngine::class)->contacts($owner) as $row) {
            $add($row['phone'], $row['name'] !== '' ? $row['name'] : $row['phone']);
        }

        return $out;
    }

    /**
     * Quick-add a recipient (customer) from the announcement compose screen.
     */
    public function quickRecipient(Request $request)
    {
        if (\App\Support\MaiHost::is($request)) {
            return response()->json(['message' => 'Choose a person from your WhatsApp list.'], 422);
        }
        $this->authorizeAnnouncements('announcements.create');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:500',
            'student' => 'nullable|string|max:255',
            'class_name' => 'nullable|string|max:255',
        ]);

        try {
            $result = app(PeopleDirectoryService::class)->findOrCreateCustomerQuick($data);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'created' => $result['created'],
            'customer_id' => $result['customer']->id,
            'user' => $result['person'],
        ]);
    }

    public function lookupGroups(Request $request)
    {
        $this->authorizeAnnouncements('announcements.create');
        @set_time_limit(90);
        $jids = preg_split('/\s*,\s*/', trim((string) $request->query('jids', '')));
        $jids = array_values(array_filter((array) $jids, function ($jid) {
            return substr((string) $jid, -5) === '@g.us';
        }));

        return response()->json(
            app(\App\Services\WhatsApp\GroupContactExportService::class)->enrich(array_slice($jids, 0, 8))
        );
    }

    public function composeState(Request $request)
    {
        $this->authorizeAnnouncements('announcements.create');
        @set_time_limit(90);
        $users = $this->usersJson();
        $categories = $this->announcements->categories();
        $settings = $this->announcements->settings();
        $sessionId = $this->personalSessionId();
        $clientBook = (bool) $sessionId;
        $templates = $clientBook ? collect() : $this->announcements->templates();
        if ($sessionId) {
            $session = \App\Mai\MaiSession::find($sessionId);
            $waGroups = $session ? app(\App\Services\Mai\PersonalHub::class)->groups($session) : [];
        } else {
            $waGroups = app(\App\Services\WhatsApp\GroupContactExportService::class)->announcementGroups();
            app(\App\Services\WhatsApp\GroupContactExportService::class)->scheduleResolve();
        }
        $clone = null;
        if ($request->filled('clone')) {
            $src = WaAnnouncement::find($request->get('clone'));
            if ($src) {
                $clone = $this->announcements->cloneAnnouncement($src);
            }
        }
        if ($request->filled('template')) {
            $tpl = $this->announcements->templates()->firstWhere('id', (int) $request->get('template'));
            if ($tpl) {
                $clone = array_merge($clone ?: [], [
                    'subject' => $tpl->subject,
                    'header' => $tpl->header,
                    'body' => $tpl->body,
                    'category_id' => $tpl->category_id,
                ]);
            }
        }

        return compact('users', 'categories', 'templates', 'settings', 'clone', 'waGroups', 'clientBook');
    }

    public function compose(Request $request)
    {
        if (\App\Support\MaiHost::is($request)) {
            return redirect()->route('whatsapp.chats', array_filter([
                'pane' => 'announcements',
                'group' => $request->get('group'),
            ]));
        }

        return view('announcement_manager.compose', $this->composeState($request));
    }

    protected function leaveAnnouncements($key, $message)
    {
        $to = \App\Support\MaiHost::is(request())
            ? redirect()->route('whatsapp.chats', ['pane' => 'announcements'])
            : redirect()->route('announcements.index');

        return $to->with($key, $message);
    }

    public function previewRecipients(Request $request)
    {
        $this->authorizeAnnouncements('announcements.create');
        @set_time_limit(90);
        if ($sessionId = $this->personalSessionId()) {
            $this->announcements->forSession($sessionId);
        }
        $audience = (string) $request->input('audience', 'people');
        try {
            $people = $audience === 'group'
                ? $this->announcements->previewGroupPeople((array) $request->input('group_jids', []))
                : $this->announcements->resolvePeople((array) $request->input('recipient_ids', []));
            if (! $this->personalSessionId()) {
                $people = $this->announcements->withSchoolRoster($people);
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json(['people' => [], 'error' => $e->getMessage()], 422);
        }
        $rows = [];
        foreach ($people as $person) {
            $phone = preg_replace('/\D+/', '', (string) (isset($person['phone']) ? $person['phone'] : ''));
            $name = trim((string) (isset($person['name']) ? $person['name'] : ''));
            if ($phone === '') {
                continue;
            }
            if ($name === '' || preg_match('/^\d+$/', $name)) {
                $name = '';
            }
            $rows[] = [
                'phone' => $phone,
                'name' => $name,
                'parent' => isset($person['parent']) ? $person['parent'] : $name,
                'student' => isset($person['student']) ? $person['student'] : '',
                'class' => isset($person['class']) ? $person['class'] : '',
                'group' => isset($person['group']) ? $person['group'] : '',
            ];
        }

        return response()->json(['people' => $rows]);
    }

    public function store(Request $request)
    {
        $this->authorizeAnnouncements('announcements.create');
        @set_time_limit(180);

        $attachmentPath = null;
        $attachmentName = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $dir = public_path('images/announcements');
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $name = 'ann_' . time() . '_' . Str::random(6) . '.' . $file->getClientOriginalExtension();
            $file->move($dir, $name);
            $attachmentPath = 'images/announcements/' . $name;
            $attachmentName = $file->getClientOriginalName();
        }

        if ($this->personalSessionId()) {
            $onlyContacts = function ($ids) {
                return array_values(array_filter((array) $ids, function ($id) {
                    return strpos((string) $id, 'wa:') === 0;
                }));
            };
            $request->merge([
                'recipient_ids' => $onlyContacts($request->input('recipient_ids', [])),
                'cc_ids' => $onlyContacts($request->input('cc_ids', [])),
            ]);
        }

        if (! $request->has('send_whatsapp') && ! $request->has('send_sms')) {
            return back()->withInput()->with('not_permitted', 'Choose WhatsApp or SMS.');
        }

        try {
            $row = $this->announcements->create([
            'subject' => $request->input('subject'),
            'header' => $request->input('header'),
            'body' => $request->input('body'),
            'footer' => $request->input('footer'),
            'category_id' => $request->input('category_id') ?: null,
            'recipient_ids' => $request->input('recipient_ids', []),
            'cc_ids' => $request->input('cc_ids', []),
            'audience' => $request->input('audience', 'people'),
            'group_jids' => $request->input('group_jids', []),
            'name_overrides' => $request->input('name_overrides', []),
            'school_overrides' => $request->input('school', []),
            'send_whatsapp' => $request->has('send_whatsapp'),
            'send_sms' => $request->has('send_sms'),
            'personalized' => $request->has('personalized'),
            'broadcast' => $request->has('broadcast'),
            'send_mode' => $request->input('send_mode', 'now'),
            'schedule_at' => $request->input('schedule_at'),
            'reminders' => $request->input('reminders', []),
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'cloned_from_id' => $request->input('cloned_from_id'),
            'mai_session_id' => $this->personalSessionId(),
        ], Auth::id());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('not_permitted', $e->getMessage());
        }

        if ($request->filled('save_as_template') || $request->input('save_as_template') == '1') {
            $this->announcements->storeTemplate([
                'name' => $request->input('template_name') ?: ($row->subject . ' template'),
                'category_id' => $row->category_id,
                'subject' => $row->subject,
                'header' => $row->header,
                'body' => $row->body,
            ]);
        }

        $row = $row->fresh();
        if ($row->whatsapp_status === 'sending') {
            $count = count($row->recipients());

            return $this->leaveAnnouncements('message', 'Announcement is going out to '.$count.' contact'.($count === 1 ? '' : 's').' by '.$this->announcementChannels($row).' ('.$row->reference.'). Delivery continues in the background.');
        }

        if ($row->status === 'scheduled') {
            return $this->leaveAnnouncements('message', 'Announcement scheduled (' . $row->reference . ').');
        }

        $failures = [];
        foreach (($row->send_results_json ? json_decode($row->send_results_json, true) : []) ?: [] as $r) {
            if (empty($r['ok'])) {
                $failures[] = ($r['name'] ?? 'Recipient') . ' (' . ($r['phone'] ?? 'no phone') . ')'
                    . (! empty($r['error']) ? ': '.$r['error'] : '');
            }
        }

        if ($row->whatsapp_status === 'sent' && empty($failures)) {
            return $this->leaveAnnouncements('message', 'Announcement sent by '.$this->announcementChannels($row).' (' . $row->reference . ').');
        }

        if ($row->whatsapp_status === 'partial') {
            return $this->leaveAnnouncements('message', 'Announcement partially sent (' . $row->reference . '). Failed: ' . implode('; ', $failures));
        }

        return $this->leaveAnnouncements('not_permitted',
                'Announcement saved (' . $row->reference . ') but WhatsApp delivery failed. '
                . 'Check each recipient phone in People → Customers/Users (full number e.g. 675321739, not +237237…). '
                . 'Failed: ' . (implode('; ', $failures) ?: 'unknown')
            );
    }

    public function index(Request $request)
    {
        $this->authorizeAnnouncements('announcements.view');
        $items = $this->announcements->list($request->get('status'), $request->get('q'));

        return view('announcement_manager.index', compact('items'));
    }

    public function scheduled()
    {
        $this->authorizeAnnouncements('announcements.view');
        $items = $this->announcements->list('scheduled');

        return view('announcement_manager.scheduled', compact('items'));
    }

    public function reminders()
    {
        $this->authorizeAnnouncements('announcements.view');
        $reminders = $this->announcements->reminders();

        return view('announcement_manager.reminders', compact('reminders'));
    }

    public function deleteReminder($id)
    {
        $this->authorizeAnnouncements('announcements.delete');
        $this->announcements->deleteReminder($id);

        return back()->with('message', 'Reminder deleted.');
    }

    public function destroy($id)
    {
        $this->authorizeAnnouncements('announcements.delete');
        $this->announcements->softDelete($id);

        return back()->with('message', 'Announcement deleted.');
    }

    public function templates()
    {
        $this->authorizeAnnouncements('announcements.view');
        $templates = $this->announcements->templates();
        $categories = $this->announcements->categories();

        return view('announcement_manager.templates', compact('templates', 'categories'));
    }

    public function storeTemplate(Request $request)
    {
        $this->authorizeAnnouncements('announcements.create');
        $this->announcements->storeTemplate($request->only('name', 'category_id', 'subject', 'header', 'body'));

        return back()->with('message', 'Template saved.');
    }

    public function destroyTemplate($id)
    {
        $this->authorizeAnnouncements('announcements.delete');
        $this->announcements->deleteTemplate($id);

        return back()->with('message', 'Template deleted.');
    }

    public function categories()
    {
        $this->authorizeAnnouncements('announcements.settings');
        $categories = $this->announcements->categories();

        return view('announcement_manager.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $this->authorizeAnnouncements('announcements.settings');
        $this->announcements->storeCategory($request->only('name', 'description'));

        return back()->with('message', 'Category added.');
    }

    public function destroyCategory($id)
    {
        $this->authorizeAnnouncements('announcements.settings');
        $this->announcements->deleteCategory($id);

        return back()->with('message', 'Category deleted.');
    }

    public function settings()
    {
        $this->authorizeAnnouncements('announcements.settings');
        $settings = $this->announcements->settings();

        return view('announcement_manager.settings', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $this->authorizeAnnouncements('announcements.settings');
        $this->announcements->updateSettings($request->only(
            'company_name', 'default_header', 'serial_prefix', 'next_serial', 'serial_padding', 'timezone', 'timezone_offset'
        ));

        return back()->with('message', 'Settings saved.');
    }

    protected function announcementChannels($row)
    {
        $channels = [];
        if ($row->send_whatsapp) {
            $channels[] = 'WhatsApp';
        }
        if ($row->send_sms) {
            $channels[] = 'SMS';
        }

        return $channels ? implode(' and ', $channels) : 'WhatsApp';
    }
}
