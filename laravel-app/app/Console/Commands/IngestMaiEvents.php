<?php

namespace App\Console\Commands;

use App\Services\Mai\MaiInboxStore;
use Illuminate\Console\Command;

class IngestMaiEvents extends Command
{
    protected $signature = 'mai:ingest-events';

    protected $description = 'Read Whats MAI worker events into the inbox';

    public function handle()
    {
        if (! config('cloud.mai_customer.enabled')) {
            return 0;
        }
        $dir = storage_path('app/whatsapp-link/outbox');
        if (! is_dir($dir)) {
            return 0;
        }
        $files = glob($dir.'/*.jsonl');
        if (! is_array($files)) {
            return 0;
        }
        $store = app(MaiInboxStore::class);
        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            if (! is_array($lines)) {
                continue;
            }
            $pending = [];
            foreach ($lines as $line) {
                $event = json_decode($line, true);
                if (! is_array($event)) {
                    continue;
                }
                try {
                    $store->accept($event);
                } catch (\Throwable $e) {
                    $pending[] = $line;
                }
            }
            if ($pending === []) {
                @unlink($file);
            } else {
                file_put_contents($file, implode("\n", $pending)."\n");
            }
        }

        return 0;
    }
}
