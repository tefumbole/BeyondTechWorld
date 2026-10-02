<?php

namespace App\Console\Commands;

use App\Services\Cloud\CloudInternalTenantService;
use Illuminate\Console\Command;

class CreateInternalTenant extends Command
{
    protected $signature = 'cloud:create-internal-tenant';

    protected $description = 'Create or reuse the BeyondTechWorld INTERNAL CloudTenant';

    public function handle(CloudInternalTenantService $service)
    {
        $result = $service->ensure();
        $tenant = $result['tenant'];
        $this->info(($result['created'] ? 'Created' : 'Reused').' CloudTenant '.$tenant->name.' id='.$tenant->id.' slug='.$tenant->slug.' type='.$tenant->type);
        $this->line('currency='.$tenant->currency.' timezone='.$tenant->timezone.' status='.$tenant->status);
        $this->line('legal_name source=default biller; system_name source=general_settings.site_title');
        foreach ($result['identity']['conflicts'] as $conflict) {
            $this->warn($conflict);
        }
        $members = $result['memberships'];
        $this->line('memberships owner='.$members['owner'].' admin='.$members['admin'].' staff='.$members['staff'].' platform_admins_not_added='.$members['skipped_platform_admin']);
        $this->line('entitlements='.implode(',', $result['entitlements']));
        $this->line('subscriptions_for_this_company='.$result['subscriptions']);

        return 0;
    }
}
