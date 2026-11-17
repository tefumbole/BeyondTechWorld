<?php

namespace App\Services\Payroll;

use App\Department;
use App\Employee;
use App\Payroll\CameroonPayrollCalculator;
use App\Payroll\CameroonPayrollRules;
use App\Payroll\PayrollAmountWords;
use App\Services\Cloud\CloudTenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CameroonPayrollService
{
    public function tenantId()
    {
        return app(CloudTenantContext::class)->id();
    }

    public function scopedRuns()
    {
        return $this->scoped('cm_payroll_runs');
    }

    public function rulesFor($period)
    {
        $date = $period.'-01';
        $row = DB::table('cm_payroll_rules')
            ->where('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->orderBy('effective_from', 'desc')
            ->first();
        if (! $row) {
            $fallback = CameroonPayrollRules::twentyTwentySix();

            return $fallback;
        }
        $rules = json_decode($row->rules_json, true);
        $rules['id'] = $row->id;
        $settings = $this->settings();
        if ($settings && $settings->accident_rate !== null) {
            $rules['cnps_accident_rate'] = (float) $settings->accident_rate;
        }

        return $rules;
    }

    public function settings()
    {
        $tenant = $this->tenantId();
        $query = DB::table('cm_payroll_settings');
        if ($tenant) {
            $query->where('cloud_tenant_id', $tenant);
        } else {
            $query->whereNull('cloud_tenant_id');
        }

        return $query->first();
    }

    public function saveSettings(array $data)
    {
        $tenant = $this->tenantId();
        $existing = $this->settings();
        $payload = [
            'niu' => trim((string) $data['niu']),
            'cnps_employer_no' => trim((string) $data['cnps_employer_no']),
            'company_address' => trim((string) $data['company_address']),
            'accident_rate' => (float) $data['accident_rate'],
            'updated_at' => now(),
        ];
        if ($existing) {
            DB::table('cm_payroll_settings')->where('id', $existing->id)->update($payload);
            $id = $existing->id;
        } else {
            $payload['cloud_tenant_id'] = $tenant;
            $payload['created_at'] = now();
            $id = DB::table('cm_payroll_settings')->insertGetId($payload);
        }
        $this->audit('settings', 'settings', $id, 'Payroll settings saved');

        return $id;
    }

    public function categories()
    {
        return $this->scoped('cm_salary_categories')->orderBy('name')->get();
    }

    public function saveCategory($id, $name, array $components, $active)
    {
        $clean = [];
        foreach ($components as $component) {
            $percent = (float) (isset($component['percent']) ? $component['percent'] : 0);
            if ($percent <= 0 || trim((string) $component['label']) === '') {
                continue;
            }
            $clean[] = [
                'code' => preg_replace('/[^a-z0-9_]/', '', strtolower((string) $component['code'])) ?: 'part',
                'label' => trim((string) $component['label']),
                'label_fr' => trim((string) (isset($component['label_fr']) ? $component['label_fr'] : $component['label'])),
                'percent' => $percent,
                'cnps' => ! empty($component['cnps']),
                'irpp' => ! empty($component['irpp']),
            ];
        }
        if (count($clean) < 1) {
            throw new \InvalidArgumentException('Add at least one salary component.');
        }
        $payload = [
            'name' => trim($name),
            'components_json' => json_encode($clean),
            'is_active' => $active ? 1 : 0,
            'updated_at' => now(),
        ];
        if ($id) {
            $this->scoped('cm_salary_categories')->where('id', $id)->update($payload);
        } else {
            $payload['cloud_tenant_id'] = $this->tenantId();
            $payload['code'] = substr(preg_replace('/[^a-z0-9]+/', '_', strtolower($payload['name'])), 0, 40);
            $payload['created_at'] = now();
            $id = DB::table('cm_salary_categories')->insertGetId($payload);
        }
        $this->audit('category', 'category', $id, $payload['name']);

        return $id;
    }

    public function profiles()
    {
        return $this->scoped('cm_salary_profiles')->orderBy('id', 'desc')->get();
    }

    public function saveProfile(array $data)
    {
        $employee = Employee::where('is_active', 1)->find($data['employee_id']);
        if (! $employee) {
            throw new \InvalidArgumentException('Choose an active employee.');
        }
        $category = $this->scoped('cm_salary_categories')->where('id', $data['category_id'])->where('is_active', 1)->first();
        if (! $category) {
            throw new \InvalidArgumentException('Choose an active salary category.');
        }
        $target = (int) round((float) str_replace([' ', ','], '', (string) $data['target_net']));
        if ($target < 1) {
            throw new \InvalidArgumentException('Enter the take-home salary.');
        }
        $payload = [
            'employee_id' => $employee->id,
            'category_id' => $category->id,
            'target_net' => $target,
            'cnps_number' => trim((string) $data['cnps_number']),
            'bank_name' => trim((string) $data['bank_name']),
            'bank_account' => trim((string) $data['bank_account']),
            'payment_method' => trim((string) $data['payment_method']),
            'is_active' => empty($data['is_active']) ? 0 : 1,
            'updated_at' => now(),
        ];
        $existing = $this->scoped('cm_salary_profiles')->where('employee_id', $employee->id)->first();
        if ($existing) {
            $this->scoped('cm_salary_profiles')->where('id', $existing->id)->update($payload);
            $id = $existing->id;
        } else {
            $payload['cloud_tenant_id'] = $this->tenantId();
            $payload['created_at'] = now();
            $id = DB::table('cm_salary_profiles')->insertGetId($payload);
        }
        $this->audit('profile', 'profile', $id, $employee->name.' target '.$target);

        return $id;
    }

    public function preview($profileId, $period, array $extras = [])
    {
        $profile = $this->scoped('cm_salary_profiles')->where('id', $profileId)->first();
        if (! $profile) {
            throw new \InvalidArgumentException('Salary profile not found.');
        }
        $category = DB::table('cm_salary_categories')->where('id', $profile->category_id)->first();
        $rules = $this->rulesFor($period);
        $components = json_decode($category->components_json, true);

        return (new CameroonPayrollCalculator())->solve((int) $profile->target_net, $components ?: [], $rules, $extras);
    }

    public function generate($period, array $profileIds)
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new \InvalidArgumentException('Choose a payroll month.');
        }
        $profiles = $this->scoped('cm_salary_profiles')->where('is_active', 1)->whereIn('id', $profileIds)->get();
        if ($profiles->count() < 1) {
            throw new \InvalidArgumentException('Select at least one employee.');
        }
        $rules = $this->rulesFor($period);
        if (empty($rules['id'])) {
            $rules['id'] = DB::table('cm_payroll_rules')->insertGetId([
                'name' => $rules['name'],
                'effective_from' => $rules['effective_from'],
                'rules_json' => json_encode($rules),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $runId = DB::table('cm_payroll_runs')->insertGetId([
            'cloud_tenant_id' => $this->tenantId(),
            'period' => $period,
            'title' => 'Payroll '.$period,
            'status' => 'calculated',
            'rule_id' => $rules['id'],
            'prepared_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $seq = (int) DB::table('cm_payroll_lines')->where('payslip_no', 'like', 'BE-PS-'.str_replace('-', '', $period).'-%')->count();
        foreach ($profiles as $profile) {
            $seq++;
            $this->writeLine($runId, $profile, $period, $rules, [], $seq);
        }
        $this->refreshTotals($runId);
        $this->audit('generate', 'run', $runId, $period);

        return $runId;
    }

    public function recalculate($runId, array $extrasByLine)
    {
        $run = $this->findRun($runId);
        if (! in_array($run->status, ['draft', 'calculated'], true)) {
            throw new \InvalidArgumentException('This payroll can no longer be recalculated.');
        }
        $rules = json_decode(DB::table('cm_payroll_rules')->where('id', $run->rule_id)->value('rules_json'), true);
        $rules['id'] = $run->rule_id;
        $settings = $this->settings();
        if ($settings && $settings->accident_rate !== null) {
            $rules['cnps_accident_rate'] = (float) $settings->accident_rate;
        }
        foreach (DB::table('cm_payroll_lines')->where('run_id', $runId)->get() as $line) {
            $profile = DB::table('cm_salary_profiles')->where('id', $line->profile_id)->first();
            $extra = isset($extrasByLine[$line->id]) ? $extrasByLine[$line->id] : [];
            $category = DB::table('cm_salary_categories')->where('id', $profile->category_id)->first();
            $components = json_decode($category->components_json, true);
            $solved = (new CameroonPayrollCalculator())->solve((int) $profile->target_net, $components ?: [], $rules, [
                'overtime' => isset($extra['overtime']) ? $extra['overtime'] : 0,
                'leave_pay' => isset($extra['leave_pay']) ? $extra['leave_pay'] : 0,
                'advance' => isset($extra['advance']) ? $extra['advance'] : 0,
                'other_deduction' => isset($extra['other_deduction']) ? $extra['other_deduction'] : 0,
            ]);
            DB::table('cm_payroll_lines')->where('id', $line->id)->update([
                'snapshot_json' => json_encode($this->snapshot($profile, $category, $solved, $rules)),
                'gross' => $solved['gross'],
                'net' => $solved['net'],
                'updated_at' => now(),
            ]);
        }
        $this->refreshTotals($runId);
        $this->audit('recalculate', 'run', $runId, 'Recalculated');

        return $runId;
    }

    public function transition($runId, $status)
    {
        $run = $this->findRun($runId);
        $map = [
            'submitted' => ['from' => ['calculated', 'draft'], 'column' => 'submitted'],
            'approved' => ['from' => ['submitted'], 'column' => 'approved'],
            'paid' => ['from' => ['approved'], 'column' => 'paid'],
        ];
        if (! isset($map[$status]) || ! in_array($run->status, $map[$status]['from'], true)) {
            throw new \InvalidArgumentException('This payroll cannot move to '.$status.'.');
        }
        $column = $map[$status]['column'];
        DB::table('cm_payroll_runs')->where('id', $run->id)->update([
            'status' => $status,
            $column.'_by' => Auth::id(),
            $column.'_at' => now(),
            'updated_at' => now(),
        ]);
        if ($status === 'paid') {
            DB::table('cm_payroll_lines')->where('run_id', $run->id)->update(['payment_status' => 'paid', 'updated_at' => now()]);
        }
        $this->audit($status, 'run', $run->id, $run->period);

        return $run->id;
    }

    public function revise($runId)
    {
        $run = $this->findRun($runId);
        if (! in_array($run->status, ['approved', 'paid'], true)) {
            throw new \InvalidArgumentException('Only an approved payroll can be revised.');
        }
        $newId = DB::table('cm_payroll_runs')->insertGetId([
            'cloud_tenant_id' => $run->cloud_tenant_id,
            'period' => $run->period,
            'title' => $run->title.' revision',
            'status' => 'calculated',
            'revision_of_id' => $run->id,
            'rule_id' => $run->rule_id,
            'prepared_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $rules = json_decode(DB::table('cm_payroll_rules')->where('id', $run->rule_id)->value('rules_json'), true);
        $base = (int) DB::table('cm_payroll_lines')->max('id');
        $seq = 0;
        foreach (DB::table('cm_payroll_lines')->where('run_id', $run->id)->get() as $line) {
            $seq++;
            $profile = DB::table('cm_salary_profiles')->where('id', $line->profile_id)->first();
            $this->writeLine($newId, $profile, $run->period, $rules, [], $base + $seq);
        }
        $this->refreshTotals($newId);
        $this->audit('revise', 'run', $newId, 'Revision of '.$run->id);

        return $newId;
    }

    public function findRun($id)
    {
        $run = $this->scoped('cm_payroll_runs')->where('id', $id)->first();
        if (! $run) {
            abort(404);
        }

        return $run;
    }

    public function lines($runId)
    {
        return DB::table('cm_payroll_lines')->where('run_id', $runId)->orderBy('payslip_no')->get();
    }

    public function employeeMap()
    {
        $rows = [];
        foreach (Employee::where('is_active', 1)->orderBy('name')->get() as $employee) {
            $department = $employee->department_id ? Department::find($employee->department_id) : null;
            $rows[$employee->id] = [
                'name' => $employee->name,
                'email' => $employee->email,
                'phone' => $employee->phone_number,
                'department' => $department ? $department->name : '',
                'user_id' => $employee->user_id,
            ];
        }

        return $rows;
    }

    public function report($period)
    {
        $runs = $this->scoped('cm_payroll_runs')->where('period', $period)->whereIn('status', ['approved', 'paid', 'submitted', 'calculated'])->get();
        $rows = [];
        foreach ($runs as $run) {
            foreach ($this->lines($run->id) as $line) {
                $snap = json_decode($line->snapshot_json, true);
                $rows[] = array_merge($snap, [
                    'run_status' => $run->status,
                    'payslip_no' => $line->payslip_no,
                    'net' => $line->net,
                    'gross' => $line->gross,
                ]);
            }
        }

        return $rows;
    }

    public function company()
    {
        $general = DB::table('general_settings')->latest('id')->first();
        $settings = $this->settings();
        $logo = null;
        if ($general && ! empty($general->site_logo)) {
            $file = public_path('logo/'.$general->site_logo);
            if (is_file($file)) {
                $logo = 'data:image/png;base64,'.base64_encode(file_get_contents($file));
            }
        }

        return [
            'name' => $general ? $general->site_title : 'Beyond Enterprise',
            'address' => $settings && $settings->company_address ? $settings->company_address : '',
            'niu' => $settings ? $settings->niu : '',
            'cnps' => $settings ? $settings->cnps_employer_no : '',
            'logo' => $logo,
        ];
    }

    protected function writeLine($runId, $profile, $period, array $rules, array $extras, $seq)
    {
        $category = DB::table('cm_salary_categories')->where('id', $profile->category_id)->first();
        $components = json_decode($category->components_json, true);
        $solved = (new CameroonPayrollCalculator())->solve((int) $profile->target_net, $components ?: [], $rules, $extras);
        $stamp = str_replace('-', '', $period);
        DB::table('cm_payroll_lines')->insert([
            'run_id' => $runId,
            'profile_id' => $profile->id,
            'employee_id' => $profile->employee_id,
            'payslip_no' => 'BE-PS-'.$stamp.'-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'verification_code' => strtoupper(substr(bin2hex(random_bytes(8)), 0, 12)),
            'snapshot_json' => json_encode($this->snapshot($profile, $category, $solved, $rules)),
            'gross' => $solved['gross'],
            'net' => $solved['net'],
            'payment_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function snapshot($profile, $category, array $solved, array $rules)
    {
        $people = $this->employeeMap();
        $person = isset($people[$profile->employee_id]) ? $people[$profile->employee_id] : ['name' => 'Employee', 'department' => '', 'email' => ''];
        $words = PayrollAmountWords::both($solved['net']);

        return [
            'employee' => $person['name'],
            'employee_id' => $profile->employee_id,
            'department' => $person['department'],
            'category' => $category->name,
            'cnps_number' => $profile->cnps_number,
            'bank_name' => $profile->bank_name,
            'bank_account' => $profile->bank_account,
            'payment_method' => $profile->payment_method,
            'target_net' => (int) $profile->target_net,
            'words_en' => $words['en'],
            'words_fr' => $words['fr'],
            'rules_name' => isset($rules['name']) ? $rules['name'] : 'Payroll rules',
            'calculation' => $solved,
        ];
    }

    protected function refreshTotals($runId)
    {
        $lines = DB::table('cm_payroll_lines')->where('run_id', $runId)->get();
        $gross = 0;
        $net = 0;
        $employer = 0;
        foreach ($lines as $line) {
            $gross += (int) $line->gross;
            $net += (int) $line->net;
            $snap = json_decode($line->snapshot_json, true);
            $employer += isset($snap['calculation']['employer_cost']) ? (int) $snap['calculation']['employer_cost'] : 0;
        }
        DB::table('cm_payroll_runs')->where('id', $runId)->update([
            'total_gross' => $gross,
            'total_net' => $net,
            'total_employer' => $employer,
            'updated_at' => now(),
        ]);
    }

    protected function scoped($table)
    {
        $query = DB::table($table);
        $tenant = $this->tenantId();
        if ($tenant) {
            $query->where(function ($inner) use ($tenant) {
                $inner->where('cloud_tenant_id', $tenant)->orWhereNull('cloud_tenant_id');
            });
        }

        return $query;
    }

    protected function audit($action, $type, $id, $detail)
    {
        DB::table('cm_payroll_audits')->insert([
            'cloud_tenant_id' => $this->tenantId(),
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $type,
            'subject_id' => (string) $id,
            'detail' => $detail,
            'created_at' => now(),
        ]);
    }
}
