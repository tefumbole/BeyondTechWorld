<?php

namespace App\Http\Controllers;

use App\EventContractTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;

class EventContractTemplateController extends Controller
{
    protected $all_permission = [];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $role = Role::find(Auth::user()->role_id);
            foreach (Role::findByName($role->name)->permissions as $permission) {
                $this->all_permission[] = $permission->name;
            }
            View::share('all_permission', $this->all_permission);

            return $next($request);
        });
    }

    protected function can($perm)
    {
        if (! in_array($perm, $this->all_permission, true)) {
            abort(403);
        }
    }

    public function index()
    {
        $this->can('event_contracts.view');
        $templates = EventContractTemplate::orderBy('name')->get();

        return view('events.settings.contract_templates', compact('templates'));
    }

    public function store(Request $request)
    {
        $this->can('event_contracts.create');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'contract_type' => 'nullable|string|max:64',
            'header' => 'nullable|string',
            'body' => 'required|string',
            'footer' => 'nullable|string',
        ]);

        EventContractTemplate::create(array_merge($data, [
            'is_active' => true,
            'created_by' => Auth::id(),
        ]));

        return back()->with('message', 'Contract template created.');
    }

    public function update(Request $request, $id)
    {
        $this->can('event_contracts.create');

        $template = EventContractTemplate::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'contract_type' => 'nullable|string|max:64',
            'header' => 'nullable|string',
            'body' => 'required|string',
            'footer' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $template->update($data);

        return back()->with('message', 'Template updated.');
    }
}
