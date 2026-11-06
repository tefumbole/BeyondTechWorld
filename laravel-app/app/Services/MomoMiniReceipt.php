<?php

namespace App\Services;

use App\Biller;
use App\GeneralSetting;
use App\Support\LetterSignature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Beyond Mini invoice sent after a confirmed Mobile Money payment.
 */
class MomoMiniReceipt
{
    public function send(array $receipt)
    {
        $phone = isset($receipt['phone']) ? trim((string) $receipt['phone']) : '';
        if ($phone === '') {
            return;
        }
        $reference = isset($receipt['reference']) ? trim((string) $receipt['reference']) : '';
        $key = 'momo-mini:'.md5($reference.'|'.$phone.'|'.(string) (isset($receipt['amount']) ? $receipt['amount'] : ''));
        if (! Cache::add($key, 1, now()->addDays(30))) {
            return;
        }
        try {
            $path = $this->writePdf($receipt, $reference);
            $sent = app(BeyondWasenderService::class)->sendDocument(
                $phone,
                $path,
                'Beyond-Mini-Invoice.pdf',
                'Beyond Mini Invoice',
                true
            );
            if (is_file($path)) {
                @unlink($path);
            }
            if (empty($sent['success']) && empty($sent['skipped']) && empty($sent['dev'])) {
                Cache::forget($key);
                Log::warning('MoMo mini invoice was not sent: '.(isset($sent['error']) ? $sent['error'] : 'unknown'));
            }
        } catch (\Throwable $e) {
            Cache::forget($key);
            Log::warning('MoMo mini invoice failed: '.$e->getMessage());
        }
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
        $logo = '';
        if ($setting && ! empty($setting->site_logo)) {
            $file = public_path('logo/'.$setting->site_logo);
            if (is_file($file)) {
                $raw = @file_get_contents($file);
                if ($raw !== false) {
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $mime = ($ext === 'jpg' || $ext === 'jpeg') ? 'image/jpeg' : ($ext === 'gif' ? 'image/gif' : 'image/png');
                    $logo = 'data:'.$mime.';base64,'.base64_encode($raw);
                }
            }
        }
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
            'clientPhone' => isset($receipt['phone']) ? $receipt['phone'] : '',
            'city' => isset($receipt['city']) ? $receipt['city'] : '',
            'reason' => isset($receipt['reason']) && trim((string) $receipt['reason']) !== '' ? $receipt['reason'] : 'Mobile Money payment',
            'amount' => number_format($amount, 2, '.', ','),
            'words' => $words,
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
}
