<?php

namespace App\Http\Controllers;

use App\Gbhs\GbhsApplication;
use App\Gbhs\GbhsAuditLog;
use App\Gbhs\GbhsBatch;
use App\Gbhs\GbhsBatchUser;
use App\Gbhs\GbhsBylawVersion;
use App\Gbhs\GbhsCatalog;
use App\Gbhs\GbhsContribution;
use App\Gbhs\GbhsDelivery;
use App\Gbhs\GbhsDocument;
use App\Gbhs\GbhsEvent;
use App\Gbhs\GbhsLeader;
use App\Gbhs\GbhsMember;
use App\Gbhs\GbhsMembership;
use App\Gbhs\GbhsMessage;
use App\Gbhs\GbhsMessenger;
use App\Gbhs\GbhsPayment;
use App\Gbhs\GbhsProject;
use App\Gbhs\GbhsSection;
use App\Gbhs\GbhsSetting;
use App\Gbhs\GbhsTenant;
use App\Letter;
use App\LetterOrganization;
use App\Support\BrandingImage;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class GbhsAdminController extends Controller
{
    protected $permissions = [];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (! GbhsTenant::ready()) {
                return redirect('dashboard')->with('not_permitted', 'Run the GBHS migration before opening this module.');
            }
            GbhsTenant::bind();
            GbhsCatalog::ensure();
            if (Auth::check()) {
                $role = Role::find(Auth::user()->role_id);
                if ($role) {
                    foreach (Role::findByName($role->name)->permissions as $permission) {
                        $this->permissions[] = $permission->name;
                    }
                }
            }
            View::share('all_permission', $this->permissions);
            if (! $this->canAny(['gbhs_module', 'gbhs.manage', 'gbhs.membership', 'gbhs.finance', 'gbhs.communications', 'gbhs.batch'])) {
                return redirect('dashboard')->with('not_permitted', 'Sorry! You are not allowed to access this module');
            }

            return $next($request);
        });
    }

    public function index()
    {
        $batches = $this->batchIds();
        $apps = GbhsApplication::query();
        $members = GbhsMember::query();
        $money = GbhsContribution::query();
        if ($batches !== null) {
            $apps->whereIn('batch_id', $batches);
            $members->whereIn('batch_id', $batches);
            $money->whereIn('batch_id', $batches);
        }

        return view('gbhs.admin.dashboard', [
            'applications' => (clone $apps)->count(),
            'pending' => (clone $apps)->where('status', 'submitted')->count(),
            'members' => $members->count(),
            'verified' => (clone $money)->where('verified', 1)->whereNotIn('classification', ['reversal', 'in_kind'])->sum('amount'),
            'messages' => GbhsMessage::orderBy('id', 'desc')->limit(8)->get(),
        ]);
    }

    public function website()
    {
        $this->need('gbhs.manage');

        return view('gbhs.admin.website', [
            'sections' => GbhsSection::orderBy('sort')->orderBy('id')->get(),
            'leaders' => GbhsLeader::orderBy('sort')->get(),
            'documents' => GbhsDocument::orderBy('sort')->get(),
            'setting' => GbhsSetting::query()->first(),
        ]);
    }

    public function saveSection(Request $request, $id)
    {
        $this->need('gbhs.manage');
        $section = GbhsSection::findOrFail($id);
        $section->fill($request->only(['title', 'body']));
        $section->published = $request->filled('published') ? 1 : 0;
        if ($request->hasFile('image')) {
            $section->image = $this->storeUpload($request->file('image'), 'gbhs/media');
        }
        $section->save();

        return back()->with('message', 'Section saved.');
    }

    public function storeNews(Request $request)
    {
        $this->need('gbhs.manage');
        $request->validate(['title' => 'required|string|max:160', 'body' => 'nullable|string']);
        GbhsSection::create([
            'slug' => 'news-'.time(),
            'title' => $request->title,
            'body' => $request->body,
            'kind' => 'news',
            'published' => 1,
            'sort' => 100,
        ]);

        return back()->with('message', 'News item added.');
    }

    public function storeLeader(Request $request)
    {
        $this->need('gbhs.manage');
        $request->validate(['name' => 'required|string|max:160']);
        $leader = GbhsLeader::create($request->only(['name', 'role', 'phone', 'email']));
        if ($request->hasFile('photo')) {
            $leader->photo = $this->storeUpload($request->file('photo'), 'gbhs/leaders');
            $leader->save();
        }

        return back()->with('message', 'Leader added.');
    }

    public function storeDocument(Request $request)
    {
        $this->need('gbhs.manage');
        $request->validate(['title' => 'required|string|max:160', 'file' => 'required|file|max:12288']);
        GbhsDocument::create([
            'title' => $request->title,
            'file' => $this->storeUpload($request->file('file'), 'gbhs/documents'),
            'published' => $request->filled('published') ? 1 : 0,
        ]);

        return back()->with('message', 'Document saved.');
    }

    public function applications()
    {
        $this->needAny(['gbhs.manage', 'gbhs.membership', 'gbhs.batch']);
        $query = GbhsApplication::orderBy('id', 'desc');
        if ($this->batchIds() !== null) {
            $query->whereIn('batch_id', $this->batchIds());
        }

        return view('gbhs.admin.applications', [
            'applications' => $query->limit(200)->get(),
            'showSignature' => $this->can('gbhs.manage') || $this->can('gbhs.membership'),
        ]);
    }

    public function showApplication($id)
    {
        $this->needAny(['gbhs.manage', 'gbhs.membership', 'gbhs.batch']);
        $application = GbhsApplication::findOrFail($id);
        $this->assertBatch($application->batch_id);

        return view('gbhs.admin.application', [
            'application' => $application,
            'bylaw' => $application->bylaw_version_id ? GbhsBylawVersion::find($application->bylaw_version_id) : null,
            'member' => $application->member_id ? GbhsMember::find($application->member_id) : null,
        ]);
    }

    public function review(Request $request, $id)
    {
        $this->needAny(['gbhs.manage', 'gbhs.membership']);
        $application = GbhsApplication::findOrFail($id);
        $this->assertBatch($application->batch_id);
        $request->validate(['decision' => 'required|in:approved,rejected,under_review,more_info']);
        if ($request->decision === 'approved') {
            if ($application->status === 'rejected') {
                return back()->with('not_permitted', 'A rejected application is not approved from this screen.');
            }
            $member = GbhsMembership::approve($application, Auth::id(), $request->review_note);
            $application->refresh();
            if ($member) {
                GbhsMembership::notifyApproved($application, $member);
            }

            return back()->with('message', 'Membership '.$member->membership_number.' is on file.');
        }
        $application->status = $request->decision;
        $application->review_note = $request->review_note;
        $application->reviewer_id = Auth::id();
        $application->reviewed_at = now();
        $application->save();
        GbhsAuditLog::create([
            'actor_id' => Auth::id(),
            'action' => 'membership.'.$request->decision,
            'summary' => $application->reference,
        ]);

        return back()->with('message', 'Application updated.');
    }

    public function members()
    {
        $this->needAny(['gbhs.manage', 'gbhs.membership', 'gbhs.batch']);
        $query = GbhsMember::orderBy('id', 'desc');
        if ($this->batchIds() !== null) {
            $query->whereIn('batch_id', $this->batchIds());
        }

        return view('gbhs.admin.members', ['members' => $query->limit(300)->get()]);
    }

    public function batches()
    {
        $this->needAny(['gbhs.manage', 'gbhs.batch', 'gbhs.membership']);
        $query = GbhsBatch::orderBy('year');
        if ($this->batchIds() !== null) {
            $query->whereIn('id', $this->batchIds());
        }

        return view('gbhs.admin.batches', [
            'batches' => $query->get(),
            'users' => User::orderBy('name')->limit(200)->get(['id', 'name']),
        ]);
    }

    public function saveBatch(Request $request, $id)
    {
        $this->needAny(['gbhs.manage', 'gbhs.batch']);
        $batch = GbhsBatch::findOrFail($id);
        $this->assertBatch($batch->id);
        $batch->fill($request->only([
            'name', 'status', 'representative_name', 'representative_phone', 'representative_email',
            'contribution_target', 'whatsapp_group_jid', 'notes',
        ]));
        if ($batch->whatsapp_group_jid && ! preg_match('/@g\.us$/', $batch->whatsapp_group_jid)) {
            return back()->with('not_permitted', 'A WhatsApp group id ends with @g.us.');
        }
        $batch->save();
        if ($this->can('gbhs.manage') && $request->filled('user_id')) {
            GbhsBatchUser::firstOrCreate([
                'user_id' => (int) $request->user_id,
                'batch_id' => $batch->id,
            ]);
        }

        return back()->with('message', 'Batch saved.');
    }

    public function events()
    {
        $this->needAny(['gbhs.manage', 'gbhs.communications']);

        return view('gbhs.admin.events', ['events' => GbhsEvent::orderBy('starts_at', 'desc')->get()]);
    }

    public function storeEvent(Request $request)
    {
        $this->needAny(['gbhs.manage', 'gbhs.communications']);
        $request->validate(['title' => 'required|string|max:160']);
        GbhsEvent::create([
            'title' => $request->title,
            'body' => $request->body,
            'starts_at' => $request->starts_at,
            'place' => $request->place,
            'published' => $request->filled('published') ? 1 : 0,
        ]);

        return back()->with('message', 'Event saved.');
    }

    public function letters()
    {
        $this->needAny(['gbhs.manage', 'gbhs.communications']);
        $setting = GbhsSetting::query()->first();
        $letters = Letter::query()->orderBy('id', 'desc');
        if ($setting && $setting->letter_organization_id && Schema::hasColumn('letters', 'organization_id')) {
            $letters->where('organization_id', $setting->letter_organization_id);
        }
        $groups = GbhsBatch::whereNotNull('whatsapp_group_jid')->where('whatsapp_group_jid', '!=', '')->get();

        return view('gbhs.admin.letters', [
            'letters' => $letters->limit(50)->get(),
            'groups' => $groups,
            'deliveries' => GbhsDelivery::orderBy('id', 'desc')->limit(40)->get(),
        ]);
    }

    public function previewGroups(Request $request)
    {
        $this->needAny(['gbhs.manage', 'gbhs.communications']);
        $request->validate(['letter_id' => 'required|integer', 'jids' => 'required|array']);
        $plan = GbhsMessenger::plan($request->jids, (int) $request->letter_id);
        session([
            'gbhs_send_letter' => (int) $request->letter_id,
            'gbhs_send_jids' => $plan,
        ]);

        return view('gbhs.admin.preview', [
            'letter' => Letter::findOrFail($request->letter_id),
            'jids' => $plan,
        ]);
    }

    public function confirmGroups(Request $request)
    {
        $this->needAny(['gbhs.manage', 'gbhs.communications']);
        if (! $request->filled('confirm')) {
            return back()->with('not_permitted', 'Confirm the send before it goes out.');
        }
        $letterId = (int) session('gbhs_send_letter');
        $jids = (array) session('gbhs_send_jids');
        $rows = GbhsMessenger::confirm($letterId, $jids);
        session()->forget(['gbhs_send_letter', 'gbhs_send_jids']);

        return redirect()->route('gbhs.letters')->with('message', count($rows).' group delivery row'.(count($rows) === 1 ? '' : 's').' recorded.');
    }

    public function settings()
    {
        $this->need('gbhs.manage');

        return view('gbhs.admin.settings', [
            'setting' => GbhsSetting::query()->first(),
            'organization' => $this->organization(),
            'bylaws' => GbhsBylawVersion::orderBy('id', 'desc')->get(),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $this->need('gbhs.manage');
        $setting = GbhsSetting::query()->first();
        $setting->fill($request->only([
            'hero_title', 'hero_subtitle', 'seo_title', 'seo_description',
            'address', 'phone', 'email', 'president_name', 'president_title',
        ]));
        $setting->show_provisional = $request->filled('show_provisional') ? 1 : 0;
        if ($request->hasFile('logo')) {
            $setting->logo = $this->storeUpload($request->file('logo'), 'gbhs');
        }
        if ($request->hasFile('hero_image')) {
            $setting->hero_image = $this->storeUpload($request->file('hero_image'), 'gbhs');
        }
        if ($request->hasFile('president_signature')) {
            $setting->president_signature = $this->storeUpload($request->file('president_signature'), 'gbhs');
        }
        $setting->save();
        $org = $this->organization();
        if ($org) {
            if ($request->filled('reference_prefix')) {
                $org->reference_prefix = $request->reference_prefix;
            }
            foreach (['logo' => 'site_logo', 'header' => 'email_header', 'footer' => 'email_footer'] as $field => $slot) {
                if ($request->hasFile('org_'.$field)) {
                    $org->{$field} = BrandingImage::storeFitted($request->file('org_'.$field), public_path('logo'), $slot);
                }
            }
            $org->save();
        }

        return back()->with('message', 'Association settings saved.');
    }

    public function storeBylaw(Request $request)
    {
        $this->need('gbhs.manage');
        $request->validate([
            'version' => 'required|string|max:40',
            'file' => 'required|file|mimes:pdf|max:12288',
            'effective_on' => 'nullable|date',
        ]);
        if ($request->status === 'published') {
            GbhsBylawVersion::where('status', 'published')->update(['status' => 'archived']);
        }
        GbhsBylawVersion::create([
            'version' => $request->version,
            'effective_on' => $request->effective_on,
            'file' => $this->storeUpload($request->file('file'), 'gbhs/bylaws'),
            'status' => $request->status === 'published' ? 'published' : 'draft',
        ]);

        return back()->with('message', 'Bylaws version stored. The text is the uploaded PDF.');
    }

    protected function organization()
    {
        $setting = GbhsSetting::query()->first();
        if (! $setting || ! $setting->letter_organization_id || ! Schema::hasTable('letter_organizations')) {
            return GbhsCatalog::letterOrganization();
        }

        return LetterOrganization::find($setting->letter_organization_id);
    }

    protected function storeUpload($file, $folder)
    {
        $dir = public_path($folder);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = time().'-'.preg_replace('/[^A-Za-z0-9._-]/', '', $file->getClientOriginalName());
        $file->move($dir, $name);

        return trim($folder, '/').'/'.$name;
    }

    protected function batchIds()
    {
        if ($this->can('gbhs.manage') || $this->can('gbhs.membership') || $this->can('gbhs.finance') || $this->can('gbhs.communications')) {
            return null;
        }
        if (! $this->can('gbhs.batch')) {
            return [];
        }

        return GbhsBatchUser::where('user_id', Auth::id())->pluck('batch_id')->all();
    }

    protected function assertBatch($batchId)
    {
        $ids = $this->batchIds();
        if ($ids === null) {
            return;
        }
        if (! in_array((int) $batchId, array_map('intval', $ids), true)) {
            abort(403);
        }
    }

    protected function can($name)
    {
        return in_array($name, $this->permissions, true);
    }

    protected function canAny(array $names)
    {
        foreach ($names as $name) {
            if ($this->can($name)) {
                return true;
            }
        }

        return false;
    }

    protected function need($name)
    {
        if (! $this->can($name) && ! $this->can('gbhs.manage')) {
            abort(403);
        }
    }

    protected function needAny(array $names)
    {
        if ($this->can('gbhs.manage') || $this->canAny($names)) {
            return;
        }
        abort(403);
    }
}
