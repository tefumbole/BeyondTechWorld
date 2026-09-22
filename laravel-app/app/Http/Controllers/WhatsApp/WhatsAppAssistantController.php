<?php

namespace App\Http\Controllers\WhatsApp;

use App\Assistant\AssistantActivity;
use App\Assistant\AssistantKnowledge;
use App\Assistant\IntentCatalog;
use App\Http\Controllers\Controller;
use App\Services\Assistant\AssistantPolicyService;
use App\Services\Assistant\AssistantToolRegistry;
use App\Services\Assistant\BeyondAssistantService;
use App\Services\WhatsApp\WhatsAppConversationService;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class WhatsAppAssistantController extends Controller
{
    public function index(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.ai', 'whatsapp.ai.manage', 'whatsapp.manage'])) {
            return $deny;
        }
        $tab = $request->get('tab', 'status');
        $policy = app(AssistantPolicyService::class);
        $enabled = $policy->globallyEnabled();
        $configured = trim((string) config('assistant.api_key')) !== '';
        $knowledge = AssistantKnowledge::orderBy('category')->orderBy('title')->get();
        $intents = IntentCatalog::all();
        $tools = app(AssistantToolRegistry::class)->all();
        $from = $request->get('from') ? Carbon::parse($request->get('from'))->startOfDay() : Carbon::now()->subDays(6)->startOfDay();
        $to = $request->get('to') ? Carbon::parse($request->get('to'))->endOfDay() : Carbon::now()->endOfDay();
        $activities = AssistantActivity::with('conversation.contact')->orderByDesc('id')->paginate(30)->appends($request->query());
        $failures = AssistantActivity::where('status', AssistantActivity::FAILED)->orderByDesc('id')->limit(20)->get();
        $usage = [
            'requests' => AssistantActivity::whereBetween('created_at', [$from, $to])->count(),
            'input_tokens' => (int) AssistantActivity::whereBetween('created_at', [$from, $to])->sum('input_tokens'),
            'output_tokens' => (int) AssistantActivity::whereBetween('created_at', [$from, $to])->sum('output_tokens'),
        ];
        $usage['total_tokens'] = $usage['input_tokens'] + $usage['output_tokens'];

        return view('whatsapp_hub.assistant', compact(
            'tab', 'enabled', 'configured', 'knowledge', 'intents', 'tools', 'activities', 'failures', 'usage', 'from', 'to'
        ));
    }

    public function updateEnabled(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.ai.manage', 'whatsapp.manage'])) {
            return $deny;
        }
        WhatsAppSetting::putValue('assistant_enabled', $request->input('assistant_enabled') ? '1' : '0');

        return back()->with('message', $request->input('assistant_enabled') ? 'Beyond Assistant enabled.' : 'Beyond Assistant disabled.');
    }

    public function storeKnowledge(Request $request)
    {
        if ($deny = $this->denyUnless(['whatsapp.ai.knowledge', 'whatsapp.ai.manage', 'whatsapp.manage'])) {
            return $deny;
        }
        AssistantKnowledge::create([
            'title' => $request->input('title'),
            'category' => $request->input('category', 'general'),
            'content' => $request->input('content'),
            'enabled' => (bool) $request->input('enabled', 1),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('whatsapp.assistant', ['tab' => 'knowledge'])->with('message', 'Knowledge saved.');
    }

    public function updateKnowledge(Request $request, $id)
    {
        if ($deny = $this->denyUnless(['whatsapp.ai.knowledge', 'whatsapp.ai.manage', 'whatsapp.manage'])) {
            return $deny;
        }
        $row = AssistantKnowledge::findOrFail($id);
        $row->title = $request->input('title', $row->title);
        $row->category = $request->input('category', $row->category);
        $row->content = $request->input('content', $row->content);
        $row->enabled = $request->has('enabled') ? (bool) $request->input('enabled') : $row->enabled;
        $row->updated_by = Auth::id();
        $row->save();

        return redirect()->route('whatsapp.assistant', ['tab' => 'knowledge'])->with('message', 'Knowledge updated.');
    }

    public function enableAi($id)
    {
        if ($deny = $this->denyUnless(['whatsapp.ai', 'whatsapp.takeover', 'whatsapp.manage'])) {
            return $deny;
        }
        app(WhatsAppConversationService::class)->enableAi(WhatsAppConversation::findOrFail($id), Auth::id());

        return back()->with('message', 'Beyond Assistant enabled for this conversation.');
    }

    public function suggest($id)
    {
        if ($deny = $this->denyUnless(['whatsapp.ai.suggest', 'whatsapp.ai', 'whatsapp.manage'])) {
            return $deny;
        }
        $conversation = WhatsAppConversation::with('contact')->findOrFail($id);
        $draft = app(BeyondAssistantService::class)->suggest($conversation);

        return back()->with('suggested_reply', $draft);
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

        return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access Beyond Assistant.');
    }
}
