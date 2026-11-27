<?php

/**
 * Dependency-free HTTP load runner (PHP curl_multi). Logs in as the users created by
 * `php artisan loadtest:seed`, then drives C concurrent virtual users for D seconds, mixing page
 * reads with journal-entry writes (create draft). Reports RPS, error rate and latency percentiles.
 *
 *   php tests/Stress/http_load.php --base=http://127.0.0.1:8000 --users=20 --concurrency=20 --duration=30
 *
 * The server under test sets the ceiling: `php artisan serve` is a single process, so test a real
 * php-fpm + nginx deployment to get meaningful capacity numbers.
 */
$o = getopt('', ['base::', 'users::', 'concurrency::', 'duration::', 'prefix::', 'write-ratio::']);
$base = rtrim($o['base'] ?? 'http://127.0.0.1:8000', '/');
$users = (int) ($o['users'] ?? 20);
$concurrency = (int) ($o['concurrency'] ?? 20);
$duration = (int) ($o['duration'] ?? 30);
$prefix = $o['prefix'] ?? 'loadtest';
$writeRatio = (float) ($o['write-ratio'] ?? 0.2);

function csrf(string $html): string
{
    preg_match('/name="_token" value="([^"]+)"/', $html, $m);

    return $m[1] ?? '';
}

function request(string $method, string $url, string $jar, array $fields = []): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_TIMEOUT => 30, CURLOPT_CUSTOMREQUEST => $method,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    }
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$code, (string) $body];
}

// --- Setup: log every user in, discover their account ids ------------------------------------
echo "Logging in {$users} users...\n";
$sessions = [];
for ($i = 1; $i <= $users; $i++) {
    $jar = tempnam(sys_get_temp_dir(), 'jar');
    [, $html] = request('GET', "$base/login", $jar);
    [$code] = request('POST', "$base/login", $jar, ['_token' => csrf($html), 'email' => "{$prefix}{$i}@example.com", 'password' => 'password']);
    [$c2, $page] = request('GET', "$base/accounting/journal-entries/create", $jar);
    if ($c2 !== 200) {
        fwrite(STDERR, "Login/setup failed for user $i (HTTP $c2). Did you run loadtest:seed?\n");
        exit(1);
    }
    preg_match_all('/<option value="(\d+)"[^>]*>(1000|4000) /', $page, $acc, PREG_SET_ORDER);
    $ids = [];
    foreach ($acc as $a) {
        $ids[$a[2]] = $a[1];
    }
    $sessions[] = ['jar' => $jar, 'cash' => $ids['1000'] ?? null, 'sales' => $ids['4000'] ?? null, 'token' => csrf($page)];
}

// --- Load: a pool of concurrent in-flight requests until the deadline -----------------------
$mh = curl_multi_init();
$inflight = [];
$latencies = [];
$errors = 0;
$total = 0;
$deadline = microtime(true) + $duration;

$launch = function () use (&$inflight, $mh, $sessions, $base, $writeRatio) {
    $s = $sessions[array_rand($sessions)];
    $ch = curl_init();
    $write = mt_rand() / mt_getrandmax() < $writeRatio && $s['cash'] && $s['sales'];
    if ($write) {
        $amount = (string) mt_rand(1, 5000).'.00';
        curl_setopt_array($ch, [CURLOPT_URL => "$base/accounting/journal-entries", CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                '_token' => $s['token'], 'entry_date' => '2026-03-15', 'description' => 'load',
                'idempotency_key' => bin2hex(random_bytes(12)),
                'lines' => [['account_id' => $s['cash'], 'debit' => $amount], ['account_id' => $s['sales'], 'credit' => $amount]],
            ])]);
    } else {
        $paths = ['/dashboard', '/accounting/accounts', '/accounting/journal-entries', '/accounting/fiscal-years'];
        curl_setopt($ch, CURLOPT_URL, $base.$paths[array_rand($paths)]);
    }
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_COOKIEFILE => $s['jar'], CURLOPT_COOKIEJAR => $s['jar'], CURLOPT_TIMEOUT => 30]);
    curl_multi_add_handle($mh, $ch);
    $inflight[(int) $ch] = [$ch, microtime(true)];
};

echo "Running {$concurrency} concurrent users for {$duration}s...\n";
$start = microtime(true);
while (count($inflight) < $concurrency) {
    $launch();
}
while ($inflight) {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh, 0.05);
    while ($info = curl_multi_info_read($mh)) {
        $ch = $info['handle'];
        [, $t0] = $inflight[(int) $ch];
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $latencies[] = (microtime(true) - $t0) * 1000;
        $total++;
        if ($info['result'] !== CURLE_OK || $code >= 500 || $code === 0) {
            $errors++;
        }
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        unset($inflight[(int) $ch]);
        if (microtime(true) < $deadline) {
            $launch();
        }
    }
}
$elapsed = microtime(true) - $start;
sort($latencies);
$p = fn (float $q) => $latencies ? $latencies[(int) min(count($latencies) - 1, floor($q * count($latencies)))] : 0;

printf("\nRequests: %d in %.1fs  =>  %.1f req/s\n", $total, $elapsed, $total / $elapsed);
printf("Errors (5xx/timeouts): %d (%.2f%%)\n", $errors, $total ? $errors / $total * 100 : 0);
printf("Latency ms  p50=%.0f  p95=%.0f  p99=%.0f  max=%.0f\n", $p(0.5), $p(0.95), $p(0.99), $latencies ? end($latencies) : 0);
foreach ($sessions as $s) {
    @unlink($s['jar']);
}
