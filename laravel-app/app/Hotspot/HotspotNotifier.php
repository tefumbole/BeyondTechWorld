<?php

namespace App\Hotspot;

use App\GeneralSetting;
use App\Jobs\SendHotspotWhatsApp;

class HotspotNotifier
{
    public function receipt(HotspotSubscription $subscription, HotspotPayment $payment)
    {
        if ($subscription->receipt_sent_at) {
            return;
        }
        $subscription->receipt_sent_at = now();
        $subscription->save();
        $this->send($subscription, 'receipt', $payment);
    }

    public function once(HotspotSubscription $subscription, $key, $column)
    {
        if ($subscription->{$column}) {
            return;
        }
        $subscription->{$column} = now();
        $subscription->save();
        $this->send($subscription, $key, HotspotPayment::where('subscription_id', $subscription->id)->first());
    }

    public function send(HotspotSubscription $subscription, $key, HotspotPayment $payment = null)
    {
        $phone = $payment ? $payment->phone : null;
        if (! $phone && $subscription->customer) {
            $phone = $subscription->customer->phone_number;
        }
        if (! $phone) {
            return;
        }
        $template = HotspotMessageTemplate::where('key', $key)->first();
        $body = $template ? $template->body : (HotspotMessageTemplate::defaults()[$key]['body'] ?? '');
        $package = $subscription->package;
        $company = optional(GeneralSetting::query()->first())->site_title ?: 'Beyond Tech World';
        $replace = [
            '{company}' => $company,
            '{package}' => $package ? $package->name : 'Wi-Fi',
            '{amount}' => $payment ? number_format((int) $payment->amount, 0, '.', ' ') : '',
            '{username}' => $subscription->username,
            '{password}' => (string) $subscription->access_secret,
            '{expires}' => $subscription->expires_at ? $subscription->expires_at->format('d M Y H:i') : '',
            '{support}' => 'https://wa.me/'.app(HotspotBillingService::class)->supportPhone(),
            '{portal}' => url('/hotspot'),
        ];
        SendHotspotWhatsApp::dispatch($phone, strtr($body, $replace));
    }

    public function ensureTemplates()
    {
        foreach (HotspotMessageTemplate::defaults() as $key => $template) {
            if (HotspotMessageTemplate::where('key', $key)->exists()) {
                continue;
            }
            $row = new HotspotMessageTemplate();
            $row->key = $key;
            $row->body = $template['body'];
            $row->hours_before = $template['hours_before'];
            $row->save();
        }
    }
}
