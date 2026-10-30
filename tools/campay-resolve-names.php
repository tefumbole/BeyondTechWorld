<?php
/**
 * Resolve Cameroon MTN/Orange numbers to Campay holder names, slowly.
 *
 * Resolved names are stored in a JSON file and are never sent to Campay again.
 * Unknown numbers are remembered for 14 days. A 403 stops the run for 24 hours.
 * At most 120 new Campay calls per calendar day, 5 at a time, 8 seconds apart.
 *
 * Skyline (or any PHP host):
 *   CAMPAY_TOKEN='your-token' php campay-resolve-names.php /path/to/phones.txt
 *   CAMPAY_TOKEN='your-token' php campay-resolve-names.php /path/to/phones.txt \
 *       --cache=/var/www/skylinehotel/storage/app/campay-holder-cache.json \
 *       --max=40
 *
 * phones.txt is one number per line. Re-run the same command to continue.
 * Cached numbers are skipped and do not count toward the daily limit.
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Run this from the command line.\n");
    exit(1);
}

$args = array_slice($argv, 1);
$phonesFile = null;
$cacheFile = null;
$maxThisRun = 40;
$batch = 5;
$pause = 8;
$daily = 120;
foreach ($args as $arg) {
    if (strpos($arg, '--cache=') === 0) {
        $cacheFile = substr($arg, 8);
    } elseif (strpos($arg, '--max=') === 0) {
        $maxThisRun = max(1, min(120, (int) substr($arg, 6)));
    } elseif (strpos($arg, '--batch=') === 0) {
        $batch = max(1, min(5, (int) substr($arg, 8)));
    } elseif (strpos($arg, '--pause=') === 0) {
        $pause = max(8, (int) substr($arg, 8));
    } elseif (strpos($arg, '--daily=') === 0) {
        $daily = max(1, min(120, (int) substr($arg, 8)));
    } elseif ($arg !== '' && $arg[0] !== '-') {
        $phonesFile = $arg;
    }
}
if (! $phonesFile || ! is_file($phonesFile)) {
    fwrite(STDERR, "Usage: CAMPAY_TOKEN=... php campay-resolve-names.php phones.txt [--cache=file.json] [--max=40]\n");
    exit(1);
}
$token = getenv('CAMPAY_TOKEN') ?: getenv('MOMO_TOKEN');
if (! $token) {
    fwrite(STDERR, "Set CAMPAY_TOKEN in the environment. Do not put the token in this file.\n");
    exit(1);
}
if (! $cacheFile) {
    $cacheFile = dirname($phonesFile).'/campay-holder-cache.json';
}

function store_empty()
{
    return array('hits' => array(), 'misses' => array(), 'blocked_until' => 0, 'calls_on' => '', 'calls' => 0);
}

function store_read($path)
{
    if (! is_file($path)) {
        return store_empty();
    }
    $fp = fopen($path, 'r');
    if (! $fp) {
        return store_empty();
    }
    flock($fp, LOCK_SH);
    $raw = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $decoded = json_decode((string) $raw, true);

    return is_array($decoded) ? array_merge(store_empty(), $decoded) : store_empty();
}

function store_write($path, $store)
{
    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $fp = fopen($path, 'c+');
    if (! $fp) {
        return;
    }
    flock($fp, LOCK_EX);
    rewind($fp);
    ftruncate($fp, 0);
    fwrite($fp, json_encode($store));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

function holder_key($raw)
{
    $digits = preg_replace('/\D/', '', (string) $raw);
    if (strpos($digits, '237') === 0 && strlen($digits) > 9) {
        $digits = substr($digits, 3);
    }
    if (strlen($digits) !== 9 || $digits[0] !== '6') {
        return null;
    }
    $three = substr($digits, 0, 3);
    $two = substr($digits, 0, 2);
    $mtn = $two === '67' || ($three >= '650' && $three <= '654') || ($three >= '680' && $three <= '683');
    $orange = $two === '69' || ($three >= '655' && $three <= '659') || ($three >= '690' && $three <= '699');
    if (! $mtn && ! $orange) {
        return null;
    }

    return '237'.$digits;
}

function campay_name($token, $key)
{
    $ch = curl_init('https://www.campay.net/api/holder_info/?phone_number='.rawurlencode($key));
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Token '.$token,
            'Accept: application/json',
        ),
    ));
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $body = json_decode((string) $raw, true);
    $name = '';
    if (is_array($body)) {
        foreach (array('full_name', 'fullName', 'name', 'registeredName') as $field) {
            if (! empty($body[$field]) && is_string($body[$field])) {
                $name = trim($body[$field]);
                break;
            }
        }
    }

    return array('code' => $code, 'name' => $name);
}

$lines = file($phonesFile, FILE_IGNORE_NEW_LINES);
$store = store_read($cacheFile);
$today = date('Y-m-d');
if ((string) $store['calls_on'] !== $today) {
    $store['calls_on'] = $today;
    $store['calls'] = 0;
}
if ((int) $store['blocked_until'] > time()) {
    fwrite(STDERR, "Campay is paused until ".date('Y-m-d H:i', (int) $store['blocked_until']).". Nothing was sent.\n");
    exit(2);
}

$skipped = 0;
$named = 0;
$missed = 0;
$invalid = 0;
$sent = 0;
$inBatch = 0;
$seen = array();

foreach ($lines as $line) {
    $key = holder_key($line);
    if (! $key) {
        $invalid++;
        continue;
    }
    if (isset($seen[$key])) {
        continue;
    }
    $seen[$key] = 1;
    if (! empty($store['hits'][$key]['name'])) {
        $skipped++;
        continue;
    }
    $missedAt = isset($store['misses'][$key]) ? (int) $store['misses'][$key] : 0;
    if ($missedAt > 0 && (time() - $missedAt) < 14 * 86400) {
        $skipped++;
        continue;
    }
    if ((int) $store['calls'] >= $daily || $sent >= $maxThisRun) {
        break;
    }
    $result = campay_name($token, $key);
    $sent++;
    $store['calls'] = (int) $store['calls'] + 1;
    $inBatch++;
    if ($result['code'] === 403 || $result['code'] === 429) {
        $store['blocked_until'] = time() + 86400;
        store_write($cacheFile, $store);
        fwrite(STDERR, "Campay returned {$result['code']}. Stopped for 24 hours after {$sent} new lookups.\n");
        exit(2);
    }
    if ($result['name'] !== '') {
        $store['hits'][$key] = array('name' => $result['name'], 'address' => '');
        unset($store['misses'][$key]);
        $named++;
        echo $key."\t".$result['name']."\n";
    } else {
        $store['misses'][$key] = time();
        $missed++;
        echo $key."\t\n";
    }
    store_write($cacheFile, $store);
    if ($sent >= $maxThisRun || (int) $store['calls'] >= $daily) {
        break;
    }
    if ($inBatch >= $batch) {
        $inBatch = 0;
        sleep($pause + 22);
    } else {
        sleep($pause);
    }
}

store_write($cacheFile, $store);
$left = 0;
$counted = array();
foreach ($lines as $line) {
    $key = holder_key($line);
    if (! $key || isset($counted[$key])) {
        continue;
    }
    $counted[$key] = 1;
    $hasHit = ! empty($store['hits'][$key]['name']);
    $missedAt = isset($store['misses'][$key]) ? (int) $store['misses'][$key] : 0;
    $hasMiss = $missedAt > 0 && (time() - $missedAt) < 14 * 86400;
    if (! $hasHit && ! $hasMiss) {
        $left++;
    }
}
fwrite(STDERR, "named={$named} missed={$missed} skipped={$skipped} not_momo={$invalid} sent={$sent} still_to_do={$left}\n");
fwrite(STDERR, "cache={$cacheFile}\n");
fwrite(STDERR, "Run the same command again to continue. Cached numbers are not sent.\n");
