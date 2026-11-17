<?php

namespace App\Payroll;

class CameroonPayrollRules
{
    /**
     * Rules cited to the 2026 General Tax Code and CNPS decree 2016/072,
     * as republished by the DGI annex and the Ministry of Finance.
     * Rows marked needs_validation are schedules taken from payroll guides,
     * not from a page of the Code that was opened line by line.
     */
    public static function twentyTwentySix()
    {
        return [
            'name' => 'Cameroon payroll 2026',
            'effective_from' => '2026-01-01',
            'cnps_employee_rate' => 0.042,
            'cnps_employer_pension_rate' => 0.042,
            'cnps_family_rate' => 0.07,
            'cnps_accident_rate' => 0.0175,
            'cnps_ceiling' => 750000,
            'irpp_monthly_threshold' => 62000,
            'professional_expense_rate' => 0.30,
            'professional_expense_cap' => 4800000,
            'fixed_abatement' => 500000,
            'cac_rate' => 0.10,
            'cfc_employee_rate' => 0.01,
            'cfc_employer_rate' => 0.015,
            'cfc_round_down_thousand' => true,
            'fne_employer_rate' => 0.01,
            'irpp_brackets' => [
                ['up_to' => 2000000, 'rate' => 0.10],
                ['up_to' => 3000000, 'rate' => 0.15],
                ['up_to' => 5000000, 'rate' => 0.25],
                ['up_to' => null, 'rate' => 0.35],
            ],
            'rav' => [
                ['up_to' => 50000, 'amount' => 0],
                ['up_to' => 100000, 'amount' => 750],
                ['up_to' => 200000, 'amount' => 1950],
                ['up_to' => 300000, 'amount' => 3250],
                ['up_to' => 400000, 'amount' => 4550],
                ['up_to' => 500000, 'amount' => 5850],
                ['up_to' => 600000, 'amount' => 7150],
                ['up_to' => 700000, 'amount' => 8450],
                ['up_to' => 800000, 'amount' => 9750],
                ['up_to' => 900000, 'amount' => 11050],
                ['up_to' => 1000000, 'amount' => 12350],
                ['up_to' => 999999999, 'amount' => 13000],
            ],
            'tdl' => [
                ['up_to' => 61999, 'amount' => 0],
                ['up_to' => 75000, 'amount' => 3000],
                ['up_to' => 100000, 'amount' => 6000],
                ['up_to' => 125000, 'amount' => 9000],
                ['up_to' => 150000, 'amount' => 12000],
                ['up_to' => 200000, 'amount' => 15000],
                ['up_to' => 250000, 'amount' => 18000],
                ['up_to' => 300000, 'amount' => 24000],
                ['up_to' => 500000, 'amount' => 27000],
                ['up_to' => 999999999, 'amount' => 30000],
            ],
            'sources' => [
                'CNPS pension 4.2% employee and employer, family 7%, ceiling 750,000 XAF: decree 2016/072 of 15 February 2016, annexed to the 2026 General Tax Code.',
                'Work-accident 1.75% is the low-risk group. Confirm the company risk group.',
                'IRPP brackets, 30% expenses capped at 4,800,000, 500,000 abatement, CAC 10%, threshold 62,000: General Tax Code 2026, articles 69, 34, 29 and 81.',
                'CFC 1% employee and 1.5% employer, FNE 1% employer: law 77/10 as reproduced in the 2026 Code, and the Ministry of Finance note on payroll withholdings.',
                'RAV monthly amounts follow the published wage bands used on 2026 payslips. Confirm the CRTV band before the first live run.',
                'TDL annual amounts follow General Tax Code 2026, article C 86, withheld as one twelfth.',
                'The transport allowance is excluded from the CNPS base. Housing, meal, responsibility and communication allowances stay in the CNPS and IRPP bases until an administrator changes the category.',
            ],
            'needs_validation' => [
                'rav',
                'cfc_round_down_thousand',
                'cnps_accident_rate',
                'transport_excluded_from_cnps',
            ],
        ];
    }

    public static function categories()
    {
        return [
            ['code' => 'technical_director', 'name' => 'Technical Director', 'components' => self::split(50, 20, 8, 5, 12, 5)],
            ['code' => 'general_manager', 'name' => 'General Manager', 'components' => self::split(50, 18, 8, 5, 14, 5)],
            ['code' => 'department_manager', 'name' => 'Department Manager', 'components' => self::split(55, 18, 8, 6, 8, 5)],
            ['code' => 'senior_engineer', 'name' => 'Senior Engineer', 'components' => self::split(60, 15, 10, 6, 5, 4)],
            ['code' => 'engineer', 'name' => 'Engineer / Technician', 'components' => self::split(65, 15, 10, 5, 3, 2)],
            ['code' => 'admin_officer', 'name' => 'Administrative Officer', 'components' => self::split(65, 15, 10, 5, 3, 2)],
            ['code' => 'accounts', 'name' => 'Accounts / Finance Officer', 'components' => self::split(65, 15, 10, 5, 3, 2)],
            ['code' => 'general_staff', 'name' => 'General Staff', 'components' => self::split(70, 15, 10, 5, 0, 0)],
            ['code' => 'intern', 'name' => 'Intern / Trainee', 'components' => self::split(80, 10, 10, 0, 0, 0)],
        ];
    }

    protected static function split($basic, $housing, $transport, $meal, $responsibility, $communication)
    {
        return [
            self::part('basic', 'Basic salary', 'Salaire de base', $basic, true, true),
            self::part('housing', 'Housing allowance', 'Indemnité de logement', $housing, true, true),
            self::part('transport', 'Transport allowance', 'Indemnité de transport', $transport, false, true),
            self::part('meal', 'Meal allowance', 'Indemnité de repas', $meal, true, true),
            self::part('responsibility', 'Responsibility allowance', 'Indemnité de responsabilité', $responsibility, true, true),
            self::part('communication', 'Communication allowance', 'Indemnité de communication', $communication, true, true),
        ];
    }

    protected static function part($code, $label, $labelFr, $percent, $cnps, $irpp)
    {
        return [
            'code' => $code,
            'label' => $label,
            'label_fr' => $labelFr,
            'percent' => $percent,
            'cnps' => $cnps,
            'irpp' => $irpp,
        ];
    }
}
