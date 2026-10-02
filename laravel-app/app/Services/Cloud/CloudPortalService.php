<?php

namespace App\Services\Cloud;

use App\Cloud\CloudMembershipRole;
use App\Cloud\CloudMembershipStatus;
use App\Cloud\CloudModule;
use App\Cloud\CloudPlan;
use App\Cloud\CloudSubscription;
use App\Cloud\CloudSubscriptionStatus;
use App\Cloud\CloudTenant;
use App\Cloud\CloudTenantMembership;
use App\Cloud\CloudTenantSetting;
use App\Cloud\CloudTenantStatus;
use App\Cloud\CloudTenantType;
use App\Cloud\CloudTrialUnit;
use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CloudPortalService
{
    public function register(array $input)
    {
        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'phone' => $input['phone'],
                'password' => Hash::make($input['password']),
                'role_id' => 5,
                'is_active' => 1,
                'company_name' => $input['company_name'],
            ]);

            $tenant = CloudTenant::create([
                'name' => $input['company_name'],
                'slug' => $this->uniqueSlug($input['company_name']),
                'system_name' => $input['company_name'],
                'type' => CloudTenantType::CUSTOMER,
                'status' => CloudTenantStatus::ACTIVE,
                'email' => $input['email'],
                'phone' => $input['phone'],
                'country' => isset($input['country']) ? $input['country'] : null,
                'currency' => 'XAF',
                'timezone' => 'Africa/Douala',
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

            return [$user, $tenant];
        });
    }

    public function membershipFor($user)
    {
        if (! $user) {
            return null;
        }

        return CloudTenantMembership::where('user_id', $user->id)
            ->where('status', CloudMembershipStatus::ACTIVE)
            ->orderByDesc('is_owner')
            ->orderBy('id')
            ->first();
    }

    public function startTrial(CloudTenant $tenant, CloudPlan $plan)
    {
        return app(CloudSubscriptionService::class)->startTrial($tenant, $plan);
    }

    public function saveBusinessRules(CloudTenant $tenant, array $input)
    {
        if (isset($input['system_name'])) {
            $tenant->system_name = $input['system_name'];
            $tenant->save();
        }
        foreach (['business_summary', 'services', 'business_rules'] as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }
            CloudTenantSetting::updateOrCreate(
                ['cloud_tenant_id' => $tenant->id, 'key' => $key],
                ['value' => $input[$key], 'type' => 'string']
            );
        }
    }

    public function setting(CloudTenant $tenant, $key, $default = '')
    {
        $row = CloudTenantSetting::where('cloud_tenant_id', $tenant->id)->where('key', $key)->first();

        return $row && $row->value !== null ? $row->value : $default;
    }

    public function storeHero(CloudTenant $tenant, UploadedFile $file)
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new \InvalidArgumentException('Use a JPG, PNG, or WebP image.');
        }
        $size = @getimagesize($file->getRealPath());
        if (! $size || $size[0] < 400 || $size[1] < 200) {
            throw new \InvalidArgumentException('The hero image should be at least 400 by 200 pixels.');
        }
        if ($size[0] > 4000 || $size[1] > 4000) {
            throw new \InvalidArgumentException('The hero image is too large. Use a picture under 4000 pixels on each side.');
        }

        $name = 'hero.'.($ext === 'jpeg' ? 'jpg' : $ext);
        $dir = storage_path('app/tenants/'.$tenant->uuid);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('The hero image could not be stored.');
        }
        $target = $dir.DIRECTORY_SEPARATOR.$name;
        if (! $file->move($dir, $name) && ! is_file($target)) {
            throw new \RuntimeException('The hero image could not be stored.');
        }

        $tenant->hero_path = $name;
        $tenant->save();

        return $name;
    }

    public function heroFile(CloudTenant $tenant)
    {
        $name = basename((string) $tenant->hero_path);
        if (! preg_match('/^hero\.(jpg|png|webp)$/', $name)) {
            return null;
        }
        $root = storage_path('app/tenants/'.$tenant->uuid);
        $path = $root.DIRECTORY_SEPARATOR.$name;
        $real = realpath($path);
        $realRoot = realpath($root);
        if (! $real || ! $realRoot || strpos($real, $realRoot.DIRECTORY_SEPARATOR) !== 0) {
            return null;
        }

        return $real;
    }

    public function trialEnd(CloudPlan $plan, $start = null)
    {
        $start = $start ?: now();
        $value = max(1, (int) $plan->trial_value);
        if ($plan->trial_unit === CloudTrialUnit::MONTH) {
            return $start->copy()->addMonths($value);
        }

        return $start->copy()->addHours($value);
    }

    protected function uniqueSlug($name)
    {
        $base = CloudSlug::fromName($name);
        $slug = $base;
        $n = 2;
        while (CloudTenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n;
            $n++;
        }

        return $slug;
    }
}
