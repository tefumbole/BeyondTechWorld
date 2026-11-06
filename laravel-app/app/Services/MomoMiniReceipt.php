<?php

namespace App\Services;

use App\Biller;
use App\GeneralSetting;
use App\Support\LetterSignature;
use App\Support\WhatsAppMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Beyond Mini invoice sent after a confirmed Mobile Money payment.
 */
class MomoMiniReceipt
{
    public function send(array $receipt)
    {
        if (! isset($receipt['client_phone']) || trim((string) $receipt['client_phone']) === '') {
            $receipt['client_phone'] = isset($receipt['phone']) ? $receipt['phone'] : '';
        }
        $this->deliver($receipt);
        if (! empty($receipt['skip_admin_copy'])) {
            return;
        }
        $phone = isset($receipt['phone']) ? trim((string) $receipt['phone']) : '';
        $admin = \App\Support\TwilioAdminCopy::PHONE;
        if ($admin === '' || \App\Support\TwilioAdminCopy::isAdmin($phone)) {
            return;
        }
        $copy = $receipt;
        $copy['phone'] = $admin;
        $copy['skip_admin_copy'] = true;
        $this->deliver($copy);
    }

    protected function deliver(array $receipt)
    {
        $phone = isset($receipt['phone']) ? trim((string) $receipt['phone']) : '';
        if ($phone === '') {
            return;
        }
        $reference = isset($receipt['reference']) ? trim((string) $receipt['reference']) : '';
        $hash = md5($reference.'|'.$phone.'|'.(string) (isset($receipt['amount']) ? $receipt['amount'] : ''));
        $pdfKey = 'momo-mini:'.$hash;
        $textKey = 'momo-text:'.$hash;
        $pdfFresh = Cache::add($pdfKey, 1, now()->addDays(30));
        $textFresh = Cache::add($textKey, 1, now()->addDays(30));
        if (! $pdfFresh && ! $textFresh) {
            return;
        }
        if ($pdfFresh) {
            try {
                $front = ! empty($receipt['front']);
                $path = $front ? $this->writeFrontPdf($receipt, $reference) : $this->writePdf($receipt, $reference);
                $sent = app(BeyondWasenderService::class)->sendDocument(
                    $phone,
                    $path,
                    $front ? 'Beyond-Invoice.pdf' : 'Beyond-Mini-Invoice.pdf',
                    $front ? 'Sales Invoice' : 'Beyond Mini Invoice',
                    true
                );
                if (is_file($path)) {
                    @unlink($path);
                }
                if (empty($sent['success']) && empty($sent['skipped']) && empty($sent['dev'])) {
                    Cache::forget($pdfKey);
                    Log::warning('MoMo mini invoice was not sent: '.(isset($sent['error']) ? $sent['error'] : 'unknown'));
                }
            } catch (\Throwable $e) {
                Cache::forget($pdfKey);
                Log::warning('MoMo mini invoice failed: '.$e->getMessage());
            }
        }
        if ($textFresh) {
            try {
                $channel = app(ClientNoticeService::class)->send($phone, $this->textInvoice($receipt), false);
                if ($channel === 'none') {
                    Cache::forget($textKey);
                }
            } catch (\Throwable $e) {
                Cache::forget($textKey);
                Log::warning('Payment text invoice failed: '.$e->getMessage());
            }
        }
    }

    /**
     * Formatted WhatsApp invoice for clients who do not open the PDF.
     */
    protected function textInvoice(array $receipt)
    {
        $name = isset($receipt['name']) ? trim((string) $receipt['name']) : '';
        if ($name === '') {
            $name = 'Client';
        }
        $party = isset($receipt['party']) ? strtoupper(trim((string) $receipt['party'])) : 'CLIENT';
        $title = $party === 'RECEIVER' ? 'Withdrawal' : 'Sales invoice';
        $amount = (float) (isset($receipt['amount']) ? $receipt['amount'] : 0);
        $fee = (float) (isset($receipt['fee']) ? $receipt['fee'] : 0);
        $phone = isset($receipt['client_phone']) ? trim((string) $receipt['client_phone']) : '';
        if ($phone === '' && isset($receipt['phone'])) {
            $phone = trim((string) $receipt['phone']);
        }
        $text = WhatsAppMessage::statusBlock('✅', $title);
        $text .= 'Dear *'.$name."*,\n\n";
        $text .= '*Amount paid:* '.$this->money($amount)." XAF\n";
        if ($fee > 0) {
            $text .= '*'.$this->chargeLabel($receipt, $phone).':* '.$this->money($fee)." XAF\n";
        }
        if (array_key_exists('pending', $receipt) && $receipt['pending'] !== null && $receipt['pending'] !== '') {
            $text .= '*Pending:* '.$this->money($receipt['pending'])." XAF\n";
        }
        $text .= "\n";
        $reference = isset($receipt['reference']) ? trim((string) $receipt['reference']) : '';
        if ($reference !== '') {
            $text .= WhatsAppMessage::bullet('Reference', $reference);
        }
        $text .= WhatsAppMessage::bullet('Date', $this->textDate(isset($receipt['date']) ? $receipt['date'] : now()));
        if ($phone !== '') {
            $text .= WhatsAppMessage::bullet('Phone', $phone);
        }
        $method = isset($receipt['method']) ? trim((string) $receipt['method']) : '';
        if ($method !== '') {
            $text .= WhatsAppMessage::bullet('Method', $method);
        }
        $reason = isset($receipt['reason']) ? trim((string) $receipt['reason']) : '';
        if ($reason !== '') {
            $text .= WhatsAppMessage::bullet('Reason', $reason);
        }

        return $text.WhatsAppMessage::footer();
    }

    protected function feeAmount(array $receipt)
    {
        return max(0, (float) (isset($receipt['fee']) ? $receipt['fee'] : 0));
    }

    protected function hasPending(array $receipt)
    {
        return array_key_exists('pending', $receipt) && $receipt['pending'] !== null && $receipt['pending'] !== '';
    }

    protected function pendingAmount(array $receipt)
    {
        return max(0, (float) $receipt['pending']);
    }

    protected function invoiceTotal(array $receipt, $amount)
    {
        if (! isset($receipt['invoice_total']) || $receipt['invoice_total'] === null || $receipt['invoice_total'] === '') {
            return null;
        }
        $total = (float) $receipt['invoice_total'];
        if ($total <= 0 || abs($total - (float) $amount) < 0.01) {
            return null;
        }

        return number_format($total, 2, '.', ',');
    }

    protected function money($amount)
    {
        return number_format((float) $amount, 0, '.', ' ');
    }

    protected function textDate($when)
    {
        if ($when instanceof \DateTimeInterface) {
            return $when->format('d M Y');
        }
        $unix = strtotime((string) $when) ?: time();

        return date('d M Y', $unix);
    }

    protected function chargeLabel(array $receipt, $phone)
    {
        $operator = strtoupper(trim((string) (isset($receipt['operator']) ? $receipt['operator'] : '')));
        if ($operator === '' && $phone !== '') {
            $operator = strtoupper((string) app(CampayPayoutService::class)->operatorName($phone));
        }
        if ($operator === 'ORANGE') {
            return 'Orange Charges';
        }
        if ($operator === 'MTN' || $operator === '') {
            return 'MTN Charges';
        }

        return ucfirst(strtolower($operator)).' Charges';
    }

    protected function writePdf(array $receipt, $reference)
    {
        $setting = GeneralSetting::first();
        $biller = null;
        if ($setting && ! empty($setting->default_biller_id)) {
            $biller = Biller::find($setting->default_biller_id);
        }
        if (! $biller) {
            $biller = Biller::query()->first();
        }
        $admin = LetterSignature::adminWithSignature();
        $signature = LetterSignature::invoiceSignatureForUser($admin, isset($receipt['date']) ? $receipt['date'] : now());
        $amount = (float) (isset($receipt['amount']) ? $receipt['amount'] : 0);
        $words = '';
        try {
            $words = (new \NumberToWords\NumberToWords())->getNumberTransformer('en')->toWords((int) round($amount));
        } catch (\Throwable $e) {
            $words = '';
        }
        $logo = $this->logoData($setting);
        $when = isset($receipt['date']) ? $receipt['date'] : now();
        if ($when instanceof \DateTimeInterface) {
            $when = $when->format('d-m-Y H:i');
        } else {
            $when = date('d-m-Y H:i', strtotime((string) $when) ?: time());
        }
        $pdf = \PDF::loadView('pdf.momo_mini', [
            'title' => $setting ? $setting->site_title : 'Beyond Tech World',
            'phone' => $biller ? $biller->phone : '',
            'logo' => $logo,
            'reference' => $reference !== '' ? $reference : '—',
            'date' => $when,
            'name' => isset($receipt['name']) && trim((string) $receipt['name']) !== '' ? $receipt['name'] : 'Client',
            'clientPhone' => isset($receipt['client_phone']) && trim((string) $receipt['client_phone']) !== '' ? $receipt['client_phone'] : (isset($receipt['phone']) ? $receipt['phone'] : ''),
            'city' => isset($receipt['city']) ? $receipt['city'] : '',
            'reason' => isset($receipt['reason']) && trim((string) $receipt['reason']) !== '' ? $receipt['reason'] : 'Mobile Money payment',
            'amount' => number_format($amount, 2, '.', ','),
            'fee' => $this->feeAmount($receipt) > 0 ? number_format($this->feeAmount($receipt), 2, '.', ',') : '',
            'feeLabel' => $this->chargeLabel($receipt, isset($receipt['client_phone']) ? $receipt['client_phone'] : ''),
            'pending' => $this->hasPending($receipt) ? number_format($this->pendingAmount($receipt), 2, '.', ',') : '0.00',
            'words' => $words,
            'method' => isset($receipt['method']) && trim((string) $receipt['method']) !== '' ? $receipt['method'] : 'Mobile Money',
            'admin' => $admin ? $admin->name : 'Admin',
            'signature' => isset($signature['src']) ? $signature['src'] : null,
        ])->setPaper([0, 0, 226.77, 640], 'portrait')->setOptions(['isRemoteEnabled' => true]);

        $dir = storage_path('app/momo-invoices');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir.'/mini-'.substr(md5($reference.'|'.microtime(true)), 0, 12).'.pdf';
        file_put_contents($path, $pdf->output());

        return $path;
    }

    /**
     * A4 sales invoice for a front-end payment or a withdrawal.
     * The signature belongs to the approver only.
     */
    protected function writeFrontPdf(array $receipt, $reference)
    {
        $setting = GeneralSetting::first();
        $biller = null;
        if ($setting && ! empty($setting->default_biller_id)) {
            $biller = Biller::find($setting->default_biller_id);
        }
        if (! $biller) {
            $biller = Biller::query()->first();
        }
        $company = ($biller && trim((string) $biller->company_name) !== '')
            ? trim((string) $biller->company_name)
            : ($setting && trim((string) $setting->site_title) !== '' ? trim((string) $setting->site_title) : 'Beyond Enterprise');

        $when = isset($receipt['date']) ? $receipt['date'] : now();
        $stamp = $when instanceof \DateTimeInterface ? $when : now();
        $admin = LetterSignature::adminWithSignature();
        $signer = $admin;
        if (! empty($receipt['approver_id'])) {
            $user = \App\User::find($receipt['approver_id']);
            if ($user) {
                $signer = $user;
            }
        }
        $signature = LetterSignature::invoiceSignatureForUser($signer, $stamp);
        $approvedBy = isset($receipt['approved_by']) ? trim((string) $receipt['approved_by']) : '';
        if ($approvedBy === '') {
            $approvedBy = $signer && trim((string) $signer->name) !== ''
                ? trim((string) $signer->name)
                : ($admin ? trim((string) $admin->name) : 'Admin');
        }
        $createdBy = isset($receipt['created_by']) ? trim((string) $receipt['created_by']) : '';
        if ($createdBy === '') {
            $createdBy = $admin && trim((string) $admin->name) !== '' ? trim((string) $admin->name) : 'Admin';
        }
        $amount = (float) (isset($receipt['amount']) ? $receipt['amount'] : 0);
        if ($when instanceof \DateTimeInterface) {
            $dateText = $when->format('d M Y').' · '.$when->format('H:i');
        } else {
            $unix = strtotime((string) $when) ?: time();
            $dateText = date('d M Y', $unix).' · '.date('H:i', $unix);
        }
        $party = isset($receipt['party']) ? strtoupper(trim((string) $receipt['party'])) : 'CLIENT';
        if ($party === '') {
            $party = 'CLIENT';
        }
        $description = isset($receipt['description']) ? trim((string) $receipt['description']) : '';
        if ($description === '') {
            $description = $party === 'RECEIVER' ? 'Withdrawal' : 'Payment';
        }
        $method = isset($receipt['method']) ? trim((string) $receipt['method']) : '';
        if ($method === '') {
            $method = 'Mobile Money';
        }

        $pdf = \PDF::loadView('pdf.front_payment', [
            'company' => $company,
            'logo' => $this->logoData($setting),
            'reference' => $reference !== '' ? $reference : '—',
            'date' => $dateText,
            'partyLabel' => $party,
            'partyName' => isset($receipt['name']) ? trim((string) $receipt['name']) : '',
            'partyPhone' => isset($receipt['client_phone']) ? trim((string) $receipt['client_phone']) : '',
            'requestedName' => isset($receipt['requested_by']) ? trim((string) $receipt['requested_by']) : '',
            'requestedPhone' => isset($receipt['requested_phone']) ? trim((string) $receipt['requested_phone']) : '',
            'reason' => isset($receipt['reason']) ? trim((string) $receipt['reason']) : '',
            'description' => $description,
            'amount' => number_format($amount, 2, '.', ','),
            'invoiceTotal' => $this->invoiceTotal($receipt, $amount),
            'fee' => $this->feeAmount($receipt) > 0 ? number_format($this->feeAmount($receipt), 2, '.', ',') : '',
            'feeLabel' => $this->chargeLabel($receipt, isset($receipt['client_phone']) ? $receipt['client_phone'] : ''),
            'pending' => $this->hasPending($receipt) ? number_format($this->pendingAmount($receipt), 2, '.', ',') : null,
            'method' => $method,
            'createdBy' => $createdBy,
            'approvedBy' => $approvedBy,
            'signature' => isset($signature['src']) ? $signature['src'] : null,
        ])->setPaper('a4', 'portrait')->setOptions(['isRemoteEnabled' => true]);

        $dir = storage_path('app/momo-invoices');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir.'/invoice-'.substr(md5($reference.'|'.microtime(true)), 0, 12).'.pdf';
        file_put_contents($path, $pdf->output());

        return $path;
    }

    protected function logoData($setting)
    {
        if (! $setting || empty($setting->site_logo)) {
            return '';
        }
        $file = public_path('logo/'.$setting->site_logo);
        if (! is_file($file)) {
            return '';
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return '';
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = ($ext === 'jpg' || $ext === 'jpeg') ? 'image/jpeg' : ($ext === 'gif' ? 'image/gif' : 'image/png');

        return 'data:'.$mime.';base64,'.base64_encode($raw);
    }
}
