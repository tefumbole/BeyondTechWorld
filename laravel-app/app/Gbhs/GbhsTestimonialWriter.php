<?php

namespace App\Gbhs;

use App\Services\BeyondWasenderService;
use App\Services\FuneralPledgeService;
use App\Services\PeopleDirectoryService;
use App\Support\TwilioAdminCopy;
use App\Support\WhatsAppPhone;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GbhsTestimonialWriter
{
    public function create(array $data)
    {
        $body = trim((string) ($data['body'] ?? ''));
        if (mb_strlen($body) < 20) {
            throw new \InvalidArgumentException('Please write a little more for the testimony.');
        }
        $phone = (string) ($data['phone'] ?? '');
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || strlen(preg_replace('/\D/', '', $phone)) < 8) {
            throw new \InvalidArgumentException('Enter your name and a valid phone number.');
        }

        $duplicate = GbhsTestimonial::where('phone', $phone)->where('body', $body)->orderByDesc('id')->first();
        if ($duplicate) {
            return $duplicate;
        }

        $customerId = null;
        try {
            $customer = app(PeopleDirectoryService::class)->findOrCreateCustomerQuick([
                'name' => $name,
                'phone' => $phone,
                'address' => '',
            ]);
            $customerId = $customer['customer']->id ?? null;
        } catch (\Throwable $e) {
            Log::info('GBHS testimony customer skipped: '.$e->getMessage());
        }

        $signature = $this->storeSignature($data['signature'] ?? '');
        if (! $signature) {
            throw new \InvalidArgumentException('Please sign the testimony before submitting.');
        }

        $row = GbhsTestimonial::create([
            'customer_id' => $customerId,
            'name' => $name,
            'phone' => $phone,
            'body' => $body,
            'signature_path' => $signature,
            'selfie_path' => $this->storeSelfie($data['selfie'] ?? null),
        ]);

        try {
            $this->notify($row->fresh());
        } catch (\Throwable $e) {
            Log::info('GBHS testimony notify failed: '.$e->getMessage(), ['testimony' => $row->id]);
        }

        return $row;
    }

    public function notify(GbhsTestimonial $row)
    {
        $pageUrl = route('gbhs.page', 'leaders');
        $excerpt = trim(preg_replace('/\s+/', ' ', (string) $row->body));
        if (strlen($excerpt) > 500) {
            $excerpt = rtrim(substr($excerpt, 0, 500)).'…';
        }
        $thanks = "Thank you, *{$row->name}*.\n\n"
            ."Your testimony about *GBHS Kumbo* was received. A PDF copy is attached.\n\n"
            .$pageUrl;
        $adminMsg = "New GBHS Kumbo testimony\n\n"
            ."*{$row->name}*\n"
            .WhatsAppPhone::display($row->phone)."\n\n"
            .$excerpt."\n\n"
            .$pageUrl;
        $whatsapp = app(BeyondWasenderService::class);
        $adminPhone = TwilioAdminCopy::PHONE;
        $pdfPath = $this->copyPdf($row);
        $writerPhone = $row->phone;
        $id = $row->id;

        try {
            $whatsapp->sendTextRaw($writerPhone, $thanks, true, false);
        } catch (\Throwable $e) {
            Log::info('GBHS testimony WhatsApp thanks failed: '.$e->getMessage(), ['testimony' => $id]);
        }

        app()->terminating(function () use ($whatsapp, $writerPhone, $adminPhone, $adminMsg, $pdfPath, $id) {
            if ($pdfPath) {
                try {
                    $whatsapp->sendDocument(
                        $writerPhone,
                        $pdfPath,
                        'GBHS-Kumbo-testimony.pdf',
                        'Your testimony about GBHS Kumbo.',
                        true
                    );
                } catch (\Throwable $e) {
                    Log::info('GBHS testimony PDF copy failed: '.$e->getMessage(), ['testimony' => $id]);
                }
            }
            try {
                $whatsapp->sendTextRaw($adminPhone, $adminMsg, true, false);
            } catch (\Throwable $e) {
                Log::info('GBHS testimony admin notice failed: '.$e->getMessage(), ['testimony' => $id]);
            }
        });
    }

    protected function copyPdf(GbhsTestimonial $row)
    {
        try {
            $dir = storage_path('app/gbhs-testimonials');
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $path = $dir.'/testimony-'.$row->id.'.pdf';
            \PDF::loadView('gbhs.public.testimonial_pdf', ['row' => $row])
                ->setPaper('a4')
                ->save($path);

            return is_file($path) ? $path : null;
        } catch (\Throwable $e) {
            Log::info('GBHS testimony PDF failed: '.$e->getMessage(), ['testimony' => $row->id]);

            return null;
        }
    }

    protected function storeSignature($dataUri)
    {
        $dataUri = trim((string) $dataUri);
        if ($dataUri === '' || stripos($dataUri, 'data:image') !== 0) {
            return null;
        }
        $comma = strpos($dataUri, ',');
        $payload = $comma === false ? '' : substr($dataUri, $comma + 1);
        $bin = base64_decode(preg_replace('/\s+/', '', $payload), true);
        if ($bin === false || strlen($bin) < 80 || strlen($bin) > 2 * 1024 * 1024) {
            return null;
        }
        $processed = app(FuneralPledgeService::class)->signaturePngWithTimestamp($bin, Carbon::now('Africa/Douala'));
        if ($processed === null) {
            return null;
        }
        $dir = public_path('gbhs/testimonials');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = 'sign_'.date('YmdHis').'_'.substr(md5($processed), 0, 8).'.png';
        file_put_contents($dir.'/'.$name, $processed);

        return 'gbhs/testimonials/'.$name;
    }

    protected function storeSelfie($file)
    {
        if (! $file || ! is_object($file) || ! method_exists($file, 'isValid') || ! $file->isValid()) {
            return null;
        }
        $mime = method_exists($file, 'getMimeType') ? strtolower((string) $file->getMimeType()) : '';
        if ($mime !== '' && strpos($mime, 'image/') !== 0 && $mime !== 'application/octet-stream') {
            throw new \InvalidArgumentException('Please choose a photo.');
        }
        $bin = @file_get_contents($file->getRealPath());
        if ($bin === false || strlen($bin) < 80) {
            return null;
        }
        $maxBytes = 256 * 1024;
        if (function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring($bin);
            if ($img) {
                $w = imagesx($img);
                $h = imagesy($img);
                $maxSide = 960;
                if ($w > $maxSide || $h > $maxSide) {
                    $scale = $maxSide / max($w, $h);
                    $nw = max(1, (int) round($w * $scale));
                    $nh = max(1, (int) round($h * $scale));
                    $dst = imagecreatetruecolor($nw, $nh);
                    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                    imagedestroy($img);
                    $img = $dst;
                }
                $out = null;
                for ($q = 80; $q >= 38; $q -= 8) {
                    ob_start();
                    imagejpeg($img, null, $q);
                    $try = ob_get_clean();
                    $out = $try;
                    if (strlen($try) <= $maxBytes) {
                        break;
                    }
                }
                imagedestroy($img);
                if ($out) {
                    $bin = $out;
                }
            }
        }
        if (strlen($bin) > $maxBytes) {
            throw new \InvalidArgumentException('The selfie must be 256 KB or smaller.');
        }
        $dir = public_path('gbhs/testimonials');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = 'selfie_'.date('YmdHis').'_'.substr(md5($bin), 0, 8).'.jpg';
        file_put_contents($dir.'/'.$name, $bin);

        return 'gbhs/testimonials/'.$name;
    }
}
