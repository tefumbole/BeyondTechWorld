<?php

use App\ContractClause;
use App\ContractTemplate;
use App\ContractType;
use App\Services\Contracts\ContractInstanceService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

Auth::loginUsingId(1);
$svc = app(ContractInstanceService::class);
$fail = 0;
$ok = function ($label, $cond) use (&$fail) {
    echo ($cond ? 'OK  ' : 'FAIL').' '.$label.PHP_EOL;
    if (! $cond) {
        $fail++;
    }
};

$tpl = ContractTemplate::with('currentVersion')->where('code', 'ENG-EVENT')->where('active', 1)->first();
$ok('ENG-EVENT template published', $tpl && $tpl->currentVersion);

$c = $svc->createFromTemplate($tpl, [
    'title' => 'Smoke ENG '.date('YmdHis'),
    'effective_date' => date('Y-m-d'),
    'start_date' => date('Y-m-d'),
    'end_date' => date('Y-m-d', strtotime('+7 days')),
    'party_a' => ['name' => 'Beyond Enterprise', 'address' => 'Kigali'],
    'party_b' => ['name' => 'Smoke Worker', 'email' => 'smoke@example.com', 'phone' => '237600000000', 'address' => 'Test'],
    'worker_role' => 'Sound Engineer',
    'worker_daily_rate' => '25000',
    'work_estimated_days' => '3',
]);
$ok('ENG draft created '.$c->number, (bool) $c->number);
$html = $svc->renderedHtml($c);
$ok('ENG HTML includes role', strpos($html, 'Sound Engineer') !== false);

$shrTpl = ContractTemplate::with('currentVersion')->where('code', 'SHR-MAIN')->where('active', 1)->first();
$ok('SHR-MAIN active', $shrTpl && $shrTpl->currentVersion);
$c2 = $svc->createFromTemplate($shrTpl, [
    'title' => 'Smoke SHR '.date('YmdHis'),
    'effective_date' => date('Y-m-d'),
    'party_a' => ['name' => 'Beyond Enterprise'],
    'party_b' => ['name' => 'Investor Smoke'],
]);
$h2 = $svc->renderedHtml($c2);
$ok('SHR vesting wording', stripos($h2, '24 months') !== false);
$ok('SHR price token gone', strpos($h2, '{{share.price_label}}') === false);

foreach (['RNT-EQUIPMENT', 'RNT-ACCOMMODATION', 'SFT-LICENSE', 'EMP-INTERNSHIP', 'EMP-EMPLOYMENT'] as $code) {
    $ok($code.' active', (bool) ContractTemplate::where('code', $code)->where('active', 1)->first());
}
$ok('clauses>=5', ContractClause::active()->count() >= 5);
$ok('RNT type', (bool) ContractType::where('code', 'RNT')->where('active', 1)->first());
$ok('SHR type', (bool) ContractType::where('code', 'SHR')->where('active', 1)->first());

// Blade compile (full layout render needs HTTP session — covered separately)
$compiler = app('blade.compiler');
$paths = array_merge(
    glob(__DIR__.'/../resources/views/contracts/*.blade.php') ?: [],
    glob(__DIR__.'/../resources/views/contracts/*/*.blade.php') ?: [],
    glob(__DIR__.'/../resources/views/contracts/*/*/*.blade.php') ?: []
);
foreach (array_unique($paths) as $p) {
    try {
        $compiler->compile($p);
        $ok('compile '.basename(dirname($p)).'/'.basename($p), true);
    } catch (Throwable $e) {
        $ok('compile '.$p.' :: '.$e->getMessage(), false);
    }
}

echo PHP_EOL.($fail === 0 ? 'SMOKE PASSED' : "SMOKE FAILED ($fail)").PHP_EOL;
exit($fail === 0 ? 0 : 1);
