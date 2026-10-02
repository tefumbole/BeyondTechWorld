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
                $user = User::create([
                    'name' => trim($input['first_name'].' '.$input['last_name']),
                    'email' => $email,
                    'phone' => $input['phone'],
                    'password' => Hash::make($input['password']),
                    'role_id' => 5,
                    'is_active' => 1,
                    'company_name' => $input['company_name'],
                ]);

                return [$user, $this->createCompany($user, $input)];
            });
        });
        $this->welcome($pair[0], $pair[1]);

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
        $steps = [
            ['label' => 'Company created', 'done' => true, 'show' => true],
            ['label' => 'Upload logo', 'done' => $tenant->logo_path ? true : false, 'show' => true],
        ];
        if ($sales || $rentals) {
            $steps[] = ['label' => 'Add products', 'done' => false, 'show' => true];
            $steps[] = ['label' => 'Add customers', 'done' => false, 'show' => true];
        }
        if ($sales) {
            $steps[] = ['label' => 'Create first quotation', 'done' => false, 'show' => true];
        }
        if ($rentals) {
            $steps[] = ['label' => 'Set rental prices', 'done' => false, 'show' => true];
        }
        if ($messaging) {
            $steps[] = ['label' => 'Configure Messaging', 'done' => false, 'show' => true];
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
        $subscriptions = app(CloudSubscriptionService::class);
        foreach ($quote['lines'] as $line) {
            $plan = CloudPlan::whereHas('module', function ($query) use ($line) {
                $query->where('code', $line['code']);
            })->where('active', true)->first();
            $subscriptions->startTrial($tenant, $plan, $user->id);
        }

        return $tenant;
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
    }
}
