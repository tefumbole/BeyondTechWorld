<?php

namespace App\Services;

use App\FuneralCampaign;
use App\FuneralEulogy;
use App\FuneralItem;
use App\FuneralPledge;
use App\Support\WhatsAppMessage;
use App\Support\WhatsAppPhone;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

class FuneralPledgeService
{
    public function isEnabled()
    {
        $flag = config('services.funeral_pledge.enabled', true);
        if (! $flag) {
            return false;
        }
        $campaign = $this->campaign();

        return $campaign && $campaign->enabled;
    }

    public function campaign()
    {
        return FuneralCampaign::where('slug', 'pangwayu')->first();
    }

    public function pageData()
    {
        $campaign = FuneralCampaign::where('slug', 'pangwayu')
            ->with(['items.pledges', 'pledges'])
            ->first();
        if (! $campaign) {
            return null;
        }

        $groups = [
            'food' => 'Food and main service',
            'takeaway' => 'Take Away',
            'logistics' => 'Mortuary and Logistics',
            'other' => 'Other',
        ];
        $items = [];
        foreach ($campaign->items as $item) {
            $remaining = $item->remainingAmount();
            $items[] = [
                'id' => $item->id,
                'category' => $item->category,
                'category_label' => isset($groups[$item->category]) ? $groups[$item->category] : $item->category,
                'name' => $item->name,
                'target' => $item->target_amount !== null ? (int) $item->target_amount : null,
                'is_open' => (bool) $item->is_open,
                'committed' => $item->committedAmount(),
                'remaining' => $remaining,
                'covered' => $item->isCovered(),
                'names' => $item->pledgerFirstNames(),
            ];
        }

        $eulogies = FuneralEulogy::where('campaign_id', $campaign->id)
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->map(function ($row) {
                return [
                    'name' => $row->name,
                    'excerpt' => $row->excerpt(240),
                    'body' => $row->body,
                    'paragraphs' => $this->eulogyParagraphs($row->body),
                    'has_signature' => ! empty($row->signature_path),
                    'signature' => $row->signature_path ? asset($row->signature_path) : '',
                    'has_selfie' => ! empty($row->selfie_path),
                    'selfie' => $row->selfie_path ? asset($row->selfie_path) : '',
                    'when' => $row->created_at ? $row->created_at->timezone('Africa/Douala')->format('d M Y') : '',
                ];
            })
            ->all();

        return [
            'campaign' => $campaign,
            'items' => $items,
            'groups' => $groups,
            'eulogies' => $eulogies,
            'raised' => $campaign->raisedAmount(),
            'target' => (int) $campaign->target_amount,
            'percent' => $campaign->raisedPercent(),
            'funeral_at' => $this->funeralAtIso($campaign),
        ];
    }

    public function createPledge(array $data)
    {
        $campaign = $this->campaign();
        $item = FuneralItem::with('pledges')->find($data['item_id']);
        if (! $campaign || ! $item || (int) $item->campaign_id !== (int) $campaign->id) {
            throw new \InvalidArgumentException('Item not found.');
        }

        $amount = (int) $data['amount'];
        if ($amount < 100) {
            throw new \InvalidArgumentException('Enter at least 100 XAF.');
        }

        $remaining = $item->remainingAmount();
        if ($remaining !== null && $amount > $remaining) {
            throw new \InvalidArgumentException('Only '.$remaining.' XAF remains on this item.');
        }

        $customer = app(PeopleDirectoryService::class)->findOrCreateCustomerQuick([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'address' => '',
        ]);

        $isPay = ($data['action'] ?? 'pledge') === 'pay';
        $pledge = FuneralPledge::create([
            'campaign_id' => $campaign->id,
            'item_id' => $item->id,
            'customer_id' => $customer['customer']->id,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'amount' => $amount,
            'kind' => $isPay ? FuneralPledge::KIND_PAYMENT : FuneralPledge::KIND_PLEDGE,
            'status' => $isPay ? FuneralPledge::STATUS_PENDING : FuneralPledge::STATUS_PLEDGED,
        ]);

        if (! $isPay) {
            $this->notifyPledge($pledge->fresh(['item', 'campaign']));
        }

        return $pledge;
    }

    public function paymentLink(FuneralPledge $pledge, $method = 'momo', $back = null)
    {
        if ($method === 'visa' || $method === 'stripe') {
            return $this->stripeCheckout($pledge, $back);
        }

        return $this->campayMomoLink($pledge, $back);
    }

    protected function publicPayUrl($status, $back = null)
    {
        if ($back === 'remember') {
            return route('funeral.pangwayu.remember', ['pay' => $status]);
        }

        return route('funeral.pangwayu', ['pay' => $status]);
    }

    protected function campayMomoLink(FuneralPledge $pledge, $back = null)
    {
        $token = config('services.campay.token') ?: getenv('CAMPAY_TOKEN') ?: getenv('MOMO_TOKEN');
        if (! $token) {
            throw new \RuntimeException('Mobile money is not configured.');
        }

        $phone = preg_replace('/\D/', '', (string) $pledge->phone);
        $callback = route('funeral.pangwayu.payment');
        $fail = $this->publicPayUrl('failed', $back);
        $payload = json_encode([
            'amount' => (string) $pledge->amount,
            'from' => $phone,
            'currency' => 'XAF',
            'external_reference' => $pledge->id.','.$fail.','.($back ?: ''),
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
            Log::info('Funeral Campay link failed', ['body' => substr((string) $raw, 0, 400)]);
            throw new \RuntimeException('Could not start MoMo / Orange Money payment.');
        }

        return $decoded['link'];
    }

    protected function stripeCheckout(FuneralPledge $pledge, $back = null)
    {
        $secret = config('services.stripe.secret') ?: getenv('STRIPE_SECRET');
        if (! $secret) {
            throw new \RuntimeException('Card payment is not configured.');
        }

        $itemName = $pledge->item ? $pledge->item->name : 'Funeral pledge';
        Stripe::setApiKey($secret);
        $session = StripeSession::create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'xaf',
                    'unit_amount' => (int) $pledge->amount,
                    'product_data' => [
                        'name' => 'Pa Ngwayu Francis — '.$itemName,
                        'description' => $pledge->name.' · funeral pledge',
                    ],
                ],
                'quantity' => 1,
            ]],
            'success_url' => route('funeral.pangwayu.stripe', [], true).'?session_id={CHECKOUT_SESSION_ID}&back='.urlencode((string) $back),
            'cancel_url' => $this->publicPayUrl('failed', $back),
            'metadata' => [
                'pledge_id' => (string) $pledge->id,
                'module' => 'pangwayu',
                'back' => (string) $back,
            ],
        ]);

        $pledge->stripe_session_id = $session->id;
        $pledge->save();

        return $session->url;
    }

    public function handleStripeReturn($sessionId, $back = null)
    {
        $secret = config('services.stripe.secret') ?: getenv('STRIPE_SECRET');
        if (! $secret || ! $sessionId) {
            return ['ok' => false, 'redirect' => $this->publicPayUrl('failed', $back)];
        }

        Stripe::setApiKey($secret);
        try {
            $session = StripeSession::retrieve($sessionId);
        } catch (\Throwable $e) {
            Log::info('Funeral Stripe retrieve failed: '.$e->getMessage());

            return ['ok' => false, 'redirect' => $this->publicPayUrl('failed', $back)];
        }

        $pledge = FuneralPledge::with(['item', 'campaign'])
            ->where('stripe_session_id', $sessionId)
            ->first();
        if (! $pledge && ! empty($session->metadata->pledge_id)) {
            $pledge = FuneralPledge::with(['item', 'campaign'])->find((int) $session->metadata->pledge_id);
        }
        if (! $back && ! empty($session->metadata->back)) {
            $back = (string) $session->metadata->back;
        }
        if (! $pledge) {
            return ['ok' => false, 'redirect' => $this->publicPayUrl('failed', $back)];
        }

        $paid = ($session->payment_status === 'paid') || ($session->status === 'complete');
        if (! $paid) {
            return ['ok' => false, 'redirect' => $this->publicPayUrl('pending', $back)];
        }

        $pledge->status = FuneralPledge::STATUS_PAID;
        $pledge->kind = FuneralPledge::KIND_PAYMENT;
        $pledge->stripe_session_id = $sessionId;
        $pledge->paid_at = Carbon::now();
        $pledge->save();
        $this->notifyPledge($pledge->fresh(['item', 'campaign']));

        return ['ok' => true, 'redirect' => $this->publicPayUrl('ok', $back)];
    }

    public function createEulogy(array $data)
    {
        $campaign = $this->campaign();
        if (! $campaign) {
            throw new \InvalidArgumentException('Campaign not found.');
        }
        $body = trim((string) $data['body']);
        if (strlen($body) < 20) {
            throw new \InvalidArgumentException('Please write a little more for the eulogy.');
        }

        $duplicate = FuneralEulogy::where('campaign_id', $campaign->id)
            ->where('phone', $data['phone'])
            ->where('body', $body)
            ->orderByDesc('id')
            ->first();
        if ($duplicate) {
            return $duplicate;
        }

        $customer = app(PeopleDirectoryService::class)->findOrCreateCustomerQuick([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'address' => '',
        ]);

        $sigPath = $this->storeSignature($data['signature'] ?? '');
        if (! empty($data['require_signature']) && ! $sigPath) {
            throw new \InvalidArgumentException('Please sign the eulogy before submitting.');
        }
        $selfiePath = $this->storeSelfie($data['selfie'] ?? null);
        $eulogy = FuneralEulogy::create([
            'campaign_id' => $campaign->id,
            'customer_id' => $customer['customer']->id,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'body' => $body,
            'signature_path' => $sigPath,
            'selfie_path' => $selfiePath,
        ]);

        $this->notifyEulogy($eulogy->fresh());

        return $eulogy;
    }

    protected function storeSignature($dataUri)
    {
        $dataUri = trim((string) $dataUri);
        if ($dataUri === '' || strpos($dataUri, 'data:image') !== 0) {
            return null;
        }
        if (! preg_match('#^data:image/(png|jpeg|jpg);base64,([A-Za-z0-9+/=\s]+)$#', $dataUri, $m)) {
            return null;
        }
        $bin = base64_decode($m[2], true);
        if ($bin === false || strlen($bin) < 80 || strlen($bin) > 800000) {
            return null;
        }
        $processed = $this->signaturePngWithTimestamp($bin);
        if ($processed === null) {
            return null;
        }

        return $this->writeEulogySignaturePng($processed);
    }

    /**
     * Turn a signature into a cropped transparent PNG with a small timestamp.
     */
    public function signaturePngWithTimestamp($bin, $at = null)
    {
        if (! function_exists('imagecreatefromstring')) {
            return $bin;
        }
        $src = @imagecreatefromstring($bin);
        if (! $src) {
            return null;
        }
        imagealphablending($src, true);
        imagesavealpha($src, true);
        $w = imagesx($src);
        $h = imagesy($src);
        $minX = $w;
        $minY = $h;
        $maxX = -1;
        $maxY = -1;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if ($this->signaturePixelIsInk($src, $x, $y)) {
                    if ($x < $minX) {
                        $minX = $x;
                    }
                    if ($y < $minY) {
                        $minY = $y;
                    }
                    if ($x > $maxX) {
                        $maxX = $x;
                    }
                    if ($y > $maxY) {
                        $maxY = $y;
                    }
                }
            }
        }
        if ($maxX < 0) {
            imagedestroy($src);

            return null;
        }

        $pad = 10;
        $stampH = 18;
        $cw = max(168, ($maxX - $minX + 1) + ($pad * 2));
        $ch = ($maxY - $minY + 1) + ($pad * 2) + $stampH;
        $dst = imagecreatetruecolor($cw, $ch);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $cw, $ch, $transparent);

        for ($y = $minY; $y <= $maxY; $y++) {
            for ($x = $minX; $x <= $maxX; $x++) {
                $pixel = $this->signaturePixel($src, $x, $y);
                if ($pixel['skip']) {
                    continue;
                }
                $color = imagecolorallocatealpha($dst, $pixel['r'], $pixel['g'], $pixel['b'], $pixel['a']);
                imagesetpixel($dst, $x - $minX + $pad, $y - $minY + $pad, $color);
            }
        }

        imagealphablending($dst, true);
        $when = $at instanceof Carbon ? $at->copy()->timezone('Africa/Douala') : Carbon::now('Africa/Douala');
        $stamp = $when->format('d M Y - H:i');
        $stampColor = imagecolorallocatealpha($dst, 92, 74, 36, 15);
        $stampX = max($pad, $cw - (imagefontwidth(2) * strlen($stamp)) - $pad);
        imagestring($dst, 2, $stampX, $ch - 16, $stamp, $stampColor);

        ob_start();
        imagepng($dst);
        $out = ob_get_clean();
        imagedestroy($src);
        imagedestroy($dst);

        return $out ?: null;
    }

    public function refreshEulogySignatures()
    {
        $updated = 0;
        $rows = FuneralEulogy::whereNotNull('signature_path')->orderBy('id')->get();
        foreach ($rows as $row) {
            $rel = ltrim((string) $row->signature_path, '/');
            $path = public_path(preg_replace('#^public/#', '', $rel));
            if (! is_file($path)) {
                $path = public_path($rel);
            }
            if (! is_file($path)) {
                continue;
            }
            $bin = @file_get_contents($path);
            if ($bin === false || strlen($bin) < 80) {
                continue;
            }
            $processed = $this->signaturePngWithTimestamp($bin, $row->created_at);
            if ($processed === null) {
                continue;
            }
            $newRel = $this->writeEulogySignaturePng($processed);
            if ($newRel && $newRel !== $row->signature_path) {
                $row->signature_path = $newRel;
                $row->save();
                $updated++;
            }
        }

        return $updated;
    }

    public function renameEulogyAuthor($from, $to)
    {
        return FuneralEulogy::where('name', $from)->update(['name' => $to]);
    }

    protected function writeEulogySignaturePng($bin)
    {
        $dir = public_path('memorial/pangwayu/eulogies');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = 'sig_'.date('YmdHis').'_'.substr(md5($bin), 0, 8).'.png';
        file_put_contents($dir.'/'.$name, $bin);

        return 'public/memorial/pangwayu/eulogies/'.$name;
    }

    protected function signaturePixelIsInk($img, $x, $y)
    {
        $pixel = $this->signaturePixel($img, $x, $y);

        return ! $pixel['skip'];
    }

    protected function signaturePixel($img, $x, $y)
    {
        $rgba = imagecolorat($img, $x, $y);
        if (imageistruecolor($img)) {
            $a = ($rgba & 0x7F000000) >> 24;
            $r = ($rgba >> 16) & 0xFF;
            $g = ($rgba >> 8) & 0xFF;
            $b = $rgba & 0xFF;
        } else {
            $cols = imagecolorsforindex($img, $rgba);
            $a = isset($cols['alpha']) ? (int) $cols['alpha'] : 0;
            $r = (int) $cols['red'];
            $g = (int) $cols['green'];
            $b = (int) $cols['blue'];
        }
        $nearWhite = $r > 236 && $g > 236 && $b > 236;
        $skip = $a >= 118 || $nearWhite;

        return [
            'r' => $r,
            'g' => $g,
            'b' => $b,
            'a' => $a,
            'skip' => $skip,
        ];
    }

    protected function eulogyParagraphs($body)
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $body));
        if ($text === '') {
            return [];
        }
        $blocks = preg_split("/\n{2,}/", $text);
        if (count($blocks) === 1 && substr_count($text, "\n") >= 2) {
            $blocks = preg_split("/\n/", $text);
        }

        return array_values(array_filter(array_map('trim', $blocks), function ($p) {
            return $p !== '';
        }));
    }

    /**
     * Store a eulogy selfie as JPEG, never larger than 256 KB.
     *
     * @param  \Illuminate\Http\UploadedFile|null  $file
     */
    public function storeSelfie($file)
    {
        if (! $file || ! is_object($file) || ! method_exists($file, 'isValid') || ! $file->isValid()) {
            return null;
        }

        $mime = method_exists($file, 'getMimeType') ? strtolower((string) $file->getMimeType()) : '';
        $okMime = $mime === '' || strpos($mime, 'image/') === 0 || in_array($mime, ['application/octet-stream'], true);
        if (! $okMime) {
            throw new \InvalidArgumentException('Please choose a photo from your gallery.');
        }

        $bin = @file_get_contents($file->getRealPath());
        if ($bin === false || strlen($bin) < 80) {
            return null;
        }

        $maxBytes = 256 * 1024;
        $decoded = false;
        if (function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring($bin);
            if ($img) {
                $decoded = true;
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

        if (! $decoded && strlen($bin) > 512 * 1024) {
            throw new \InvalidArgumentException('Please choose a JPEG or PNG photo, or a smaller image from your gallery.');
        }

        if (strlen($bin) > $maxBytes && $decoded) {
            throw new \InvalidArgumentException('Selfie must be 256 KB or smaller.');
        }

        $dir = public_path('memorial/pangwayu/eulogies');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = 'selfie_'.date('YmdHis').'_'.substr(md5($bin), 0, 8).'.jpg';
        file_put_contents($dir.'/'.$name, $bin);

        return 'public/memorial/pangwayu/eulogies/'.$name;
    }

    public function openGiftItem()
    {
        $campaign = $this->campaign();
        if (! $campaign) {
            return null;
        }

        $other = FuneralItem::where('campaign_id', $campaign->id)
            ->where('is_open', 1)
            ->where('name', 'Other')
            ->first();
        if ($other) {
            return $other;
        }

        return FuneralItem::where('campaign_id', $campaign->id)
            ->where('is_open', 1)
            ->orderByDesc('id')
            ->first();
    }

    public function rememberPhotos()
    {
        $files = [
            'memorial/pangwayu/remember-landing.jpg',
            'memorial/pangwayu/remember-red.jpg',
            'memorial/pangwayu/remember-cbc-heaven.jpg',
            'memorial/pangwayu/remember-jesus.jpg',
        ];

        return array_map(function ($rel) {
            $path = public_path($rel);
            $ver = is_file($path) ? filemtime($path) : time();

            return asset('public/'.$rel).'?v='.$ver;
        }, $files);
    }

    public function notifyEulogy(FuneralEulogy $eulogy)
    {
        $pageUrl = route('funeral.pangwayu.remember').'#eulogies';
        $familyMsg = WhatsAppMessage::funeralEulogyThanks($eulogy->name, $eulogy->body, $pageUrl);
        if (strlen($familyMsg) > 3900) {
            $familyMsg = WhatsAppMessage::funeralEulogyThanks(
                $eulogy->name,
                $eulogy->excerpt(2800)."\n\n_(Full copy attached as PDF.)_",
                $pageUrl
            );
        }
        $adminMsg = WhatsAppMessage::funeralEulogyAdmin(
            $eulogy->name,
            WhatsAppPhone::display($eulogy->phone),
            $eulogy->excerpt(700),
            $pageUrl
        );

        $whatsapp = app(BeyondWasenderService::class);
        try {
            $whatsapp->sendText($eulogy->phone, $familyMsg);
        } catch (\Throwable $e) {
            Log::info('Funeral eulogy WhatsApp family failed: '.$e->getMessage());
        }

        $pdfPath = $this->eulogyCopyPdf($eulogy);
        $staffPhones = $this->staffPhones($this->campaign());
        $writerPhone = $eulogy->phone;
        $id = $eulogy->id;
        app()->terminating(function () use ($whatsapp, $staffPhones, $adminMsg, $id, $pdfPath, $writerPhone) {
            if ($pdfPath) {
                try {
                    usleep(5500000);
                    $whatsapp->sendDocument(
                        $writerPhone,
                        $pdfPath,
                        'Eulogy-Pa-Ngwayu-Francis.pdf',
                        'A PDF copy of the eulogy you wrote for Pa Ngwayu Francis.'
                    );
                } catch (\Throwable $e) {
                    Log::info('Funeral eulogy WhatsApp PDF copy failed: '.$e->getMessage(), ['eulogy' => $id]);
                }
            }
            $this->sendStaffCopies($whatsapp, $staffPhones, $adminMsg, ['eulogy' => $id]);
        });
    }

    protected function eulogyCopyPdf(FuneralEulogy $eulogy)
    {
        try {
            $dir = storage_path('app/eulogies');
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $path = $dir.'/eulogy-'.$eulogy->id.'.pdf';
            \PDF::loadView('beyond.memorial.eulogy-pdf', ['eulogy' => $eulogy])
                ->setPaper('a4')
                ->save($path);

            return is_file($path) ? $path : null;
        } catch (\Throwable $e) {
            Log::info('Funeral eulogy PDF copy failed: '.$e->getMessage(), ['eulogy' => $eulogy->id]);

            return null;
        }
    }

    public function handlePaymentCallback($status, $reference, $externalReference)
    {
        $parts = explode(',', (string) $externalReference);
        $pledgeId = isset($parts[0]) ? (int) $parts[0] : 0;
        $back = isset($parts[2]) && $parts[2] === 'remember' ? 'remember' : null;
        $pledge = FuneralPledge::with(['item', 'campaign'])->find($pledgeId);
        if (! $pledge) {
            return ['ok' => false, 'redirect' => $this->publicPayUrl('failed', $back)];
        }

        $status = strtoupper((string) $status);
        if ($status === 'SUCCESSFUL') {
            $pledge->status = FuneralPledge::STATUS_PAID;
            $pledge->kind = FuneralPledge::KIND_PAYMENT;
            $pledge->campay_reference = $reference;
            $pledge->paid_at = Carbon::now();
            $pledge->save();
            $this->notifyPledge($pledge->fresh(['item', 'campaign']));

            return ['ok' => true, 'redirect' => $this->publicPayUrl('ok', $back)];
        }

        if ($status === 'PENDING') {
            $pledge->campay_reference = $reference;
            $pledge->save();

            return ['ok' => false, 'redirect' => $this->publicPayUrl('pending', $back)];
        }

        $pledge->status = FuneralPledge::STATUS_FAILED;
        $pledge->campay_reference = $reference;
        $pledge->save();

        return ['ok' => false, 'redirect' => $this->publicPayUrl('failed', $back)];
    }

    public function notifyPledge(FuneralPledge $pledge)
    {
        $item = $pledge->item;
        $campaign = $pledge->campaign;
        $pageUrl = route('funeral.pangwayu');
        $remaining = $item ? $item->fresh('pledges')->remainingAmount() : null;
        $remainingLabel = $remaining === null ? 'Open' : number_format($remaining).' XAF';
        $paid = $pledge->status === FuneralPledge::STATUS_PAID;
        $itemName = $item ? $item->name : 'Item';
        $funeralDate = $campaign && $campaign->funeral_at
            ? $campaign->funeral_at->format('d F Y')
            : '26 September 2026';

        $familyMsg = $paid
            ? WhatsAppMessage::funeralPledgePaid(
                $pledge->name,
                $itemName,
                $pledge->amount,
                $remainingLabel,
                $funeralDate,
                $pageUrl
            )
            : WhatsAppMessage::funeralPledgeThanks(
                $pledge->name,
                $itemName,
                $pledge->amount,
                $remainingLabel,
                $funeralDate,
                $pageUrl
            );

        $adminMsg = WhatsAppMessage::funeralPledgeAdmin(
            $pledge->name,
            WhatsAppPhone::display($pledge->phone),
            $itemName,
            $pledge->amount,
            $paid ? 'paid' : 'pledged',
            $pageUrl
        );

        $whatsapp = app(BeyondWasenderService::class);
        try {
            $whatsapp->sendText($pledge->phone, $familyMsg);
        } catch (\Throwable $e) {
            Log::info('Funeral pledge WhatsApp family failed: '.$e->getMessage());
        }

        $staffPhones = $this->staffPhones($campaign);
        $pledgeId = $pledge->id;
        app()->terminating(function () use ($whatsapp, $staffPhones, $adminMsg, $pledgeId) {
            $this->sendStaffCopies($whatsapp, $staffPhones, $adminMsg, ['pledge' => $pledgeId]);
        });
    }

    protected function funeralAtIso($campaign)
    {
        $at = $campaign->funeral_at instanceof Carbon
            ? $campaign->funeral_at
            : Carbon::parse($campaign->funeral_at);
        $wat = Carbon::create($at->year, $at->month, $at->day, 0, 0, 0, 'Africa/Douala');

        return $wat->toIso8601String();
    }

    public function adminPhone($campaign = null)
    {
        $fromEnv = preg_replace('/\D/', '', (string) config('services.funeral_pledge.admin_phone', ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }
        if ($campaign && $campaign->admin_phone) {
            return preg_replace('/\D/', '', (string) $campaign->admin_phone);
        }

        return '237677318405';
    }

    public function staffPhones($campaign = null)
    {
        $phones = [$this->adminPhone($campaign)];
        $raw = config('services.funeral_pledge.cc_phones', '237677124575');
        foreach (preg_split('/[,\s]+/', (string) $raw) as $phone) {
            $digits = preg_replace('/\D/', '', $phone);
            if ($digits !== '') {
                $phones[] = $digits;
            }
        }

        return array_values(array_unique($phones));
    }

    protected function sendStaffCopies($whatsapp, array $phones, $message, array $context = [])
    {
        foreach ($phones as $phone) {
            try {
                usleep(5500000);
                $whatsapp->sendText($phone, $message);
            } catch (\Throwable $e) {
                Log::info('Funeral WhatsApp staff copy failed: '.$e->getMessage(), $context + ['phone' => $phone]);
            }
        }
    }
}
