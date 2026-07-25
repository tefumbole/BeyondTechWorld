<?php

namespace App\Http\Controllers;

use App\ContractTemplate;
use App\ContractType;
use App\Services\Contracts\ContractTemplateService;
use App\Services\Contracts\PlaceholderResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class ContractTemplateController extends Controller
{
    protected $templates;
    protected $all_permission = [];

    public function __construct(ContractTemplateService $templates)
    {
        $this->templates = $templates;
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

    protected function gate()
    {
        if (! in_array('contracts.templates', $this->all_permission, true)
            && ! in_array('contracts_module', $this->all_permission, true)) {
            abort(403, 'You are not allowed to manage contract templates.');
        }
    }

    public function index()
    {
        $this->gate();

        return view('contracts.templates.index', [
            'templates' => ContractTemplate::with(['type', 'currentVersion'])->orderBy('name')->get(),
            'ctTab' => 'contracts.templates',
        ]);
    }

    public function create()
    {
        $this->gate();

        return view('contracts.templates.form', [
            'template' => null,
            'types' => ContractType::where('active', true)->orderBy('name')->get(),
            'ctTab' => 'contracts.templates',
        ]);
    }

    public function store(Request $request)
    {
        $this->gate();
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:60|unique:contract_templates,code',
            'type_id' => 'required|string',
            'content_html' => 'required|string',
        ]);

        $template = ContractTemplate::create([
            'id' => (string) Str::uuid(),
            'type_id' => $request->type_id,
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'active' => (bool) $request->get('active', true),
            'created_by' => Auth::id(),
        ]);

        $schema = $request->placeholder_schema
            ? json_decode($request->placeholder_schema, true)
            : ['required' => []];
        $workflow = $request->signature_workflow_json
            ? json_decode($request->signature_workflow_json, true)
            : ['mode' => 'hybrid', 'stages' => [
                ['stage' => 1, 'roles' => ['party_b']],
                ['stage' => 2, 'roles' => ['witness_b']],
                ['stage' => 3, 'roles' => ['admin', 'party_a']],
                ['stage' => 4, 'roles' => ['witness_a']],
            ]];

        $this->templates->publish($template, $request->content_html, $schema ?: [], $workflow ?: []);

        return redirect()->route('contracts.templates')->with('message', 'Template created and published.');
    }

    public function edit($id)
    {
        $this->gate();
        $template = ContractTemplate::with(['type', 'currentVersion', 'versions'])->findOrFail($id);

        return view('contracts.templates.form', [
            'template' => $template,
            'types' => ContractType::where('active', true)->orderBy('name')->get(),
            'ctTab' => 'contracts.templates',
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->gate();
        $template = ContractTemplate::findOrFail($id);
        $request->validate([
            'name' => 'required|string|max:255',
            'type_id' => 'required|string',
        ]);
        $template->fill([
            'name' => $request->name,
            'type_id' => $request->type_id,
            'description' => $request->description,
            'active' => (bool) $request->get('active', true),
        ]);
        $template->save();

        return back()->with('message', 'Template details saved. Publish a new version to update content.');
    }

    public function publish(Request $request, $id)
    {
        $this->gate();
        $template = ContractTemplate::findOrFail($id);
        $request->validate(['content_html' => 'required|string']);
        $schema = $request->placeholder_schema
            ? (json_decode($request->placeholder_schema, true) ?: [])
            : (optional($template->currentVersion)->placeholder_schema ?: []);
        $workflow = $request->signature_workflow_json
            ? (json_decode($request->signature_workflow_json, true) ?: [])
            : (optional($template->currentVersion)->signature_workflow_json ?: []);

        $version = $this->templates->publish($template, $request->content_html, $schema, $workflow);

        return back()->with('message', 'Published version '.$version->version_no.'. Existing contracts are unchanged.');
    }

    /**
     * Render template HTML the way a contract would display (placeholders filled with sample data).
     */
    public function preview(Request $request, PlaceholderResolver $resolver, $id = null)
    {
        $this->gate();
        $html = $request->input('content_html');
        if ($html === null && $id) {
            $template = ContractTemplate::with('currentVersion')->findOrFail($id);
            $html = optional($template->currentVersion)->content_html;
        }
        $sample = $this->samplePlaceholderData();
        $flat = $this->flattenSample($sample);
        $body = $resolver->resolve((string) $html, array_merge($flat, $sample));

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['html' => $body]);
        }

        return view('contracts.templates.preview', [
            'bodyHtml' => $body,
            'templateName' => $request->get('name', 'Template preview'),
        ]);
    }

    protected function samplePlaceholderData()
    {
        return [
            'contract' => [
                'number' => 'CNT-'.date('Y').'-000001',
                'effective_date' => date('d F Y'),
                'start_date' => date('d F Y'),
                'end_date' => date('d F Y', strtotime('+3 days')),
                'value' => '150,000',
                'currency' => 'XAF',
                'jurisdiction' => 'Republic of Cameroon',
                'purpose' => 'Sample engagement',
            ],
            'party_a' => [
                'role_label' => 'Party A',
                'name' => 'Beyond Enterprise',
                'address' => 'Douala, Cameroon',
                'email' => 'contracts@beyondtechworld.com',
                'phone' => '+237600000000',
                'signer_name' => 'Authorized Signatory',
                'signer_title' => 'Director',
            ],
            'party_b' => [
                'role_label' => 'Party B',
                'name' => 'Sample Counterparty',
                'address' => 'Yaoundé, Cameroon',
                'email' => 'party.b@example.com',
                'phone' => '+237611111111',
            ],
            'witness_a' => ['name' => 'Witness A'],
            'witness_b' => ['name' => 'Witness B'],
            'worker' => [
                'role' => 'Lead Engineer',
                'daily_rate' => '6,000',
                'food_allowance' => '1,000',
            ],
            'event' => [
                'name' => 'Sample Concert Event',
                'venue' => 'Palais des Sports',
                'start_date' => date('d F Y'),
                'end_date' => date('d F Y', strtotime('+2 days')),
            ],
            'quotation' => ['number' => 'QT-SAMPLE-001', 'total' => '150,000 XAF'],
            'work' => [
                'schedule' => 'as communicated',
                'estimated_days' => '3',
                'day_rate_rule' => 'full day unless otherwise authorized',
                'reporting_time' => '08:00',
                'supervisor_name' => 'Site Supervisor',
            ],
            'payment' => [
                'schedule' => 'On completion',
                'due_rule' => 'within 7 days of approved timesheets',
                'method' => 'bank transfer or Mobile Money',
            ],
            'cancellation' => [
                'rule' => 'Payment remains due only for verified eligible work already completed.',
            ],
            'signature' => [
                'party_a' => '________________',
                'party_b' => '________________',
                'witness_a' => '________________',
                'witness_b' => '________________',
                'party_a_date' => '',
                'party_b_date' => '',
                'witness_a_date' => '',
                'witness_b_date' => '',
            ],
        ];
    }

    protected function flattenSample(array $data, $prefix = '')
    {
        $out = [];
        foreach ($data as $k => $v) {
            $key = $prefix === '' ? $k : $prefix.'.'.$k;
            if (is_array($v)) {
                $out = array_merge($out, $this->flattenSample($v, $key));
            } else {
                $out[$key] = $v;
            }
        }

        return $out;
    }
}
