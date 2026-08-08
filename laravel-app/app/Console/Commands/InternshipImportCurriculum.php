<?php

namespace App\Console\Commands;

use App\Services\Internship\InternshipProgramService;
use Illuminate\Console\Command;

class InternshipImportCurriculum extends Command
{
    protected $signature = 'internship:import-curriculum {--path=} {--dry-run}';

    protected $description = 'Import beyond_180_day_curriculum_seed.json into internship programs';

    public function handle(InternshipProgramService $service)
    {
        $path = $this->option('path') ?: $service->seedPath();
        $commit = ! $this->option('dry-run');
        $this->info(($commit ? 'Importing' : 'Validating').': '.$path);
        $result = $service->importCurriculum($path, $commit);
        if (! $result['ok']) {
            foreach ($result['errors'] as $err) {
                $this->error($err);
            }

            return 1;
        }
        if (! empty($result['dry_run'])) {
            $this->info('Dry-run OK — '.$result['dry_run'].' program(s) valid.');
        } else {
            $this->info('Imported '.$result['imported'].' program(s).');
        }

        return 0;
    }
}
