<?php

namespace App\Payroll;

/**
 * Cameroon monthly payroll, gross to net and net to gross.
 * Rates come from the effective-dated rule record. This class does not invent them.
 */
class CameroonPayrollCalculator
{
    /**
     * @param  float  $targetNet
     * @param  array  $components  [['code','label','percent','cnps','irpp'], ...] percents of gross
     * @param  array  $rules
     * @param  array  $extras  overtime, leave_pay, advance, other_deduction
     * @return array
     */
    public function solve($targetNet, array $components, array $rules, array $extras = [])
    {
        $targetNet = (int) round((float) $targetNet);
        if ($targetNet < 1) {
            throw new \InvalidArgumentException('Enter a take-home salary above zero.');
        }
        $components = $this->normalizeComponents($components);
        $low = $targetNet;
        $high = (int) max($targetNet + 1, round($targetNet * 1.15));
        $guard = 0;
        while ($this->grossToNet($high, $components, $rules, $extras)['net'] < $targetNet && $guard < 20) {
            $high = (int) round($high * 1.25);
            $guard++;
        }
        $best = $this->grossToNet($high, $components, $rules, $extras);
        while ($low <= $high) {
            $mid = (int) floor(($low + $high) / 2);
            $row = $this->grossToNet($mid, $components, $rules, $extras);
            if ($row['net'] >= $targetNet) {
                $best = $row;
                $high = $mid - 1;
            } else {
                $low = $mid + 1;
            }
        }
        $best['target_net'] = $targetNet;
        $best['net_gap'] = $best['net'] - $targetNet;

        return $best;
    }

    public function grossToNet($gross, array $components, array $rules, array $extras = [])
    {
        $gross = (int) round((float) $gross);
        $components = $this->normalizeComponents($components);
        $overtime = (int) round(isset($extras['overtime']) ? $extras['overtime'] : 0);
        $leavePay = (int) round(isset($extras['leave_pay']) ? $extras['leave_pay'] : 0);
        $advance = (int) round(isset($extras['advance']) ? $extras['advance'] : 0);
        $otherDeduction = (int) round(isset($extras['other_deduction']) ? $extras['other_deduction'] : 0);
        $earningsGross = $gross + $overtime + $leavePay;

        $lines = [];
        $allocated = 0;
        $count = count($components);
        foreach ($components as $index => $component) {
            if ($index === $count - 1) {
                $amount = $gross - $allocated;
            } else {
                $amount = (int) round($gross * ((float) $component['percent'] / 100));
                $allocated += $amount;
            }
            $lines[] = [
                'code' => $component['code'],
                'label' => $component['label'],
                'label_fr' => isset($component['label_fr']) ? $component['label_fr'] : $component['label'],
                'amount' => $amount,
                'cnps' => ! empty($component['cnps']),
                'irpp' => ! empty($component['irpp']),
            ];
        }

        $cnpsEarnings = $overtime + $leavePay;
        $irppEarnings = $overtime + $leavePay;
        foreach ($lines as $line) {
            if ($line['cnps']) {
                $cnpsEarnings += $line['amount'];
            }
            if ($line['irpp']) {
                $irppEarnings += $line['amount'];
            }
        }

        $ceiling = (int) $rules['cnps_ceiling'];
        $cnpsBase = min(max(0, $cnpsEarnings), $ceiling);
        $cnpsEmployee = (int) round($cnpsBase * (float) $rules['cnps_employee_rate']);

        $basic = 0;
        foreach ($lines as $line) {
            if ($line['code'] === 'basic') {
                $basic = $line['amount'];
            }
        }

        $tax = $this->monthlyTax($irppEarnings, $cnpsEmployee, $rules);
        $cfcBase = $earningsGross;
        if (! empty($rules['cfc_round_down_thousand'])) {
            $cfcBase = (int) (floor($earningsGross / 1000) * 1000);
        }
        $cfcEmployee = (int) round($cfcBase * (float) $rules['cfc_employee_rate']);
        $rav = $this->bracketAmount($earningsGross, $rules['rav']);
        $tdlAnnual = $this->bracketAmount($basic, $rules['tdl']);
        $tdl = (int) round($tdlAnnual / 12);

        $statutory = $cnpsEmployee + $tax['irpp'] + $tax['cac'] + $cfcEmployee + $rav + $tdl;
        $deductions = $statutory + $advance + $otherDeduction;
        $net = $earningsGross - $deductions;

        $familyRate = (float) $rules['cnps_family_rate'];
        $accidentRate = (float) $rules['cnps_accident_rate'];
        $employerCnpsPension = (int) round($cnpsBase * (float) $rules['cnps_employer_pension_rate']);
        $employerFamily = (int) round($cnpsBase * $familyRate);
        $employerAccident = (int) round($earningsGross * $accidentRate);
        $employerCfc = (int) round($earningsGross * (float) $rules['cfc_employer_rate']);
        $employerFne = (int) round($earningsGross * (float) $rules['fne_employer_rate']);
        $employerTotal = $employerCnpsPension + $employerFamily + $employerAccident + $employerCfc + $employerFne;

        return [
            'gross' => $earningsGross,
            'contract_gross' => $gross,
            'basic' => $basic,
            'earnings' => $lines,
            'overtime' => $overtime,
            'leave_pay' => $leavePay,
            'cnps_base' => $cnpsBase,
            'cnps_employee' => $cnpsEmployee,
            'taxable_annual' => $tax['taxable_annual'],
            'irpp' => $tax['irpp'],
            'cac' => $tax['cac'],
            'cfc_employee' => $cfcEmployee,
            'rav' => $rav,
            'tdl' => $tdl,
            'advance' => $advance,
            'other_deduction' => $otherDeduction,
            'total_deductions' => $deductions,
            'net' => $net,
            'employer_cnps_pension' => $employerCnpsPension,
            'employer_family' => $employerFamily,
            'employer_accident' => $employerAccident,
            'employer_cfc' => $employerCfc,
            'employer_fne' => $employerFne,
            'employer_cost' => $earningsGross + $employerTotal,
            'employer_contributions' => $employerTotal,
        ];
    }

    public function normalizeComponents(array $components)
    {
        $clean = [];
        $total = 0;
        foreach ($components as $component) {
            $percent = (float) (isset($component['percent']) ? $component['percent'] : 0);
            if ($percent <= 0) {
                continue;
            }
            $clean[] = $component;
            $total += $percent;
        }
        if ($total <= 0) {
            throw new \InvalidArgumentException('The salary category needs at least one earnings component.');
        }
        foreach ($clean as $index => $component) {
            $clean[$index]['percent'] = round(((float) $component['percent'] / $total) * 100, 4);
        }

        return array_values($clean);
    }

    protected function monthlyTax($irppEarnings, $cnpsEmployee, array $rules)
    {
        $threshold = (int) $rules['irpp_monthly_threshold'];
        if ($irppEarnings < $threshold) {
            return ['taxable_annual' => 0, 'irpp' => 0, 'cac' => 0];
        }
        $annual = $irppEarnings * 12;
        $expenses = min($annual * (float) $rules['professional_expense_rate'], (int) $rules['professional_expense_cap']);
        $taxable = $annual - $expenses - ($cnpsEmployee * 12) - (int) $rules['fixed_abatement'];
        if ($taxable < 0) {
            $taxable = 0;
        }
        $annualTax = $this->progressive($taxable, $rules['irpp_brackets']);
        $irpp = (int) round($annualTax / 12);
        $cac = (int) round($irpp * (float) $rules['cac_rate']);

        return [
            'taxable_annual' => (int) round($taxable),
            'irpp' => $irpp,
            'cac' => $cac,
        ];
    }

    protected function progressive($amount, array $brackets)
    {
        $tax = 0;
        $previous = 0;
        foreach ($brackets as $bracket) {
            $cap = $bracket['up_to'];
            $upper = $cap === null ? $amount : min($amount, (float) $cap);
            $slice = $upper - $previous;
            if ($slice > 0) {
                $tax += $slice * (float) $bracket['rate'];
            }
            if ($cap === null || $amount <= $cap) {
                break;
            }
            $previous = (float) $cap;
        }

        return $tax;
    }

    protected function bracketAmount($amount, array $brackets)
    {
        $amount = (float) $amount;
        foreach ($brackets as $bracket) {
            if ($amount <= (float) $bracket['up_to']) {
                return (int) $bracket['amount'];
            }
        }
        $last = end($brackets);

        return $last ? (int) $last['amount'] : 0;
    }
}
