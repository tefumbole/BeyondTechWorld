<?php

namespace App\Services;

use App\FuneralCampaign;
use App\FuneralItem;
use App\FuneralPledge;
use App\Support\WhatsAppMessage;
use App\Support\WhatsAppPhone;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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

        return [
            'campaign' => $campaign,
            'items' => $items,
            'groups' => $groups,
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

    public function paymentLink(FuneralPledge $pledge)
    {
        $token = config('services.campay.token') ?: getenv('CAMPAY_TOKEN') ?: getenv('MOMO_TOKEN');
        if (! $token) {
            throw new \RuntimeException('Mobile money is not configured.');
        }

        $phone = preg_replace('/\D/', '', (string) $pledge->phone);
        $callback = route('funeral.pangwayu.payment');
        $fail = route('funeral.pangwayu', ['pay' => 'failed']);
        $payload = json_encode([
            'amount' => (string) $pledge->amount,
            'from' => $phone,
            'currency' => 'XAF',
            'external_reference' => $pledge->id.','.$fail,
            'redirect_url' => $callback,
            'payment_options' => 'MOMO,CARD',
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
            throw new \RuntimeException('Could not start MoMo / card payment.');
        }

        return $decoded['link'];
    }

    public function handlePaymentCallback($status, $reference, $externalReference)
    {
        $parts = explode(',', (string) $externalReference);
        $pledgeId = isset($parts[0]) ? (int) $parts[0] : 0;
        $pledge = FuneralPledge::with(['item', 'campaign'])->find($pledgeId);
        if (! $pledge) {
            return ['ok' => false, 'redirect' => route('funeral.pangwayu', ['pay' => 'failed'])];
        }

        $status = strtoupper((string) $status);
        if ($status === 'SUCCESSFUL') {
            $pledge->status = FuneralPledge::STATUS_PAID;
            $pledge->kind = FuneralPledge::KIND_PAYMENT;
            $pledge->campay_reference = $reference;
            $pledge->paid_at = Carbon::now();
            $pledge->save();
            $this->notifyPledge($pledge->fresh(['item', 'campaign']));

            return ['ok' => true, 'redirect' => route('funeral.pangwayu', ['pay' => 'ok'])];
        }

        if ($status === 'PENDING') {
            $pledge->campay_reference = $reference;
            $pledge->save();

            return ['ok' => false, 'redirect' => route('funeral.pangwayu', ['pay' => 'pending'])];
        }

        $pledge->status = FuneralPledge::STATUS_FAILED;
        $pledge->campay_reference = $reference;
        $pledge->save();

        return ['ok' => false, 'redirect' => route('funeral.pangwayu', ['pay' => 'failed'])];
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
