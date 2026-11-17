<?php

namespace App\Payroll;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PayrollReportExport implements FromArray, WithHeadings
{
    protected $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function headings(): array
    {
        return ['Employee', 'Department', 'Category', 'Payslip', 'Status', 'Gross', 'CNPS employee', 'IRPP', 'CAC', 'Deductions', 'Net', 'Employer contributions', 'Employer cost'];
    }

    public function array(): array
    {
        return $this->rows;
    }
}
