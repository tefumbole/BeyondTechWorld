<?php

namespace App\Http\Controllers;

use App\ContractClause;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class ContractClauseController extends Controller
{
    protected $all_permission = [];

    public function __construct()
    {
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
        foreach (['contracts.clauses', 'contracts.templates', 'contracts_module'] as $n) {
            if (in_array($n, $this->all_permission, true)) {
                return;
            }
        }
        abort(403);
    }

    public function index()
    {
        $this->gate();

        return view('contracts.clauses.index', [
            'clauses' => ContractClause::orderBy('sort_order')->orderBy('title')->get(),
            'ctTab' => 'contracts.clauses',
        ]);
    }

    public function create()
    {
        $this->gate();

        return view('contracts.clauses.form', [
            'clause' => null,
            'ctTab' => 'contracts.clauses',
        ]);
    }

    public function store(Request $request)
    {
        $this->gate();
        $request->validate([
            'title' => 'required|string|max:255',
            'code' => 'required|string|max:60|unique:contract_clauses,code',
            'body_html' => 'required|string',
        ]);
        ContractClause::create([
            'id' => (string) Str::uuid(),
            'code' => strtoupper($request->code),
            'title' => $request->title,
            'category' => $request->category,
            'body_html' => $request->body_html,
            'active' => (bool) $request->get('active', 0),
            'sort_order' => (int) $request->get('sort_order', 0),
        ]);

        return redirect()->route('contracts.clauses')->with('message', 'Clause saved.');
    }

    public function edit($id)
    {
        $this->gate();

        return view('contracts.clauses.form', [
            'clause' => ContractClause::findOrFail($id),
            'ctTab' => 'contracts.clauses',
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->gate();
        $clause = ContractClause::findOrFail($id);
        $request->validate([
            'title' => 'required|string|max:255',
            'code' => 'required|string|max:60|unique:contract_clauses,code,'.$clause->id.',id',
            'body_html' => 'required|string',
        ]);
        $clause->fill([
            'code' => strtoupper($request->code),
            'title' => $request->title,
            'category' => $request->category,
            'body_html' => $request->body_html,
            'active' => (bool) $request->get('active', 0),
            'sort_order' => (int) $request->get('sort_order', 0),
        ])->save();

        return redirect()->route('contracts.clauses')->with('message', 'Clause updated.');
    }

    public function destroy($id)
    {
        $this->gate();
        ContractClause::where('id', $id)->delete();

        return back()->with('message', 'Clause deleted.');
    }

    /** JSON list for template editor insert */
    public function json()
    {
        $this->gate();

        return response()->json(
            ContractClause::active()->orderBy('sort_order')->orderBy('title')
                ->get(['id', 'code', 'title', 'category', 'body_html'])
        );
    }
}
