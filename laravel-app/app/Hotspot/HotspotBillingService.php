<?php

namespace App\Hotspot;

use App\Account;
use App\Biller;
use App\Customer;
use App\GeneralSetting;
use App\Jobs\SendHotspotWhatsApp;
use App\Payment;
use App\Sale;
use App\Services\CampayPayoutService;
use App\Services\PeopleDirectoryService;
use App\Services\StripeCheckoutService;
use App\User;
use App\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HotspotBillingService
{
    public function startMoMo(HotspotPackage $package, Customer $customer, $phone, $locationId)
    {
        $campay = app(CampayPayoutService::class);
        $from = $campay->momoNumber($phone);
        if (! $from) {
            throw new \InvalidArgumentException('This number is not an MTN or Orange Cameroon number. Pay with Visa.');
        }
        $payment = $this->openPayment($package, $customer, $from, $locationId, 'momo', 'campay');
        $body = $campay->collect($from, (int) $package->price, $payment->provider_reference, 'Wi-Fi '.$package->name);
        $status = strtoupper((string) (is_array($body) && isset($body['status']) ? $body['status'] : ''));
        if (! is_array($body) || empty($body['reference']) || $status === 'FAILED') {
            $payment->status = 'failed';
            $payment->error = 'Mobile Money could not be started.';
            $payment->save();
            throw new \RuntimeException($payment->error);
        }
        $payment->provider_reference = (string) $body['reference'];
        $payment->save();

        return $payment;
    }

    public function startCard(HotspotPackage $package, Customer $customer, $phone, $locationId)
    {
        $payment = $this->openPayment($package, $customer, $phone, $locationId, 'visa', 'stripe');
        $returnUrl = route('hotspot.waiting', ['token' => $payment->public_token]);
        $checkout = app(StripeCheckoutService::class)->checkout(
            'Wi-Fi '.$package->name,
            (int) $package->price,
            $returnUrl,
            $returnUrl,
            ['hotspot_payment' => (string) $payment->id]
        );
        if (empty($checkout['ok']) || empty($checkout['id'])) {
            $payment->status = 'failed';
            $payment->error = isset($checkout['error']) ? $checkout['error'] : 'Card checkout could not be opened.';
            $payment->save();
            throw new \RuntimeException($payment->error);
        }
        $payment->provider_reference = (string) $checkout['id'];
        $payment->save();

        return ['payment' => $payment, 'url' => $checkout['url']];
    }

    public function settle(HotspotPayment $payment)
    {
        if ($payment->status === 'paid' && $payment->subscription_id) {
            return 'paid';
        }
        if ($payment->status === 'failed') {
            return 'failed';
        }
        if ($payment->provider === 'stripe') {
            if (! $this->cardConfirmed($payment)) {
                return 'pending';
            }
        } elseif ($payment->provider === 'campay') {
            $outcome = app(CampayPayoutService::class)->collectionOutcome(
                $payment->provider_reference,
                $payment->amount,
                $payment->phone
            );
            if ($outcome['state'] === 'failed') {
                $payment->status = 'failed';
                $payment->error = 'The payment was not approved.';
                $payment->save();

                return 'failed';
            }
            if (! HotspotLimits::mayActivate(! empty($outcome['confirmed']) && $outcome['state'] === 'paid')) {
                return 'pending';
            }
        } else {
            return $payment->status === 'paid' ? 'paid' : 'pending';
        }
        $this->activate($payment);

        return 'paid';
    }

    public function activate(HotspotPayment $payment)
    {
        $created = false;
        $subscription = DB::transaction(function () use ($payment, &$created) {
            $locked = HotspotPayment::where('id', $payment->id)->lockForUpdate()->first();
            if (! $locked) {
                return null;
            }
            if ($locked->status === 'paid' && $locked->subscription_id) {
                return HotspotSubscription::find($locked->subscription_id);
            }
            $package = HotspotPackage::find($locked->package_id);
            if (! $package) {
                return null;
            }
            $subscription = $this->newSubscription($package, $locked->customer_id, $locked->location_id);
            $locked->status = 'paid';
            $locked->paid_at = now();
            $locked->subscription_id = $subscription->id;
            $locked->error = null;
            $locked->save();
            $created = true;

            return $subscription;
        });
        if (! $subscription || ! $created) {
            return $subscription;
        }
        $payment->refresh();
        $this->recordSale($payment);
        app(HotspotProvisioner::class)->grant($subscription);
        app(HotspotNotifier::class)->receipt($subscription, $payment);

        return $subscription;
    }

    public function redeem(HotspotVoucher $voucher, Customer $customer, $locationId)
    {
        $subscription = DB::transaction(function () use ($voucher, $customer, $locationId) {
            $locked = HotspotVoucher::where('id', $voucher->id)->lockForUpdate()->first();
            if (! $locked || $locked->state !== 'available') {
                throw new \RuntimeException('This voucher cannot be used.');
            }
            $package = HotspotPackage::find($locked->package_id);
            if (! $package || ! $package->active) {
                throw new \RuntimeException('This voucher package is not available.');
            }
            $subscription = $this->newSubscription($package, $customer->id, $locationId ?: $package->location_id);
            $payment = new HotspotPayment();
            $payment->customer_id = $customer->id;
            $payment->package_id = $package->id;
            $payment->location_id = $subscription->location_id;
            $payment->subscription_id = $subscription->id;
            $payment->amount = (int) $package->price;
            $payment->currency = $package->currency ?: 'XAF';
            $payment->method = 'voucher';
            $payment->provider = 'voucher';
            $payment->provider_reference = 'voucher-'.$locked->id.'-'.$locked->code;
            $payment->public_token = Str::random(40);
            $payment->phone = $customer->phone_number;
            $payment->status = 'paid';
            $payment->paid_at = now();
            $payment->save();
            $locked->state = 'redeemed';
            $locked->redeemed_at = now();
            $locked->subscription_id = $subscription->id;
            $locked->save();

            return $subscription;
        });
        app(HotspotProvisioner::class)->grant($subscription);
        $payment = HotspotPayment::where('subscription_id', $subscription->id)->first();
        if ($payment) {
            $this->recordSale($payment);
            app(HotspotNotifier::class)->receipt($subscription, $payment);
        }
        HotspotAuditLog::write('voucher.redeem', 'Redeemed voucher for '.$customer->phone_number);

        return $subscription;
    }

    public function customer($name, $phone)
    {
        $found = app(PeopleDirectoryService::class)->findOrCreateCustomerQuick([
            'name' => $name,
            'phone' => $phone,
        ]);

        return $found['customer'];
    }

    protected function openPayment(HotspotPackage $package, Customer $customer, $phone, $locationId, $method, $provider)
    {
        $payment = new HotspotPayment();
        $payment->customer_id = $customer->id;
        $payment->package_id = $package->id;
        $payment->location_id = $locationId ?: $package->location_id;
        $payment->amount = (int) $package->price;
        $payment->currency = 'XAF';
        $payment->method = $method;
        $payment->provider = $provider;
        $payment->provider_reference = 'hs-'.date('YmdHis').'-'.Str::lower(Str::random(8));
        $payment->public_token = Str::random(40);
        $payment->phone = $phone;
        $payment->status = 'pending';
        $payment->save();

        return $payment;
    }

    protected function newSubscription(HotspotPackage $package, $customerId, $locationId)
    {
        $subscription = new HotspotSubscription();
        $subscription->customer_id = $customerId;
        $subscription->location_id = $locationId ?: $package->location_id;
        $subscription->package_id = $package->id;
        $subscription->username = $this->username();
        $subscription->access_secret = HotspotLimits::voucherCode();
        $subscription->state = 'active';
        $subscription->starts_at = now();
        $subscription->expires_at = now()->addSeconds((int) $package->validity_seconds);
        $subscription->data_cap_bytes = $package->data_bytes;
        $subscription->data_used_bytes = 0;
        $subscription->connected_seconds = 0;
        $subscription->save();

        return $subscription;
    }

    protected function username()
    {
        do {
            $username = 'u'.date('ymdHis').random_int(10, 99);
        } while (HotspotSubscription::where('username', $username)->exists());

        return $username;
    }

    protected function recordSale(HotspotPayment $payment)
    {
        if ($payment->sale_id) {
            return;
        }
        try {
            $customer = Customer::find($payment->customer_id);
            $package = HotspotPackage::find($payment->package_id);
            $userId = User::where('role_id', 1)->value('id') ?: User::query()->value('id');
            $sale = new Sale();
            $sale->reference_no = 'hs-'.$payment->id.'-'.date('ymd');
            $sale->user_id = $userId;
            $sale->customer_id = $customer ? $customer->id : null;
            $sale->warehouse_id = Warehouse::query()->value('id');
            $sale->biller_id = Biller::query()->value('id');
            $sale->item = 1;
            $sale->total_qty = 1;
            $sale->total_discount = 0;
            $sale->total_tax = 0;
            $sale->total_price = (int) $payment->amount;
            $sale->order_tax_rate = 0;
            $sale->order_tax = 0;
            $sale->order_discount = 0;
            $sale->shipping_cost = 0;
            $sale->grand_total = (int) $payment->amount;
            $sale->paid_amount = (int) $payment->amount;
            $sale->sale_status = 1;
            $sale->payment_status = 4;
            $sale->sale_note = 'Hotspot '.($package ? $package->name : 'access');
            $sale->save();
            $account = Account::where('is_default', true)->first();
            $row = new Payment();
            $row->sale_id = $sale->id;
            $row->user_id = $userId;
            if ($account) {
                $row->account_id = $account->id;
            }
            $row->amount = (int) $payment->amount;
            $row->change = 0;
            $row->paying_method = $payment->method === 'visa' ? 'Credit Card' : ($payment->method === 'voucher' ? 'Voucher' : 'Mobile Money');
            $row->payment_reference = 'hs-'.$payment->provider_reference;
            $row->payment_note = 'Hotspot '.$payment->method;
            $row->save();
            $payment->sale_id = $sale->id;
            $payment->save();
        } catch (\Throwable $e) {
            \Log::warning('Hotspot sale was not recorded', ['payment' => $payment->id, 'error' => substr($e->getMessage(), 0, 180)]);
        }
    }

    protected function cardConfirmed(HotspotPayment $payment)
    {
        $secret = app(StripeCheckoutService::class)->secret();
        $reference = (string) $payment->provider_reference;
        if ($secret === '' || strpos($reference, 'cs_') !== 0) {
            return false;
        }
        \Stripe\Stripe::setApiKey($secret);
        try {
            $session = \Stripe\Checkout\Session::retrieve($reference);
        } catch (\Throwable $e) {
            return false;
        }
        $paid = isset($session->payment_status) && $session->payment_status === 'paid';
        $amount = isset($session->amount_total) ? (int) $session->amount_total : 0;
        $currency = strtoupper((string) (isset($session->currency) ? $session->currency : ''));

        return $paid && $amount === (int) $payment->amount && $currency === 'XAF';
    }

    public function supportPhone()
    {
        $setting = GeneralSetting::query()->first();
        if ($setting) {
            foreach (['phone', 'company_phone', 'whatsapp'] as $column) {
                $value = preg_replace('/\D+/', '', (string) ($setting->{$column} ?? ''));
                if (strlen($value) >= 9) {
                    return $value;
                }
            }
        }

        return '237675321739';
    }
}
