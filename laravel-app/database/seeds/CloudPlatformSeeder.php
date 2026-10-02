<?php

use App\Services\Cloud\CloudCatalog;
use Illuminate\Database\Seeder;

/**
 * Global module and plan rows only. Does not create a company.
 * Not called from DatabaseSeeder. The Phase 1B migration already installs the catalog.
 * Running this again does not overwrite a price that was edited later.
 */
class CloudPlatformSeeder extends Seeder
{
    public function run()
    {
        CloudCatalog::install();
    }
}
