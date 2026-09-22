<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\WhatsAppLeadService;
use App\User;
use App\WhatsApp\Lead;
use App\WhatsApp\LeadCatalog;
use App\WhatsApp\WhatsAppConversation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class WhatsAppLeadController extends Controller
{
    protected $leads;

    public function __construct(WhatsAppLeadService $leads)
    {
        parent::__construct();
        $this->leads = $leads;
    }

    public function index(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.leads', 'whatsapp.leads.manage', 'whatsapp.manage'])) {
            return $deny;
        }
        $cards = [
            'new' => Lead::where('status', LeadCatalog::STATUS_NEW)->count(),
            'unassigned' => Lead::whereNull('assigned_user_id')->whereNotIn('status', LeadCatalog::closedStatuses())->count(),
            'follow_up' => Lead::whereNotNull('follow_up_at')->where('follow_up_at', '<=', now())->whereNotIn('status', LeadCatalog::closedStatuses())->count(),
            'quotation' => Lead::where('status', LeadCatalog::STATUS_QUOTATION_REQUIRED)->count(),
            'converted' => Lead::where('status', LeadCatalog::STATUS_CONVERTED)->count(),
            'lost' => Lead::where('status', LeadCatalog::STATUS_LOST)->count(),
        ];
        $list = Lead::with(['assignee', 'contact', 'conversation'])
            ->when($request->get('status'), function ($q, $s) {
                $q->where('status', strtoupper($s));
            })
            ->when($request->get('category'), function ($q, $c) {
                $q->where('category', $c);
            })
            ->when($request->get('priority'), function ($q, $p) {
                $q->where('priority', strtoupper($p));
            })
            ->when($request->filled('assigned_user_id'), function ($q) use ($request) {
                $q->where('assigned_user_id', $request->get('assigned_user_id'));
            })
            ->when($request->get('unassigned'), function ($q) {
                $q->whereNull('assigned_user_id');
            })
            ->when($request->get('follow_up_due'), function ($q) {
                $q->whereNotNull('follow_up_at')->where('follow_up_at', '<=', now());
            })
            ->when($request->get('converted'), function ($q) {
                $q->where('status', LeadCatalog::STATUS_CONVERTED);
            })
            ->when($request->get('from') && $request->get('to'), function ($q) use ($request) {
                $q->whereBetween('created_at', [Carbon::parse($request->get('from'))->startOfDay(), Carbon::parse($request->get('to'))->endOfDay()]);
            })
            ->when($request->get('q'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', '%'.$term.'%')
                        ->orWhere('normalized_phone', 'like', '%'.$term.'%')
                        ->orWhere('company', 'like', '%'.$term.'%')
                        ->orWhere('latest_enquiry', 'like', '%'.$term.'%');
                });
            })
            ->orderByDesc('last_activity_at')
            ->paginate(40)
            ->appends($request->query());
        $staff = $this->staff();
        $filters = $request->all();

        return view('whatsapp_hub.leads', compact('list', 'cards', 'staff', 'filters'));
    }

    public function show($id)
    {
        if ($deny = $this->denyUnless(['whatsapp.leads', 'whatsapp.leads.manage', 'whatsapp.manage'])) {
            return $deny;
        }
        $lead = Lead::with(['contact', 'conversation', 'assignee', 'customer', 'activities.actor', 'notes.author'])->findOrFail($id);
        $staff = $this->staff();

        return view('whatsapp_hub.lead', compact('lead', 'staff'));
    }

    public function assign(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.assign', 'whatsapp.leads.manage', 'whatsapp.manage'])) {
            return $deny;
        }
        $lead = Lead::findOrFail($id);
        $this->leads->assign($lead, $request->input('assigned_user_id'), Auth::id());

        return back()->with('message', 'Lead assigned.');
    }

    public function status(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.leads.manage', 'whatsapp.manage'])) {
            return $deny;
        }
        $lead = Lead::findOrFail($id);
        $this->leads->changeStatus($lead, $request->input('status'), Auth::id(), $request->input('lost_reason'));

        return back()->with('message', 'Lead status updated.');
    }

    public function followUp(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.leads.manage', 'whatsapp.manage'])) {
            return $deny;
        }
        $lead = Lead::findOrFail($id);
        $when = $request->input('follow_up_at');
        $this->leads->setFollowUp($lead, $when ? Carbon::parse($when) : null, $request->input('note'), $request->input('assigned_user_id') ?: Auth::id(), Auth::id());

        return back()->with('message', 'Follow-up saved.');
    }

    public function note(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.notes', 'whatsapp.leads.manage', 'whatsapp.manage'])) {
            return $deny;
        }
        $lead = Lead::findOrFail($id);
        $this->leads->addNote($lead, $request->input('body'), Auth::id(), $lead->conversation_id);

        return back()->with('message', 'Internal note saved.');
    }

    public function convert(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.leads.manage', 'whatsapp.manage'])) {
            return $deny;
        }
        $lead = Lead::findOrFail($id);
        $result = $this->leads->convert($lead, Auth::id(), $request->input('create', '1') === '1');
        if (empty($result['customer'])) {
            return back()->with('not_permitted', 'No matching customer and create was not allowed.');
        }

        return back()->with('message', $result['created'] ? 'Customer created and lead converted.' : 'Lead linked to existing customer.');
    }

    public function createFromConversation(Request $request, $conversationId)
    {
        if ($deny = $this->denyUnless(['whatsapp.leads.manage', 'whatsapp.manage'])) {
            return $deny;
        }
        $conversation = WhatsAppConversation::with('contact')->findOrFail($conversationId);
        $lead = $this->leads->createManual($conversation->contact, $conversation, Auth::id(), $request->only(['category', 'company', 'summary', 'priority']));

        return redirect()->route('whatsapp.leads.show', $lead->id)->with('message', 'Lead created.');
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

    protected function denyUnless(array $names)
    {
        $user = Auth::user();
        if (! $user) {
            return redirect()->guest(url('/login'));
        }
        $role = Role::find($user->role_id);
        if ($role) {
            foreach ($names as $name) {
                try {
                    if ($role->hasPermissionTo($name)) {
                        return null;
                    }
                } catch (\Exception $e) {
                }
            }
        }

        return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access WhatsApp leads.');
    }
}
