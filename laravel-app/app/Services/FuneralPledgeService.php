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
                    'has_signature' => ! empty($row->signature_path),
                    'signature' => $row->signature_path ? asset($row->signature_path) : '',
                    'has_selfie' => ! empty($row->selfie_path),
                    'selfie' => $row->selfie_path ? asset($row->selfie_path) : '',
                    'when' => $row->created_at ? $row->created_at->format('d M Y') : '',
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
        if (! preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/=\s]+)$#', $dataUri, $m)) {
            return null;
        }
        $bin = base64_decode($m[2], true);
        if ($bin === false || strlen($bin) < 80 || strlen($bin) > 800000) {
            return null;
        }
        $dir = public_path('memorial/pangwayu/eulogies');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = 'sig_'.date('YmdHis').'_'.substr(md5($bin), 0, 8).'.png';
        file_put_contents($dir.'/'.$name, $bin);

        return 'public/memorial/pangwayu/eulogies/'.$name;
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
        $adminPhone = $this->adminPhone($this->campaign());
        $writerPhone = $eulogy->phone;
        $id = $eulogy->id;
        app()->terminating(function () use ($whatsapp, $adminPhone, $adminMsg, $id, $pdfPath, $writerPhone) {
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
            try {
                usleep(5500000);
                $whatsapp->sendText($adminPhone, $adminMsg);
            } catch (\Throwable $e) {
                Log::info('Funeral eulogy WhatsApp admin failed: '.$e->getMessage(), ['eulogy' => $id]);
            }
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

        $adminPhone = $this->adminPhone($campaign);
        $pledgeId = $pledge->id;
        app()->terminating(function () use ($whatsapp, $adminPhone, $adminMsg, $pledgeId) {
            try {
                usleep(5500000);
                $whatsapp->sendText($adminPhone, $adminMsg);
            } catch (\Throwable $e) {
                Log::info('Funeral pledge WhatsApp admin failed: '.$e->getMessage(), ['pledge' => $pledgeId]);
            }
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
}
