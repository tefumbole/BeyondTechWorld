<?php

namespace App\Http\Controllers;

use App\Services\AnnouncementService;
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
        if (in_array('announcements_module', $this->all_permission, true)
            || in_array($permission, $this->all_permission, true)
            || in_array('announcement_index', $this->all_permission, true)) {
            return;
        }
        abort(403, 'You are not allowed to access Announcements.');
    }

    protected function usersJson()
    {
        return collect($this->announcements->eligibleUsers())->values();
    }

    public function compose(Request $request)
    {
        $this->authorizeAnnouncements('announcements.create');
        $users = $this->usersJson();
        $categories = $this->announcements->categories();
        $templates = $this->announcements->templates();
        $settings = $this->announcements->settings();
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

        return view('announcement_manager.compose', compact('users', 'categories', 'templates', 'settings', 'clone'));
    }

    public function store(Request $request)
    {
        $this->authorizeAnnouncements('announcements.create');

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

        $row = $this->announcements->create([
            'subject' => $request->input('subject'),
            'header' => $request->input('header'),
            'body' => $request->input('body'),
            'footer' => $request->input('footer'),
            'category_id' => $request->input('category_id') ?: null,
            'recipient_ids' => $request->input('recipient_ids', []),
            'cc_ids' => $request->input('cc_ids', []),
            'send_whatsapp' => $request->has('send_whatsapp') ? true : ((string) $request->input('send_whatsapp', '1') === '1'),
            'send_mode' => $request->input('send_mode', 'now'),
            'schedule_at' => $request->input('schedule_at'),
            'reminders' => $request->input('reminders', []),
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'cloned_from_id' => $request->input('cloned_from_id'),
        ], Auth::id());

        if ($request->filled('save_as_template') || $request->input('save_as_template') == '1') {
            $this->announcements->storeTemplate([
                'name' => $request->input('template_name') ?: ($row->subject . ' template'),
                'category_id' => $row->category_id,
                'subject' => $row->subject,
                'header' => $row->header,
                'body' => $row->body,
            ]);
        }

        $msg = $row->status === 'scheduled'
            ? 'Announcement scheduled (' . $row->reference . ').'
            : 'Announcement sent (' . $row->reference . ').';

        return redirect()->route('announcements.index')->with('message', $msg);
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
}
