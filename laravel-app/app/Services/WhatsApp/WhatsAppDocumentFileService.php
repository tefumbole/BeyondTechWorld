<?php

namespace App\Services\WhatsApp;

use App\Http\Controllers\QuotationController;
use App\Http\Controllers\SaleController;

class WhatsAppDocumentFileService
{
    public function quotation($id)
    {
        if (config('services.whatsapp.document_fail_generation')) {
            throw new \RuntimeException('pdf failed');
        }
        if (config('services.whatsapp.document_use_fixtures')) {
            return $this->fixture('quotation-'.$id.'.pdf');
        }

        return app(QuotationController::class)->buildQuotationPdf($id);
    }

    public function invoice($id)
    {
        if (config('services.whatsapp.document_fail_generation')) {
            throw new \RuntimeException('pdf failed');
        }
        if (config('services.whatsapp.document_use_fixtures')) {
            return $this->fixture('invoice-'.$id.'.pdf');
        }
        $binary = app(SaleController::class)->buildSaleInvoicePdfBinary($id);
        $path = $this->fixture('invoice-'.$id.'-'.bin2hex(random_bytes(4)).'.pdf');
        file_put_contents($path, $binary);

        return $path;
    }

    public function assertSafe($path)
    {
        $path = (string) $path;
        if ($path === '' || preg_match('#\.\.|://#', $path)) {
            return false;
        }
        $real = realpath($path);
        if (! $real || ! is_file($real)) {
            return false;
        }
        $roots = [realpath(storage_path('app')), realpath(public_path('quotation'))];
        foreach ($roots as $root) {
            if ($root && strpos($real, $root) === 0) {
                return $real;
            }
        }

        return false;
    }

    protected function fixture($name)
    {
        $dir = storage_path('app/whatsapp-documents');
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
