<?php

namespace App\Services\WhatsApp;

class WhatsAppDocumentRegistry
{
    public function all()
    {
        return [
            'CUSTOMER_QUOTATION' => $this->row('Quotation', 'customer', 'CUSTOMER_DOCUMENTS', true, 'QuotationController::buildQuotationPdf'),
            'CUSTOMER_INVOICE' => $this->row('Invoice', 'customer', 'CUSTOMER_DOCUMENTS', true, 'SaleController::buildSaleInvoicePdfBinary'),
            'CUSTOMER_RECEIPT' => $this->row('Receipt', 'customer', 'CUSTOMER_DOCUMENTS', false, null),
            'CUSTOMER_CONTRACT' => $this->row('Contract', 'customer', 'CUSTOMER_DOCUMENTS', false, null),
            'EMPLOYEE_PAYSLIP' => $this->row('Payslip', 'employee', 'EMPLOYEE_DOCUMENTS', false, null),
            'EMPLOYEE_CONTRACT' => $this->row('Employment contract', 'employee', 'EMPLOYEE_DOCUMENTS', false, null),
            'EMPLOYEE_TIMESHEET' => $this->row('Timesheet', 'employee', 'EMPLOYEE_DOCUMENTS', false, null),
            'EMPLOYEE_MISSION_ORDER' => $this->row('Mission order', 'employee', 'EMPLOYEE_DOCUMENTS', false, null),
            'INTERNSHIP_LETTER' => $this->row('Internship letter', 'intern', 'INTERN_DOCUMENTS', false, null),
            'INTERNSHIP_ASSESSMENT' => $this->row('Assessment', 'intern', 'INTERN_DOCUMENTS', false, null),
            'INTERNSHIP_CERTIFICATE' => $this->row('Certificate', 'intern', 'INTERN_DOCUMENTS', false, null),
        ];
    }

    public function get($key)
    {
        $all = $this->all();

        return isset($all[$key]) ? $all[$key] : null;
    }

    public function availableFor(array $roles)
    {
        $out = [];
        foreach ($this->all() as $key => $row) {
            if (empty($row['available'])) {
                continue;
            }
            if (! in_array($row['owner'], $roles, true)) {
                continue;
            }
            $out[] = ['key' => $key, 'label' => $row['label'], 'sensitivity' => $row['sensitivity']];
        }

        return $out;
    }

    protected function row($label, $owner, $scope, $available, $generator)
    {
        return [
            'label' => $label,
            'owner' => $owner,
            'sensitivity' => 'VERIFIED',
            'scope' => $scope,
            'available' => (bool) $available,
            'otp' => true,
            'generator' => $generator,
            'multiple' => true,
            'latest' => true,
        ];
    }
}
