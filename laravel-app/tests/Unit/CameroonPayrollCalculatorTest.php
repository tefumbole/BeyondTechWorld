<?php

namespace Tests\Unit;

use App\Payroll\CameroonPayrollCalculator;
use App\Payroll\CameroonPayrollRules;
use Tests\TestCase;

class CameroonPayrollCalculatorTest extends TestCase
{
    public function test_technical_director_net_matches_the_target_within_one_franc()
    {
        $rules = CameroonPayrollRules::twentyTwentySix();
        $category = CameroonPayrollRules::categories()[0];
        $calc = new CameroonPayrollCalculator();
        $row = $calc->solve(1498000, $category['components'], $rules);

        $this->assertLessThanOrEqual(1, abs($row['net'] - 1498000));
        $this->assertGreaterThan(1498000, $row['gross']);
        $this->assertSame(31500, $row['cnps_employee']);
        $this->assertGreaterThan(0, $row['irpp']);
        $this->assertSame((int) round($row['irpp'] * 0.10), $row['cac']);
        $check = $calc->grossToNet($row['contract_gross'], $category['components'], $rules);
        $this->assertSame($row['net'], $check['net']);
    }

    public function test_cnps_ceiling_caps_the_employee_pension()
    {
        $rules = CameroonPayrollRules::twentyTwentySix();
        $category = CameroonPayrollRules::categories()[7];
        $calc = new CameroonPayrollCalculator();
        $row = $calc->grossToNet(900000, $category['components'], $rules);

        $this->assertSame(750000, $row['cnps_base']);
        $this->assertSame(31500, $row['cnps_employee']);
    }

    public function test_income_below_the_threshold_has_no_irpp()
    {
        $rules = CameroonPayrollRules::twentyTwentySix();
        $category = CameroonPayrollRules::categories()[8];
        $calc = new CameroonPayrollCalculator();
        $row = $calc->grossToNet(50000, $category['components'], $rules);

        $this->assertSame(0, $row['irpp']);
        $this->assertSame(0, $row['cac']);
        $this->assertSame(0, $row['tdl']);
    }

    public function test_every_category_can_solve_a_net_salary()
    {
        $rules = CameroonPayrollRules::twentyTwentySix();
        $calc = new CameroonPayrollCalculator();
        foreach (CameroonPayrollRules::categories() as $category) {
            $row = $calc->solve(250000, $category['components'], $rules);
            $this->assertLessThanOrEqual(1, abs($row['net'] - 250000), $category['code']);
            $sum = 0;
            foreach ($row['earnings'] as $earning) {
                $sum += $earning['amount'];
            }
            $this->assertSame($row['contract_gross'], $sum, $category['code']);
        }
    }

    public function test_transport_is_left_out_of_the_cnps_base()
    {
        $rules = CameroonPayrollRules::twentyTwentySix();
        $category = CameroonPayrollRules::categories()[0];
        $calc = new CameroonPayrollCalculator();
        $row = $calc->grossToNet(400000, $category['components'], $rules);
        $transport = 0;
        foreach ($row['earnings'] as $earning) {
            if ($earning['code'] === 'transport') {
                $transport = $earning['amount'];
            }
        }
        $this->assertGreaterThan(0, $transport);
        $this->assertSame(400000 - $transport, $row['cnps_base']);
    }
}
