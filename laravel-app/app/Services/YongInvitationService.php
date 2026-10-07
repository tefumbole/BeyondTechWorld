<?php

namespace App\Services;

use App\YongInvitation;
use Carbon\Carbon;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

class YongInvitationService
{
    const PASTOR_WHATSAPP = '+237679854954';

    const SERVICE_AT = '2026-10-11 10:00:00';

    public function serviceAt()
    {
        return Carbon::parse(self::SERVICE_AT, 'Africa/Douala');
    }

    public function create($phone, $name, $position, $pledgeAmount)
    {
        $amount = $pledgeAmount === null ? null : (int) $pledgeAmount;
        $pledged = $amount !== null && $amount >= 100;
        $position = in_array($position, ['friend', 'family', 'clergy', 'guest'], true) ? $position : 'guest';
        if ($position === 'clergy') {
            $type = 'clergy';
        } elseif ($pledged) {
            $type = 'gold';
        } else {
            $type = 'standard';
            $amount = null;
        }
        if (! $pledged) {
            $amount = null;
        }
        $id = (string) Str::uuid();
        $imageFile = $id.'.jpg';
        $this->ensureOutDir();

        $row = new YongInvitation([
            'id' => $id,
            'phone' => $phone,
            'name' => $name,
            'position' => $position,
            'pledge_amount' => $amount,
            'invitation_type' => $type,
            'image_file' => $imageFile,
            'payment_status' => $amount ? 'unpaid' : null,
        ]);

        $passUrl = url('/yong/pass/'.$id);
        $this->compose(public_path('yong/'.$type.'.jpg'), $row->imagePath(), $name, $row->typeLabel(), $passUrl, $type);

        if ($amount) {
            $pdfFile = $id.'.pdf';
            $row->pdf_file = $pdfFile;
            $this->writePdf($row);
        }

        $row->save();

        return $row;
    }

    public function guestFile(YongInvitation $row)
    {
        if ($row->isPremium() && $row->pdfPath() && is_file($row->pdfPath())) {
            return $row->pdfPath();
        }

        return $row->imagePath();
    }

    public function sendToGuest(YongInvitation $row)
    {
        $whatsapp = app(BeyondWasenderService::class);
        $caption = $this->guestCaption($row);
        $file = $this->guestFile($row);
        if ($row->isPremium() && substr($file, -4) === '.pdf') {
            return $whatsapp->sendDocument($row->phone, $file, 'Yong-Induction-Invitation.pdf', $caption);
        }

        return $whatsapp->sendImage($row->phone, $file, $caption);
    }

    public function queuePastorCopy(YongInvitation $row)
    {
        $whatsapp = app(BeyondWasenderService::class);
        try {
            $guestTo = $whatsapp->formatPhone($row->phone);
            $pastorTo = $whatsapp->formatPhone(self::PASTOR_WHATSAPP);
        } catch (\Throwable $e) {
            return;
        }
        if (! $pastorTo || $guestTo === $pastorTo) {
            return;
        }

        $path = $this->guestFile($row);
        $caption = $this->pastorCaption($row);
        $premium = $row->isPremium() && substr((string) $path, -4) === '.pdf';

        app()->terminating(function () use ($path, $caption, $premium) {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            ignore_user_abort(true);
            @set_time_limit(90);
            try {
                $sender = app(BeyondWasenderService::class);
                $copy = $premium
                    ? $sender->sendDocument(self::PASTOR_WHATSAPP, $path, 'Yong-Induction-Invitation.pdf', $caption)
                    : $sender->sendImage(self::PASTOR_WHATSAPP, $path, $caption);
                if (empty($copy['success'])) {
                    Log::info('yong pastor copy failed', ['error' => $copy['error'] ?? 'unknown']);
                }
            } catch (\Throwable $e) {
                Log::warning('yong pastor copy exception: '.$e->getMessage());
            }
        });
    }

    public function paymentLink(YongInvitation $row, $method)
    {
        if (! $row->isPremium() || (int) $row->pledge_amount < 100) {
            throw new \InvalidArgumentException('This invitation has no pledge to pay.');
        }
        if ($row->payment_status === 'paid') {
            return url('/yong/pass/'.$row->id.'?pay=ok');
        }
        if ($method === 'visa' || $method === 'stripe') {
            return $this->stripeCheckout($row);
        }

        return $this->campayLink($row);
    }

    public function handleCampay($status, $reference, $externalReference)
    {
        $row = YongInvitation::find((string) $externalReference);
        if (! $row) {
            return url('/yong?pay=failed');
        }
        $status = strtoupper((string) $status);
        if ($status === 'SUCCESSFUL') {
            $row->payment_status = 'paid';
            $row->campay_reference = $reference;
            $row->paid_at = Carbon::now();
            $row->save();

            return url('/yong/pass/'.$row->id.'?pay=ok');
        }
        if ($status === 'PENDING') {
            $row->campay_reference = $reference;
            $row->save();

            return url('/yong/pass/'.$row->id.'?pay=pending');
        }
        $row->payment_status = 'failed';
        $row->campay_reference = $reference;
        $row->save();

        return url('/yong/donate/'.$row->id.'?pay=failed');
    }

    public function handleStripe($sessionId)
    {
        $secret = config('services.stripe.secret') ?: getenv('STRIPE_SECRET');
        if (! $secret || ! $sessionId) {
            return url('/yong?pay=failed');
        }
        Stripe::setApiKey($secret);
        try {
            $session = StripeSession::retrieve($sessionId);
        } catch (\Throwable $e) {
            Log::info('Yong Stripe retrieve failed: '.$e->getMessage());

            return url('/yong?pay=failed');
        }
        $id = ! empty($session->metadata->invitation_id) ? (string) $session->metadata->invitation_id : '';
        $row = $id !== '' ? YongInvitation::find($id) : YongInvitation::where('stripe_session_id', $sessionId)->first();
        if (! $row) {
            return url('/yong?pay=failed');
        }
        $paid = ($session->payment_status === 'paid') || ($session->status === 'complete');
        if (! $paid) {
            return url('/yong/pass/'.$row->id.'?pay=pending');
        }
        $row->payment_status = 'paid';
        $row->stripe_session_id = $sessionId;
        $row->paid_at = Carbon::now();
        $row->save();

        return url('/yong/pass/'.$row->id.'?pay=ok');
    }

    protected function guestCaption(YongInvitation $row)
    {
        $lines = [
            'Induction Service invitation',
            'Sunday 11 October 2026, 10:00am',
            'The Apostolic Church Obili',
            'Invitation type: '.$row->typeLabel(),
        ];
        if ($row->isPremium()) {
            $lines[] = 'Donate '.$row->pledgeLabel().': '.url('/yong/donate/'.$row->id);
        }

        return implode("\n", $lines);
    }

    protected function pastorCaption(YongInvitation $row)
    {
        return implode("\n", [
            'Invitation for '.$row->name,
            'Phone: '.$row->phone,
            'Position: '.$row->positionLabel(),
            'Pledge: '.$row->pledgeLabel(),
            'Type: '.$row->typeLabel(),
        ]);
    }

    protected function campayLink(YongInvitation $row)
    {
        $token = config('services.campay.token') ?: getenv('CAMPAY_TOKEN') ?: getenv('MOMO_TOKEN');
        if (! $token) {
            throw new \RuntimeException('Mobile money is not configured.');
        }
        $callback = route('yong.payment');
        $payload = json_encode([
            'amount' => (string) (int) $row->pledge_amount,
            'from' => preg_replace('/\D/', '', (string) $row->phone),
            'currency' => 'XAF',
            'external_reference' => $row->id,
            'redirect_url' => $callback,
            'payment_options' => 'MOMO',
            'failure_redirect_url' => $callback,
        ]);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://www.campay.net/api/get_payment_link/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Token '.$token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        $raw = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            throw new \RuntimeException('Could not start payment.');
        }
        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded) || empty($decoded['link'])) {
            Log::info('Yong Campay link failed', ['body' => substr((string) $raw, 0, 400)]);
            throw new \RuntimeException('Could not start MoMo / Orange Money payment.');
        }

        return $decoded['link'];
    }

    protected function stripeCheckout(YongInvitation $row)
    {
        $secret = config('services.stripe.secret') ?: getenv('STRIPE_SECRET');
        if (! $secret) {
            throw new \RuntimeException('Card payment is not configured.');
        }
        Stripe::setApiKey($secret);
        $session = StripeSession::create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'xaf',
                    'unit_amount' => (int) $row->pledge_amount,
                    'product_data' => [
                        'name' => 'Induction pledge — Rev. Yong Nkiase',
                        'description' => $row->name.' · '.$row->pledgeLabel(),
                    ],
                ],
                'quantity' => 1,
            ]],
            'success_url' => route('yong.stripe', [], true).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => url('/yong/donate/'.$row->id.'?pay=failed'),
            'metadata' => [
                'invitation_id' => (string) $row->id,
                'module' => 'yong',
            ],
        ]);
        $row->stripe_session_id = $session->id;
        $row->save();

        return $session->url;
    }

    protected function writePdf(YongInvitation $row)
    {
        $image = $row->imagePath();
        $binary = is_file($image) ? file_get_contents($image) : '';
        $dataUri = $binary !== '' ? 'data:image/jpeg;base64,'.base64_encode($binary) : '';
        \PDF::loadView('beyond.yong.pdf', [
            'invitation' => $row,
            'imageData' => $dataUri,
            'donateUrl' => url('/yong/donate/'.$row->id),
        ])->setPaper('a4', 'landscape')->save($row->pdfPath());
    }

    protected function compose($template, $dest, $name, $typeLabel, $passUrl, $type)
    {
        $img = @imagecreatefromjpeg($template);
        if (! $img) {
            throw new \RuntimeException('Invitation artwork could not be opened.');
        }
        $ink = $this->inkColor($img, $type);
        $font = $this->fontPath();
        $this->drawCentered($img, $name, $font, 22, 478, 132, 464, 50, $ink);
        $this->drawCentered($img, $typeLabel, $font, 13, 518, 494, 272, 22, $ink);

        $qrFile = $dest.'.qr.png';
        $this->writeQr($passUrl, $qrFile, 240);
        $qr = @imagecreatefrompng($qrFile);
        if ($qr) {
            imagecopyresampled($img, $qr, 860, 421, 0, 0, 82, 82, imagesx($qr), imagesy($qr));
            imagedestroy($qr);
        }
        @unlink($qrFile);

        imagejpeg($img, $dest, 100);
        imagedestroy($img);
        if (! is_file($dest)) {
            throw new \RuntimeException('Invitation image was not saved.');
        }
    }

    protected function drawCentered($img, $text, $font, $size, $boxX, $boxY, $boxW, $boxH, $color)
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $text));
        if ($text === '') {
            return;
        }
        if ($font && function_exists('imagettftext')) {
            while ($size > 9) {
                $bounds = imagettfbbox($size, 0, $font, $text);
                $width = abs($bounds[2] - $bounds[0]);
                if ($width <= $boxW - 12) {
                    break;
                }
                $size--;
            }
            $bounds = imagettfbbox($size, 0, $font, $text);
            $width = abs($bounds[2] - $bounds[0]);
            $height = abs($bounds[7] - $bounds[1]);
            $x = (int) ($boxX + (($boxW - $width) / 2));
            $y = (int) ($boxY + (($boxH + $height) / 2) - 1);
            imagettftext($img, $size, 0, $x, $y, $color, $font, $text);

            return;
        }
        $x = (int) ($boxX + max(0, ($boxW - (strlen($text) * 8)) / 2));
        $y = (int) ($boxY + 8);
        imagestring($img, 5, $x, $y, substr($text, 0, 40), $color);
    }

    protected function inkColor($img, $type)
    {
        if ($type === 'gold') {
            return imagecolorallocate($img, 110, 18, 28);
        }
        if ($type === 'clergy') {
            return imagecolorallocate($img, 58, 16, 110);
        }

        return imagecolorallocate($img, 11, 36, 92);
    }

    protected function writeQr($text, $dest, $size)
    {
        $encoded = Encoder::encode($text, ErrorCorrectionLevel::M());
        $matrix = $encoded->getMatrix();
        $modules = $matrix->getWidth();
        $scale = (int) max(1, floor($size / $modules));
        $px = $modules * $scale;
        $qr = imagecreatetruecolor($px, $px);
        $white = imagecolorallocate($qr, 255, 255, 255);
        $black = imagecolorallocate($qr, 0, 0, 0);
        imagefill($qr, 0, 0, $white);
        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    imagefilledrectangle(
                        $qr,
                        $x * $scale,
                        $y * $scale,
                        (($x + 1) * $scale) - 1,
                        (($y + 1) * $scale) - 1,
                        $black
                    );
                }
            }
        }
        imagepng($qr, $dest);
        imagedestroy($qr);
    }

    protected function fontPath()
    {
        $path = public_path('yong/fonts/LibreBaskerville-Bold.ttf');
        if (is_file($path)) {
            return $path;
        }
        $fallbacks = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSerif-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSerif-Bold.ttf',
        ];
        foreach ($fallbacks as $file) {
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    protected function ensureOutDir()
    {
        $dir = public_path('yong/out');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }
}
