<?php

namespace App\Services\Property;

use App\Property\BillPaymentRequest;
use App\Property\RentObligation;
use App\Property\RentPayment;
use App\Property\Tenancy;

class TenantDocumentFileService
{
    public function make($type, $recordId)
    {
        if (config('services.whatsapp.document_fail_generation')) {
            throw new \RuntimeException('pdf failed');
        }
        if ($type === 'TENANCY_AGREEMENT') {
            $tenancy = Tenancy::find((int) $recordId);
            if (! $tenancy || ! $tenancy->agreement_path) {
                throw new \RuntimeException('agreement missing');
            }
            $real = realpath($tenancy->agreement_path);
            if (! $real) {
                throw new \RuntimeException('agreement missing');
            }

            return ['path' => $real, 'name' => 'tenancy-agreement-'.$tenancy->id.'.pdf'];
        }
        if (config('services.whatsapp.document_use_fixtures')) {
            return ['path' => $this->fixture(strtolower($type).'-'.$recordId.'.pdf'), 'name' => strtolower($type).'-'.$recordId.'.pdf'];
        }
        if ($type === 'RENT_RECEIPT') {
            $payment = RentPayment::find((int) $recordId);
            if (! $payment) {
                throw new \RuntimeException('receipt missing');
            }
            $html = view('pdf.property_rent_receipt', ['payment' => $payment])->render();

            return $this->write('rent-receipt-'.$payment->id.'.pdf', $html);
        }
        if ($type === 'RENT_STATEMENT') {
            $tenancy = Tenancy::find((int) $recordId);
            if (! $tenancy) {
                throw new \RuntimeException('statement missing');
            }
            $rows = RentObligation::where('tenancy_id', $tenancy->id)->orderBy('due_date')->get();
            $payments = RentPayment::where('tenancy_id', $tenancy->id)->orderBy('id')->get();
            $html = view('pdf.property_rent_statement', [
                'tenancy' => $tenancy,
                'rows' => $rows,
                'payments' => $payments,
            ])->render();

            return $this->write('rent-statement-'.$tenancy->id.'.pdf', $html);
        }
        if ($type === 'BILL_PAYMENT_RECEIPT') {
            $bill = BillPaymentRequest::find((int) $recordId);
            if (! $bill || $bill->status !== 'PAID') {
                throw new \RuntimeException('bill not paid');
            }
            $html = view('pdf.property_bill_receipt', ['bill' => $bill])->render();

            return $this->write('bill-receipt-'.$bill->id.'.pdf', $html);
        }

        throw new \RuntimeException('unknown document');
    }

    protected function write($name, $html)
    {
        $dir = storage_path('app/property-documents');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $path = $dir.'/'.preg_replace('/[^A-Za-z0-9._\-]/', '', $name);
        $pdf = \PDF::loadHTML($html)->setPaper('A4', 'portrait');
        $pdf->save($path);

        return ['path' => $path, 'name' => basename($path)];
    }

    protected function fixture($name)
    {
        $dir = storage_path('app/property-documents');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $path = $dir.'/'.preg_replace('/[^A-Za-z0-9._\-]/', '', $name);
        if (! is_file($path)) {
            file_put_contents($path, "%PDF-1.4\n");
        }

        return $path;
    }
}
