<?php

namespace App\Http\Controllers;

use App\Nbc\NbcAccess;
use App\Nbc\NbcAnnouncement;
use App\Nbc\NbcAttendance;
use App\Nbc\NbcBylaw;
use App\Nbc\NbcEvent;
use App\Nbc\NbcLetter;
use App\Nbc\NbcMember;
use App\Nbc\NbcMessage;
use App\Nbc\NbcQuotation;
use App\Nbc\NbcTask;
use Illuminate\Http\Request;

class NbcPortalController extends Controller
{
    public function home(Request $request)
    {
        $member = $this->member($request);
        $open = NbcAttendance::where('member_id', $member->id)->whereNull('clock_out')->first();

        return view('nbc.portal', [
            'member' => $member,
            'openAttendance' => $open,
            'modules' => $this->modules($member),
        ]);
    }

    public function profile(Request $request)
    {
        $this->permit($request, 'nbc.profile');

        return view('nbc.profile', ['member' => $this->member($request)]);
    }

    public function saveProfile(Request $request)
    {
        $member = $this->permit($request, 'nbc.profile');
        $data = $request->validate([
            'phone' => 'nullable|string|max:40',
            'part' => 'nullable|string|max:120',
            'about' => 'nullable|string|max:2000',
        ]);
        $member->fill($data);
        $member->save();

        return back()->with('nbc_message', 'Your details are saved.');
    }

    public function attendance(Request $request)
    {
        $member = $this->member($request);
        if (! $member->allows('nbc.attendance.self') && ! $member->allows('nbc.attendance.review')) {
            abort(403);
        }
        $open = NbcAttendance::where('member_id', $member->id)->whereNull('clock_out')->first();
        $mine = NbcAttendance::where('member_id', $member->id)->orderByDesc('clock_in')->limit(20)->get();
        $all = $member->allows('nbc.attendance.review')
            ? NbcAttendance::with('member', 'event')->orderByDesc('clock_in')->limit(40)->get()
            : collect();
        $practices = NbcEvent::where('published', true)->where('kind', 'practice')->orderByDesc('starts_at')->limit(12)->get();

        return view('nbc.attendance', compact('member', 'open', 'mine', 'all', 'practices'));
    }

    public function clockIn(Request $request)
    {
        $member = $this->permit($request, 'nbc.attendance.self');
        $open = NbcAttendance::where('member_id', $member->id)->whereNull('clock_out')->first();
        if ($open) {
            return back()->with('nbc_error', 'You are already clocked in.');
        }
        $eventId = $request->input('event_id');
        NbcAttendance::create([
            'member_id' => $member->id,
            'event_id' => $eventId ? (int) $eventId : null,
            'clock_in' => now(),
        ]);

        return back()->with('nbc_message', 'You are clocked in.');
    }

    public function clockOut(Request $request)
    {
        $member = $this->permit($request, 'nbc.attendance.self');
        $open = NbcAttendance::where('member_id', $member->id)->whereNull('clock_out')->first();
        if (! $open) {
            return back()->with('nbc_error', 'You are not clocked in.');
        }
        $open->clock_out = now();
        $open->save();

        return back()->with('nbc_message', 'You are clocked out.');
    }

    public function people(Request $request)
    {
        $this->permit($request, 'nbc.people');
        $people = NbcMember::orderBy('name')->get();

        return view('nbc.people', ['people' => $people, 'keys' => NbcAccess::keys()]);
    }

    public function savePerson(Request $request, $id)
    {
        $actor = $this->permit($request, 'nbc.people');
        $person = NbcMember::findOrFail($id);
        $data = $request->validate([
            'role' => 'required|in:owner,leader,member',
            'status' => 'required|in:pending,active,declined',
        ]);
        if ($person->id === $actor->id && $data['status'] !== 'active') {
            return back()->with('nbc_error', 'You cannot lock your own account.');
        }
        $person->role = $data['role'];
        $person->status = $data['status'];
        if ($actor->allows('nbc.permissions')) {
            $picked = $request->input('permissions', []);
            if (! is_array($picked)) {
                $picked = [];
            }
            $allowed = array_keys(NbcAccess::keys());
            $picked = array_values(array_intersect($picked, $allowed));
            if ($person->id === $actor->id) {
                $picked = array_values(array_unique(array_merge($picked, ['nbc.people', 'nbc.permissions', 'nbc.bylaws'])));
            }
            $person->permissions = json_encode($picked);
        }
        $person->save();

        return back()->with('nbc_message', $person->name.' is updated.');
    }

    public function bylaws(Request $request)
    {
        $this->permit($request, 'nbc.bylaws');

        return view('nbc.bylaws', ['bylaws' => NbcBylaw::current()]);
    }

    public function saveBylaws(Request $request)
    {
        $this->permit($request, 'nbc.bylaws');
        $data = $request->validate(['body' => 'required|string|max:20000']);
        $current = NbcBylaw::current();
        NbcBylaw::create([
            'version' => $current->version + 1,
            'body' => $data['body'],
        ]);

        return back()->with('nbc_message', 'A new bylaws version is saved. New applications will sign this text.');
    }

    public function events(Request $request)
    {
        $this->permit($request, 'events');
        $events = NbcEvent::orderByDesc('starts_at')->orderBy('sort')->get();

        return view('nbc.module-events', compact('events'));
    }

    public function storeEvent(Request $request)
    {
        $member = $this->permit($request, 'events');
        $data = $this->eventData($request);
        $data['author_id'] = $member->id;
        $data['published'] = $request->has('published');
        if (! isset($data['sort']) || $data['sort'] === null) {
            $data['sort'] = 0;
        }
        NbcEvent::create($data);

        return back()->with('nbc_message', 'Saved.');
    }

    public function announcements(Request $request)
    {
        $this->permit($request, 'announcements');
        $rows = NbcAnnouncement::orderByDesc('id')->get();

        return view('nbc.module-announcements', compact('rows'));
    }

    public function storeAnnouncement(Request $request)
    {
        $member = $this->permit($request, 'announcements');
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'body' => 'required|string|max:5000',
        ]);
        NbcAnnouncement::create([
            'title' => $data['title'],
            'body' => $data['body'],
            'published_at' => $request->has('publish') ? now() : null,
            'author_id' => $member->id,
        ]);

        return back()->with('nbc_message', 'Announcement saved.');
    }

    public function tasks(Request $request)
    {
        $this->permit($request, 'tasks');
        $tasks = NbcTask::with('assignee')->orderByDesc('id')->get();
        $people = NbcMember::where('status', 'active')->orderBy('name')->get();

        return view('nbc.module-tasks', compact('tasks', 'people'));
    }

    public function storeTask(Request $request)
    {
        $member = $this->permit($request, 'tasks');
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'body' => 'nullable|string|max:5000',
            'assignee_id' => 'nullable|integer',
            'due_on' => 'nullable|date',
        ]);
        $data['author_id'] = $member->id;
        $data['status'] = 'open';
        NbcTask::create($data);

        return back()->with('nbc_message', 'Task saved.');
    }

    public function closeTask(Request $request, $id)
    {
        $this->permit($request, 'tasks');
        $task = NbcTask::findOrFail($id);
        $task->status = $task->status === 'done' ? 'open' : 'done';
        $task->save();

        return back();
    }

    public function letters(Request $request)
    {
        $this->permit($request, 'letters');
        $letters = NbcLetter::orderByDesc('id')->get();
        $people = NbcMember::where('status', 'active')->orderBy('name')->get();

        return view('nbc.module-letters', compact('letters', 'people'));
    }

    public function storeLetter(Request $request)
    {
        $member = $this->permit($request, 'letters');
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'recipient_name' => 'nullable|string|max:191',
            'recipient_id' => 'nullable|integer',
            'letter_date' => 'nullable|date',
            'body' => 'required|string|max:8000',
        ]);
        $data['author_id'] = $member->id;
        NbcLetter::create($data);

        return back()->with('nbc_message', 'Letter saved.');
    }

    public function quotations(Request $request)
    {
        $this->permit($request, 'quotations');
        $quotations = NbcQuotation::orderByDesc('id')->get();

        return view('nbc.module-quotations', compact('quotations'));
    }

    public function storeQuotation(Request $request)
    {
        $member = $this->permit($request, 'quotations');
        $data = $request->validate([
            'client_name' => 'required|string|max:191',
            'amount' => 'nullable|numeric|min:0',
            'body' => 'nullable|string|max:8000',
        ]);
        $next = NbcQuotation::count() + 1;
        NbcQuotation::create([
            'number' => 'NBC-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT),
            'client_name' => $data['client_name'],
            'amount' => isset($data['amount']) ? $data['amount'] : 0,
            'body' => isset($data['body']) ? $data['body'] : null,
            'status' => 'draft',
            'author_id' => $member->id,
        ]);

        return back()->with('nbc_message', 'Quotation saved.');
    }

    public function whatsapp(Request $request)
    {
        $this->permit($request, 'whatsapp');
        $messages = NbcMessage::with('member')->orderByDesc('id')->limit(40)->get();
        $people = NbcMember::where('status', 'active')->orderBy('name')->get();

        return view('nbc.module-whatsapp', compact('messages', 'people'));
    }

    public function storeWhatsapp(Request $request)
    {
        $member = $this->permit($request, 'whatsapp');
        $data = $request->validate([
            'body' => 'required|string|max:2000',
            'member_id' => 'nullable|integer',
        ]);
        NbcMessage::create([
            'audience' => ! empty($data['member_id']) ? 'one' : 'members',
            'member_id' => ! empty($data['member_id']) ? $data['member_id'] : null,
            'body' => $data['body'],
            'author_id' => $member->id,
        ]);

        return back()->with('nbc_message', 'Notice saved for the team. It stays in this portal.');
    }

    protected function member(Request $request)
    {
        return $request->attributes->get('nbcMember');
    }

    protected function permit(Request $request, $key)
    {
        $member = $this->member($request);
        if (! $member || ! $member->allows($key)) {
            abort(403);
        }

        return $member;
    }

    protected function modules(NbcMember $member)
    {
        $all = [
            ['key' => 'events', 'label' => 'Program and events', 'route' => 'nbc.events.manage'],
            ['key' => 'announcements', 'label' => 'Announcements', 'route' => 'nbc.announcements.manage'],
            ['key' => 'tasks', 'label' => 'Task manager', 'route' => 'nbc.tasks'],
            ['key' => 'letters', 'label' => 'Letters', 'route' => 'nbc.letters'],
            ['key' => 'quotations', 'label' => 'Quotations', 'route' => 'nbc.quotations'],
            ['key' => 'whatsapp', 'label' => 'WhatsApp notices', 'route' => 'nbc.whatsapp'],
            ['key' => 'nbc.attendance.self', 'label' => 'Attendance', 'route' => 'nbc.attendance'],
            ['key' => 'nbc.profile', 'label' => 'What I do', 'route' => 'nbc.profile'],
            ['key' => 'nbc.people', 'label' => 'People', 'route' => 'nbc.people'],
            ['key' => 'nbc.bylaws', 'label' => 'Bylaws', 'route' => 'nbc.bylaws'],
        ];
        $open = [];
        foreach ($all as $row) {
            if ($member->allows($row['key']) || ($row['key'] === 'nbc.attendance.self' && $member->allows('nbc.attendance.review'))) {
                $open[] = $row;
            }
        }

        return $open;
    }

    protected function eventData(Request $request)
    {
        return $request->validate([
            'title' => 'required|string|max:191',
            'kind' => 'required|in:event,practice,program',
            'starts_at' => 'nullable|date',
            'place' => 'nullable|string|max:191',
            'body' => 'nullable|string|max:5000',
            'sort' => 'nullable|integer|min:0|max:999',
        ]);
    }
}
