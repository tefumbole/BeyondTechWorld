<?php

namespace App\Http\Controllers;

use App\Employee;
use App\Services\Payroll\CameroonPayrollService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

class CameroonPayrollController extends Controller
{
    protected $payroll;

    public function __construct(CameroonPayrollService $payroll)
    {
        $this->middleware('auth')->except(['verify']);
        $this->payroll = $payroll;
    }

    public function index()
    {
        $this->guardPrepare();
        $runs = $this->payroll->scopedRuns()->orderBy('id', 'desc')->limit(8)->get();
        $profiles = $this->payroll->profiles();

        return view('payroll.cm.dashboard', [
            'runs' => $runs,
            'profileCount' => $profiles->where('is_active', 1)->count(),
            'rules' => $this->payroll->rulesFor(date('Y-m')),
        ]);
    }

    public function profiles()
    {
        $this->guardPrepare();

        return view('payroll.cm.profiles', [
            'profiles' => $this->payroll->profiles(),
            'employees' => Employee::where('is_active', 1)->orderBy('name')->get(),
            'categories' => $this->payroll->categories(),
            'people' => $this->payroll->employeeMap(),
            'breakdowns' => $this->payroll->profileBreakdowns(),
        ]);
    }

    public function saveProfile(Request $request)
    {
        $this->guardPrepare();
        try {
            $this->payroll->saveProfile($request->all());
        } catch (\InvalidArgumentException $e) {
            return back()->with('not_permitted', $e->getMessage())->withInput();
        }

        return redirect()->route('hr.payroll.profiles')->with('message', 'Salary profile saved.');
    }

    public function categories()
    {
        $this->guardPrepare();

        return view('payroll.cm.categories', ['categories' => $this->payroll->categories()]);
    }

    public function saveCategory(Request $request)
    {
        $this->guardPrepare();
        $components = [];
        $labels = (array) $request->input('label', []);
        $french = (array) $request->input('label_fr', []);
        $codes = (array) $request->input('code', []);
        $percents = (array) $request->input('percent', []);
        $cnps = (array) $request->input('cnps', []);
        $irpp = (array) $request->input('irpp', []);
        foreach ($labels as $index => $label) {
            $components[] = [
                'code' => isset($codes[$index]) ? $codes[$index] : 'part',
                'label' => $label,
                'label_fr' => isset($french[$index]) ? $french[$index] : $label,
                'percent' => isset($percents[$index]) ? $percents[$index] : 0,
                'cnps' => ! empty($cnps[$index]),
                'irpp' => ! empty($irpp[$index]),
            ];
        }
        try {
            $this->payroll->saveCategory($request->input('id'), $request->input('name'), $components, $request->input('is_active', 1));
        } catch (\InvalidArgumentException $e) {
            return back()->with('not_permitted', $e->getMessage());
        }

        return redirect()->route('hr.payroll.categories')->with('message', 'Salary category saved.');
    }

    public function generate()
    {
        $this->guardPrepare();

        return view('payroll.cm.generate', [
            'profiles' => $this->payroll->profiles()->where('is_active', 1),
            'people' => $this->payroll->employeeMap(),
            'categories' => $this->payroll->categories()->keyBy('id'),
            'period' => date('Y-m'),
        ]);
    }

    public function storeRun(Request $request)
    {
        $this->guardPrepare();
        try {
            $id = $this->payroll->generate($request->input('period'), (array) $request->input('profiles', []));
        } catch (\InvalidArgumentException $e) {
            return back()->with('not_permitted', $e->getMessage());
        }

        return redirect()->route('hr.payroll.show', $id)->with('message', 'Payroll calculated.');
    }

    public function show($id)
    {
        $this->guardPrepare();
        $run = $this->payroll->findRun($id);

        return view('payroll.cm.show', [
            'run' => $run,
            'lines' => $this->payroll->lines($run->id),
            'canApprove' => $this->canApprove(),
        ]);
    }

    public function recalculate(Request $request, $id)
    {
        $this->guardPrepare();
        $extras = [];
        foreach ((array) $request->input('line', []) as $lineId => $row) {
            $extras[$lineId] = $row;
        }
        try {
            $this->payroll->recalculate($id, $extras);
        } catch (\InvalidArgumentException $e) {
            return back()->with('not_permitted', $e->getMessage());
        }

        return back()->with('message', 'Payroll recalculated.');
    }

    public function transition(Request $request, $id)
    {
        $status = (string) $request->input('status');
        if ($status === 'approved') {
            $this->guardApprove();
        } else {
            $this->guardPrepare();
        }
        try {
            $this->payroll->transition($id, $status);
        } catch (\InvalidArgumentException $e) {
            return back()->with('not_permitted', $e->getMessage());
        }

        return back()->with('message', 'Payroll marked '.$status.'.');
    }

    public function revise($id)
    {
        $this->guardApprove();
        try {
            $newId = $this->payroll->revise($id);
        } catch (\InvalidArgumentException $e) {
            return back()->with('not_permitted', $e->getMessage());
        }

        return redirect()->route('hr.payroll.show', $newId)->with('message', 'A revision was opened. The approved payroll was not changed.');
    }

    public function history()
    {
        $this->guardPrepare();

        return view('payroll.cm.history', [
            'runs' => $this->payroll->scopedRuns()->orderBy('period', 'desc')->orderBy('id', 'desc')->get(),
        ]);
    }

    public function reports(Request $request)
    {
        $this->guardPrepare();
        $period = $request->input('period', date('Y-m'));

        return view('payroll.cm.reports', [
            'period' => $period,
            'rows' => $this->payroll->report($period),
        ]);
    }

    public function exportExcel(Request $request)
    {
        $this->guardPrepare();
        $period = $request->input('period', date('Y-m'));
        $rows = [];
        foreach ($this->payroll->report($period) as $row) {
            $calc = $row['calculation'];
            $rows[] = [
                $row['employee'],
                $row['department'],
                $row['category'],
                $row['payslip_no'],
                $row['run_status'],
                $calc['gross'],
                $calc['cnps_employee'],
                $calc['irpp'],
                $calc['cac'],
                $calc['total_deductions'],
                $calc['net'],
                $calc['employer_contributions'],
                $calc['employer_cost'],
            ];
        }

        return Excel::download(new \App\Payroll\PayrollReportExport($rows), 'payroll-'.$period.'.xlsx');
    }

    public function exportPdf(Request $request)
    {
        $this->guardPrepare();
        $period = $request->input('period', date('Y-m'));
        $pdf = \PDF::loadView('payroll.cm.report_pdf', [
            'period' => $period,
            'rows' => $this->payroll->report($period),
            'company' => $this->payroll->company(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('payroll-'.$period.'.pdf');
    }

    public function settings()
    {
        $this->guardPrepare();

        return view('payroll.cm.settings', [
            'settings' => $this->payroll->settings(),
            'rules' => $this->payroll->rulesFor(date('Y-m')),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $this->guardPrepare();
        $rate = (float) $request->input('accident_rate');
        $allowed = [0.0175, 0.025, 0.05];
        $matched = false;
        foreach ($allowed as $candidate) {
            if (abs($rate - $candidate) < 0.0001) {
                $matched = true;
                $rate = $candidate;
            }
        }
        if (! $matched) {
            return back()->with('not_permitted', 'Choose a CNPS work-accident rate of 1.75%, 2.5% or 5%.');
        }
        $this->payroll->saveSettings([
            'niu' => $request->input('niu'),
            'cnps_employer_no' => $request->input('cnps_employer_no'),
            'company_address' => $request->input('company_address'),
            'accident_rate' => $rate,
        ]);

        return back()->with('message', 'Payroll settings saved.');
    }

    public function payslip($id)
    {
        $line = DB::table('cm_payroll_lines')->where('id', $id)->first();
        if (! $line) {
            abort(404);
        }
        $run = DB::table('cm_payroll_runs')->where('id', $line->run_id)->first();
        if (! $this->canSeeLine($line, $run)) {
            abort(403);
        }
        $verify = url('/payroll/verify/'.$line->verification_code);
        $qr = '';
        try {
            $qr = \App\Support\OnlineInvitationQr::dataUri($verify, 140, 1);
        } catch (\Throwable $e) {
            $qr = '';
        }
        $pdf = \PDF::loadView('pdf.cameroon_payslip', [
            'line' => $line,
            'run' => $run,
            'snap' => json_decode($line->snapshot_json, true),
            'company' => $this->payroll->company(),
            'verify' => $verify,
            'qr' => $qr,
        ])->setPaper('a5', 'portrait');

        return $pdf->download($line->payslip_no.'.pdf');
    }

    public function mine()
    {
        $employeeIds = DB::table('employees')->where('user_id', Auth::id())->pluck('id');
        $lines = DB::table('cm_payroll_lines')
            ->whereIn('employee_id', $employeeIds)
            ->orderBy('id', 'desc')
            ->get();
        $visible = [];
        foreach ($lines as $line) {
            $run = DB::table('cm_payroll_runs')->where('id', $line->run_id)->first();
            if ($run && in_array($run->status, ['approved', 'paid'], true)) {
                $line->period = $run->period;
                $line->status = $run->status;
                $visible[] = $line;
            }
        }

        return view('payroll.cm.mine', ['lines' => $visible]);
    }

    public function verify($code)
    {
        $line = DB::table('cm_payroll_lines')->where('verification_code', $code)->first();
        if (! $line) {
            abort(404);
        }
        $run = DB::table('cm_payroll_runs')->where('id', $line->run_id)->first();
        $snap = json_decode($line->snapshot_json, true);

        return view('payroll.cm.verify', [
            'line' => $line,
            'run' => $run,
            'name' => isset($snap['employee']) ? $snap['employee'] : '',
            'company' => $this->payroll->company(),
        ]);
    }

    protected function canSeeLine($line, $run)
    {
        if ($this->hasPayroll()) {
            return true;
        }
        $owns = DB::table('employees')->where('user_id', Auth::id())->where('id', $line->employee_id)->exists();

        return $owns && $run && in_array($run->status, ['approved', 'paid'], true);
    }

    protected function guardPrepare()
    {
        if (! $this->hasPayroll()) {
            abort(403);
        }
    }

    protected function guardApprove()
    {
        if (! $this->canApprove()) {
            abort(403);
        }
    }

    protected function hasPayroll()
    {
        $role = Role::find(Auth::user()->role_id);

        return $role && $role->hasPermissionTo('payroll');
    }

    protected function canApprove()
    {
        $role = Role::find(Auth::user()->role_id);

        return $role && $role->hasPermissionTo('payroll-approve');
    }
}
