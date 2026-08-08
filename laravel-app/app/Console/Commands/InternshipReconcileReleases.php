<?php

namespace App\Console\Commands;

use App\Services\Internship\InternshipProgramService;
use Illuminate\Console\Command;

class InternshipReconcileReleases extends Command
{
    protected $signature = 'internship:reconcile-releases {--enrolment=}';

    protected $description = 'Release internship tasks for eligible working days (idempotent)';

    public function handle(InternshipProgramService $service)
    {
        $id = $this->option('enrolment');
        $n = $service->reconcileReleases($id ? (int) $id : null);
        $this->info("Released {$n} internship task(s).");

        return 0;
    }
}
