<?php

namespace App\Http\Controllers\Wealth;

use App\Services\Wealth\WealthFilter;
use App\Services\Wealth\WealthProgramFinanceService;
use App\Support\WealthMoney;
use App\WealthProgram;
use App\WealthProgramBudgetLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WealthProgramController extends WealthBaseController
{
    public function index(Request $request, WealthProgramFinanceService $finance)
    {
        if (! $this->can('wealth.programs.view')) {
            return $this->deny();
        }
        $filter = WealthFilter::fromRequest($request);
        $cards = [];
        foreach (WealthProgram::orderByDesc('id')->get() as $program) {
            $cards[] = ['program' => $program, 'finance' => $finance->forProgram($program, $filter)];
        }

        return view('wealth.programs', $this->lookups() + [
            'wmTab' => 'programs',
            'filter' => $filter,
            'cards' => $cards,
        ]);
    }

    public function store(Request $request)
    {
        if (! $this->can('wealth.programs.manage')) {
            return $this->deny();
        }
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'code' => 'required|string|max:64',
            'biller_id' => 'nullable|integer',
            'manager_user_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'proposed_budget' => 'nullable|numeric',
            'expected_income' => 'nullable|numeric',
            'currency' => 'nullable|string|max:8',
            'status' => 'required|in:planning,active,completed,cancelled',
            'notes' => 'nullable|string',
        ]);
        $data['proposed_budget'] = WealthMoney::of($data['proposed_budget'] ?? 0);
        $data['expected_income'] = WealthMoney::of($data['expected_income'] ?? 0);
        $data['created_by'] = Auth::id();
        $data['cover_image'] = $this->storeWealthFile($request->file('cover_image'), 'covers');
        $program = WealthProgram::create($data);

        return redirect()->route('wealth.programs.show', $program->id)->with('message', 'Program created.');
    }

    public function show($id, Request $request, WealthProgramFinanceService $finance)
    {
        if (! $this->can('wealth.programs.view')) {
            return $this->deny();
        }
        $program = WealthProgram::with('budgetLines.subcategory')->findOrFail($id);
        $filter = WealthFilter::fromRequest($request);
        $fin = $finance->forProgram($program, $filter);

        return view('wealth.program_show', $this->lookups() + [
            'wmTab' => 'programs',
            'filter' => $filter,
            'program' => $program,
            'finance' => $fin,
        ]);
    }

    public function update(Request $request, $id)
    {
        if (! $this->can('wealth.programs.manage')) {
            return $this->deny();
        }
        $program = WealthProgram::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'code' => 'required|string|max:64',
            'biller_id' => 'nullable|integer',
            'manager_user_id' => 'nullable|integer',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'proposed_budget' => 'nullable|numeric',
            'expected_income' => 'nullable|numeric',
            'status' => 'required|in:planning,active,completed,cancelled',
            'notes' => 'nullable|string',
        ]);
        $data['proposed_budget'] = WealthMoney::of($data['proposed_budget'] ?? 0);
        $data['expected_income'] = WealthMoney::of($data['expected_income'] ?? 0);
        $data['updated_by'] = Auth::id();
        $program->update($data);

        return back()->with('message', 'Program updated.');
    }

    public function storeBudget(Request $request, $id)
    {
        if (! $this->can('wealth.programs.manage')) {
            return $this->deny();
        }
        $program = WealthProgram::findOrFail($id);
        $data = $request->validate([
            'name' => 'required|string|max:190',
            'budget_amount' => 'required|numeric|min:0',
            'subcategory_id' => 'nullable|integer',
            'warn_percent' => 'nullable|integer|min:1|max:100',
            'critical_percent' => 'nullable|integer|min:1|max:200',
        ]);
        $data['program_id'] = $program->id;
        $data['budget_amount'] = WealthMoney::of($data['budget_amount']);
        $data['warn_percent'] = $data['warn_percent'] ?? 85;
        $data['critical_percent'] = $data['critical_percent'] ?? 100;
        WealthProgramBudgetLine::create($data);

        return back()->with('message', 'Budget line added.');
    }

    public function destroyBudget($id)
    {
        if (! $this->can('wealth.programs.manage')) {
            return $this->deny();
        }
        WealthProgramBudgetLine::findOrFail($id)->delete();

        return back()->with('message', 'Budget line removed.');
    }
}
