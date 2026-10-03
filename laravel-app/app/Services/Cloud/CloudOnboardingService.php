<?php

namespace App\Services\Cloud;

use App\Cloud\CloudMembershipRole;
use App\Cloud\CloudMembershipStatus;
use App\Cloud\CloudModuleCode;
use App\Cloud\CloudPlan;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantMembership;
use App\Cloud\CloudTenantSetting;
use App\Cloud\CloudTenantStatus;
use App\Cloud\CloudTenantType;
use App\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;

/**
 * Public company creation. Trials go through CloudSubscriptionService.
 * The browser total is ignored. Payment is not collected.
 */
class CloudOnboardingService
{
    public function publicModules()
    {
        return [
            CloudModuleCode::MESSAGING,
            CloudModuleCode::SALES_INVOICES,
            CloudModuleCode::RENTALS,
            CloudModuleCode::QUOTATIONS,
            CloudModuleCode::DIGITAL_INVITATIONS,
        ];
    }

    public function plans()
    {
        return CloudPlan::with('module')
            ->where('active', true)
            ->whereHas('module', function ($query) {
                $query->whereIn('code', $this->publicModules())->where('active', true);
            })
            ->orderBy('sort_order')
            ->get();
    }

    public function quote(array $codes)
    {
        $codes = array_values(array_unique($codes));
        $lines = [];
        $total = 0.0;
        $currency = 'XAF';
        $trial = null;
        foreach ($this->plans() as $plan) {
            if (! in_array($plan->module->code, $codes, true)) {
                continue;
            }
            $lines[] = [
                'code' => $plan->module->code,
                'name' => $plan->name,
                'price' => (float) $plan->price,
                'currency' => $plan->currency,
                'trial_value' => (int) $plan->trial_value,
                'trial_unit' => $plan->trial_unit,
            ];
            $total += (float) $plan->price;
            $currency = $plan->currency ?: $currency;
            $trial = (int) $plan->trial_value.' '.$plan->trial_unit;
        }

        return [
            'lines' => $lines,
            'monthly_total' => $total,
            'currency' => $currency,
            'due_today' => 0,
            'trial' => $trial,
        ];
    }

    public function register(array $input)
    {
        $replay = $this->completed($input);
        if ($replay) {
            return $replay;
        }
        $email = strtolower(trim($input['email']));
        if (User::where('email', $email)->exists()) {
            throw new CloudExistingAccountException('That email already has an account. Sign in to add a company.');
        }

        $pair = $this->withToken($input, function () use ($input, $email) {
            return DB::transaction(function () use ($input, $email) {
                $attributes = [
                    'name' => trim($input['first_name'].' '.$input['last_name']),
                    'email' => $email,
                    'phone' => $input['phone'],
                    'password' => Hash::make($input['password']),
                    'role_id' => 5,
                    'is_active' => 1,
                    'company_name' => $input['company_name'],
                ];
                if (Schema::hasColumn('users', 'username') && ! empty($input['username'])) {
                    $attributes['username'] = $input['username'];
                }
                if (Schema::hasColumn('users', 'is_deleted')) {
                    $attributes['is_deleted'] = 0;
                }
                $user = User::create($attributes);

                return [$user, $this->createCompany($user, $input)];
            });
        });
        $this->welcome($pair[0], $pair[1]);
        app(CloudCompanyAccess::class)->ensureRole($pair[0], true);

        return $pair;
    }

    public function addCompany(User $user, array $input)
    {
        return $this->withToken($input, function () use ($user, $input) {
            return DB::transaction(function () use ($user, $input) {
                $tenant = $this->createCompany($user, $input);

                return [$user, $tenant];
            });
        });
    }

    public function checklist(CloudTenant $tenant)
    {
        $codes = [];
        foreach ($tenant->subscriptions()->with('plan.module')->get() as $subscription) {
            if ($subscription->plan && $subscription->plan->module) {
                $codes[] = $subscription->plan->module->code;
            }
        }
        $sales = in_array(CloudModuleCode::SALES_INVOICES, $codes, true);
        $rentals = in_array(CloudModuleCode::RENTALS, $codes, true);
        $messaging = in_array(CloudModuleCode::MESSAGING, $codes, true);
        $quotations = in_array(CloudModuleCode::QUOTATIONS, $codes, true);
        $invitations = in_array(CloudModuleCode::DIGITAL_INVITATIONS, $codes, true);
        $context = app(CloudTenantContext::class);
        $previous = $context->tenant();
        $context->set($tenant);
        $hasProduct = Schema::hasTable('products') && \App\Product::query()->exists();
        $hasCustomer = Schema::hasTable('customers') && \App\Customer::query()->exists();
        $hasQuotation = Schema::hasTable('quotations') && \App\Quotation::query()->exists();
        $hasRentRate = false;
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'rent_price_per_day')) {
            $hasRentRate = \App\Product::query()->where(function ($query) {
                $query->where('rent_price_per_hour', '>', 0)
                    ->orWhere('rent_price_per_day', '>', 0)
                    ->orWhere('rent_price_per_month', '>', 0);
            })->exists();
        }
        if ($previous) {
            $context->set($previous);
        } else {
            $context->clear();
        }
        $infoDone = trim((string) $tenant->address) !== '' || trim((string) $tenant->city) !== '' || trim((string) $tenant->phone) !== '';
        $steps = [
            ['label' => 'Upload company logo', 'done' => $tenant->logo_path ? true : false, 'show' => true],
            ['label' => 'Complete company information', 'done' => $infoDone, 'show' => true],
        ];
        if ($sales || $rentals || $quotations) {
            $steps[] = ['label' => 'Add first product/service', 'done' => $hasProduct, 'show' => true];
            $steps[] = ['label' => 'Add first customer', 'done' => $hasCustomer, 'show' => true];
        }
        if ($sales || $quotations) {
            $steps[] = ['label' => 'Create first quotation', 'done' => $hasQuotation, 'show' => true];
        }
        if ($invitations) {
            $steps[] = ['label' => 'Create the first digital invitation', 'done' => false, 'show' => true];
        }
        if ($rentals) {
            $steps[] = ['label' => 'Add rental inventory', 'done' => $hasProduct, 'show' => true];
            $steps[] = ['label' => 'Set rental rates', 'done' => $hasRentRate, 'show' => true];
        }
        if ($messaging) {
            $steps[] = ['label' => 'Review Messaging Hub', 'done' => false, 'show' => true];
        }

        return $steps;
    }

    protected function createCompany(User $user, array $input)
    {
        $quote = $this->quote(isset($input['modules']) ? $input['modules'] : []);
        if (count($quote['lines']) < 1) {
            throw new \RuntimeException('Choose at least one service.');
        }
        if (isset($input['type']) && $input['type'] === CloudTenantType::INTERNAL) {
            throw new \RuntimeException('A public registration cannot create the internal company.');
        }

        $accountPhone = isset($input['phone']) && $input['phone'] !== '' ? $input['phone'] : $user->phone;
        $companyPhone = isset($input['company_phone']) && $input['company_phone'] !== ''
            ? $input['company_phone']
            : $accountPhone;
        $tenant = CloudTenant::create([
            'name' => $input['company_name'],
            'legal_name' => isset($input['legal_name']) && $input['legal_name'] !== '' ? $input['legal_name'] : $input['company_name'],
            'system_name' => isset($input['system_name']) && $input['system_name'] !== '' ? $input['system_name'] : $input['company_name'],
            'slug' => $this->uniqueSlug($input['company_name']),
            'type' => CloudTenantType::CUSTOMER,
            'status' => CloudTenantStatus::ACTIVE,
            'email' => isset($input['company_email']) && $input['company_email'] !== '' ? $input['company_email'] : $user->email,
            'phone' => $user->phone ?: $accountPhone,
            'country' => isset($input['country']) ? $input['country'] : null,
            'city' => isset($input['city']) ? $input['city'] : null,
            'address' => isset($input['address']) ? $input['address'] : null,
            'currency' => isset($input['currency']) && $input['currency'] !== '' ? strtoupper($input['currency']) : 'XAF',
            'timezone' => isset($input['timezone']) && $input['timezone'] !== '' ? $input['timezone'] : 'Africa/Douala',
            'created_by' => $user->id,
        ]);
        CloudTenantMembership::create([
            'cloud_tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'membership_role' => CloudMembershipRole::OWNER,
            'is_owner' => true,
            'status' => CloudMembershipStatus::ACTIVE,
            'joined_at' => now(),
        ]);
        if ($companyPhone !== $tenant->phone) {
            CloudTenantSetting::create([
                'cloud_tenant_id' => $tenant->id,
                'key' => 'company_phone',
                'value' => $companyPhone,
                'type' => 'string',
            ]);
        }
        CloudTenantSetting::create([
            'cloud_tenant_id' => $tenant->id,
            'key' => 'account_kind',
            'value' => isset($input['account_kind']) && $input['account_kind'] === 'personal' ? 'personal' : 'company',
            'type' => 'string',
        ]);
        CloudTenantSetting::create([
            'cloud_tenant_id' => $tenant->id,
            'key' => 'signup_intent',
            'value' => isset($input['start_mode']) && $input['start_mode'] === 'pay' ? 'pay' : 'trial',
            'type' => 'string',
        ]);
        $this->storeSignature($tenant, isset($input['signature']) ? $input['signature'] : '');
        $subscriptions = app(CloudSubscriptionService::class);
        foreach ($quote['lines'] as $line) {
            $plan = CloudPlan::whereHas('module', function ($query) use ($line) {
                $query->where('code', $line['code']);
            })->where('active', true)->first();
            $subscriptions->startTrial($tenant, $plan, $user->id);
        }

        return $tenant;
    }

    protected function storeSignature(CloudTenant $tenant, $data)
    {
        if (! is_string($data) || strpos($data, 'data:image/png;base64,') !== 0) {
            return;
        }
        $binary = base64_decode(substr($data, 22), true);
        if ($binary === false || strlen($binary) < 80 || strlen($binary) > 400000) {
            return;
        }
        $dir = storage_path('app/cloud-signatures');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir.'/'.$tenant->id.'.png', $binary);
        CloudTenantSetting::create([
            'cloud_tenant_id' => $tenant->id,
            'key' => 'signup_signature',
            'value' => 'cloud-signatures/'.$tenant->id.'.png',
            'type' => 'string',
        ]);
    }

    protected function withToken(array $input, callable $callback)
    {
        $token = isset($input['onboard_token']) ? (string) $input['onboard_token'] : '';
        $doneKey = 'cloud-onboard-done:'.$token;
        $done = $this->completed($input);
        if ($done) {
            return $done;
        }
        if ($token === '' || ! hash_equals((string) session('cloud_onboard_token'), $token)) {
            throw new \RuntimeException('This registration form has expired. Open it again.');
        }
        if (! Cache::add('cloud-onboard-lock:'.$token, 1, 2)) {
            throw new \RuntimeException('This registration is already being submitted.');
        }
        $result = $callback();
        Cache::put($doneKey, [
            'user_id' => $result[0]->id,
            'tenant_id' => $result[1]->id,
        ], 60);
        session()->forget('cloud_onboard_token');

        return $result;
    }

    protected function completed(array $input)
    {
        $token = isset($input['onboard_token']) ? (string) $input['onboard_token'] : '';
        $done = $token !== '' ? Cache::get('cloud-onboard-done:'.$token) : null;
        if (! is_array($done) || ! isset($done['user_id'], $done['tenant_id'])) {
            return null;
        }

        return [User::find($done['user_id']), CloudTenant::find($done['tenant_id'])];
    }

    public function uniqueSlug($name)
    {
        $base = CloudSlug::fromName($name);
        if (CloudSlug::isReserved($base)) {
            $base .= '-company';
        }
        $slug = $base;
        $n = 2;
        while (CloudTenant::where('slug', $slug)->exists() || CloudSlug::isReserved($slug)) {
            $slug = $base.'-'.$n;
            $n++;
        }

        return $slug;
    }

    protected function welcome(User $user, CloudTenant $tenant)
    {
        try {
            Mail::raw(
                config('cloud.trial_welcome').' '.$tenant->system_name.' is ready.',
                function ($message) use ($user) {
                    $message->to($user->email)->subject('Your company trial has started');
                }
            );
        } catch (\Throwable $e) {
            // Platform mail must not remove a company that is already saved.
        }
        $this->notifyPlatform($user, $tenant);
    }

    protected function notifyPlatform(User $user, CloudTenant $tenant)
    {
        try {
            $admins = User::where('role_id', 1)->where('is_active', 1)->pluck('email')->filter()->all();
            if (! $admins) {
                return;
            }
            $modules = [];
            foreach ($tenant->subscriptions()->with('plan.module')->get() as $subscription) {
                if ($subscription->plan && $subscription->plan->module) {
                    $modules[] = $subscription->plan->module->code.' '.$subscription->status;
                }
            }
            $body = "A company registered.\n"
                .'Company: '.$tenant->name."\n"
                .'Type: '.$tenant->type."\n"
                .'Owner: '.$user->email."\n"
                .'Modules: '.(implode(', ', $modules) ?: 'none')."\n"
                .'Created: '.$tenant->created_at;
            Mail::raw($body, function ($message) use ($admins, $tenant) {
                $message->to($admins)->subject('New company: '.$tenant->name);
            });
        } catch (\Throwable $e) {
            // A notice failure must not affect the company that was already saved.
        }
    }
}
