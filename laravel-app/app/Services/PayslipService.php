<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PayslipService
{
    /**
     * Look up a payslip by its verification code (or payroll item id) and return
     * the public-safe verification payload. Mirrors the legacy
     * GET /api/hr/payslips/verify/:code endpoint.
     *
     * @return array|null
     */
    public function verify($code)
    {
        $code = strtoupper(trim((string) $code));

        $row = DB::table('hr_payslips as ps')
            ->join('hr_payroll_items as pi', 'pi.id', '=', 'ps.payroll_item_id')
            ->join('hr_staff_profiles as sp', 'sp.id', '=', 'pi.staff_profile_id')
            ->join('hr_payroll_runs as pr', 'pr.id', '=', 'pi.payroll_run_id')
            ->where(function ($q) use ($code) {
                $q->whereRaw('UPPER(ps.verification_code) = ?', [$code])
                  ->orWhere('ps.payroll_item_id', $code);
            })
            ->selectRaw('sp.first_name, sp.last_name, sp.staff_code, sp.position, sp.hire_date,
                pr.title as payroll_title, pi.net_amount, ps.verification_code, ps.generated_at')
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'valid' => true,
            'employee_name' => trim($row->first_name.' '.$row->last_name),
            'staff_code' => $row->staff_code,
            'position' => $row->position,
            'hire_date' => $row->hire_date,
            'payroll_title' => $row->payroll_title,
            'net_amount' => $row->net_amount,
            'verification_code' => $row->verification_code,
            'generated_at' => $row->generated_at,
        ];
    }
}
