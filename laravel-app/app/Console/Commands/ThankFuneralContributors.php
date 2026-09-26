<?php

namespace App\Console\Commands;

use App\FuneralEulogy;
use App\FuneralPledge;
use App\Services\BeyondWasenderService;
use Illuminate\Console\Command;

class ThankFuneralContributors extends Command
{
    protected $signature = 'funeral:thank-contributors
                            {--dry-run : List recipients without sending}
                            {--interval=5 : Seconds between WhatsApp sends}';

    protected $description = 'Thank funeral pledgers and eulogy authors with the Facebook live-service link';

    const FB_LINK = 'https://www.facebook.com/share/v/19S69JtHQz/';

    public function handle(BeyondWasenderService $whatsapp)
    {
        $interval = max(5, (int) $this->option('interval'));
        $dry = (bool) $this->option('dry-run');
        $queue = [];

        $pledges = FuneralPledge::whereIn('status', [
            FuneralPledge::STATUS_PLEDGED,
            FuneralPledge::STATUS_PAID,
        ])->orderBy('id')->get();

        foreach ($pledges as $pledge) {
            $phone = preg_replace('/\D/', '', (string) $pledge->phone);
            $name = trim((string) $pledge->name);
            if ($phone === '' || $name === '') {
                continue;
            }
            $queue[] = [
                'type' => 'pledge',
                'id' => $pledge->id,
                'phone' => $phone,
                'name' => $name,
                'message' => $this->pledgeMessage($name),
            ];
        }

        $seenEulogy = [];
        $eulogies = FuneralEulogy::orderBy('id')->get();
        foreach ($eulogies as $eulogy) {
            $phone = preg_replace('/\D/', '', (string) $eulogy->phone);
            $name = trim((string) $eulogy->name);
            $body = trim((string) $eulogy->body);
            if ($phone === '' || $name === '' || $body === '') {
                continue;
            }
            $dupKey = $phone.'|'.md5(mb_strtolower($body));
            if (isset($seenEulogy[$dupKey])) {
                continue;
            }
            $seenEulogy[$dupKey] = true;

            $caption = $this->eulogyCaption($body);
            $queue[] = [
                'type' => 'eulogy',
                'id' => $eulogy->id,
                'phone' => $phone,
                'name' => $name,
                'message' => $this->eulogyMessage($name, $caption),
            ];
        }

        $this->info('Recipients: '.count($queue).' ('. $pledges->count().' pledges + eulogies)');
        if ($dry) {
            foreach ($queue as $i => $row) {
                $this->line(sprintf(
                    '%d. [%s #%d] %s → +%s',
                    $i + 1,
                    $row['type'],
                    $row['id'],
                    $row['name'],
                    $row['phone']
                ));
                $this->line($row['message']);
                $this->line('---');
            }
            $this->warn('Dry run — nothing sent.');

            return 0;
        }

        $ok = 0;
        $fail = 0;
        foreach ($queue as $i => $row) {
            $this->line(sprintf(
                'Sending %d/%d [%s] to %s (+%s)…',
                $i + 1,
                count($queue),
                $row['type'],
                $row['name'],
                $row['phone']
            ));
            try {
                $result = $whatsapp->sendText($row['phone'], $row['message']);
                if (! empty($result['success'])) {
                    $ok++;
                    $this->info('  OK');
                } else {
                    $fail++;
                    $this->error('  FAIL: '.($result['error'] ?? 'unknown'));
                }
            } catch (\Throwable $e) {
                $fail++;
                $this->error('  FAIL: '.$e->getMessage());
            }

            if ($i < count($queue) - 1) {
                sleep($interval);
            }
        }

        $this->info("Done. Sent={$ok} failed={$fail}");

        return $fail > 0 ? 1 : 0;
    }

    protected function pledgeMessage($name)
    {
        return "Dear {$name},\n\n"
            ."Thank you for your pledge toward the funeral of Late Pa Ngwayu Francis Nchinda.\n\n"
            ."Follow the funeral service here:\n"
            .self::FB_LINK."\n\n"
            ."With gratitude,\nThe Ngwayu Family";
    }

    protected function eulogyMessage($name, $caption)
    {
        return "Dear {$name},\n\n"
            ."Thank you for your eulogy, “{$caption}.”\n\n"
            ."Follow the funeral service here:\n"
            .self::FB_LINK."\n\n"
            ."With gratitude,\nThe Ngwayu Family";
    }

    protected function eulogyCaption($body)
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $body)));
        $plain = trim($plain, " \t\n\r\0\x0B*\"'");
        $lines = preg_split('/\r\n|\r|\n/', (string) $body) ?: [];
        foreach ($lines as $line) {
            $line = trim(strip_tags($line));
            $line = trim($line, " \t\n\r\0\x0B*\"'");
            if ($line !== '') {
                $plain = $line;
                break;
            }
        }

        if (mb_strlen($plain) > 72) {
            $plain = rtrim(mb_substr($plain, 0, 69)).'…';
        }

        return $plain !== '' ? $plain : 'your tribute';
    }
}
