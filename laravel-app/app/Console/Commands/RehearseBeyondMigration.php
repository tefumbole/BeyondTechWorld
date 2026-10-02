<?php

namespace App\Console\Commands;

use App\Services\Cloud\BeyondOwnershipRehearsal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RehearseBeyondMigration extends Command
{
    protected $signature = 'cloud:rehearse-beyond-migration
        {--execute : Write nullable ownership. Live database also requires --confirm-production.}
        {--confirm-production= : Exact phrase APPLY-BEYONDTECHWORLD-OWNERSHIP. Required with --execute on the live database.}
        {--seed-test-tenant : Add one test customer company with test-only rows. Refused on the live database.}';

    protected $description = 'Dry-run BeyondTechWorld ownership assignment. Live writes need --execute and --confirm-production.';

    public function handle(BeyondOwnershipRehearsal $rehearsal)
    {
        try {
            $result = $rehearsal->report(
                (bool) $this->option('execute'),
                (bool) $this->option('seed-test-tenant'),
                (string) $this->option('confirm-production')
            );
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $this->line('database='.$result['database'].' mode='.($result['executed'] ? 'execute' : 'dry-run').' tenant_id='.($result['tenant_id'] ?: 'none'));
        $this->line('table total already_owned unowned would_assign ambiguous skipped');
        foreach ($result['lines'] as $line) {
            $this->line(implode(' ', [
                $line['table'],
                'total='.$line['total'],
                'already_owned='.$line['already_owned'],
                'unowned='.$line['unowned'],
                'would_assign='.$line['would_assign'],
                'ambiguous='.$line['ambiguous'],
                'skipped='.$line['skipped'],
                $line['errors'] !== '' ? 'note='.$line['errors'] : '',
            ]));
            if ($result['executed'] && Schema::hasTable($line['table'])) {
                $after = (int) DB::table($line['table'])->count();
                $before = $result['before'][$line['table']];
                if ($before !== null && $before !== $after) {
                    $this->error($line['table'].' row count changed from '.$before.' to '.$after);

                    return 1;
                }
            }
        }

        foreach ($result['relationships'] as $check) {
            $orphans = $check['orphans'] === null ? 'n/a' : $check['orphans'];
            $this->line('relationship '.$check['child'].' -> '.$check['parent'].' orphans='.$orphans.' '.$check['note']);
        }

        if ($result['test_tenant']) {
            $test = $result['test_tenant'];
            $this->line('test_tenant id='.$test['tenant_id'].' product_id='.$test['product_id'].' customer_id='.$test['customer_id']);
        }

        return 0;
    }
}
