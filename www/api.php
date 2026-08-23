<?php
// cfspeed API v3.0 — www/api.php
header('Content-Type: application/json');
header('Cache-Control: no-store');

define('CFSPEED_DATA', dirname(__DIR__) . '/data');

$ranges = [
    '3h'  => 3 * 3600,
    '24h' => 24 * 3600,
    '7d'  => 7 * 86400,
    '30d' => 30 * 86400,
];
$buckets = [
    '3h'  => 60,
    '24h' => 300,
    '7d'  => 1800,
    '30d' => 7200,
];

$range = $_GET['range'] ?? '24h';
$seconds = $ranges[$range] ?? $ranges['24h'];
$bucket = $buckets[$range] ?? 300;
$since = time() - $seconds;

try {
    $db = new SQLite3(CFSPEED_DATA . '/speed.db', SQLITE3_OPEN_READONLY);
} catch (Exception $e) {
    http_response_code(503);
    echo json_encode(['error' => 'No data yet. Waiting for the first test run.']);
    exit;
}
$db->busyTimeout(3000);

$stmt = $db->prepare(
    'SELECT ts, latency_ms, jitter_ms, down_mbps, up_mbps,
            lat_down_ms, lat_up_ms, ping_ms, ping_loss,
            bytes_down, bytes_up, retries,
            colo, client_ip, isp, city, country, asn, error
     FROM results WHERE ts >= :since ORDER BY ts ASC'
);
$stmt->bindValue(':since', $since, SQLITE3_INTEGER);
$res = $stmt->execute();

$rows = [];
while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
    $rows[] = $r;
}

$ok = array_values(array_filter($rows, fn($r) => $r['error'] === null && $r['down_mbps'] !== null));

function col(array $rows, string $k): array {
    return array_values(array_filter(array_column($rows, $k), fn($v) => $v !== null));
}
function pct(array $a, float $p) {
    if (!$a) return null;
    sort($a);
    return $a[(int) floor(($p / 100) * (count($a) - 1))];
}

$stats = null;
$usage = null;

if (count($ok) > 0) {
    $down = col($ok, 'down_mbps');
    $up   = col($ok, 'up_mbps');
    $lat  = col($ok, 'latency_ms');
    $jit  = col($ok, 'jitter_ms');
    $latD = col($ok, 'lat_down_ms');
    $latU = col($ok, 'lat_up_ms');
    $ping = col($ok, 'ping_ms');
    $loss = col($ok, 'ping_loss');

    $bb = null;
    if ($lat && ($latD || $latU)) {
        $idleMed = pct($lat, 50);
        $loadedMax = max(pct($latD, 50) ?? 0, pct($latU, 50) ?? 0);
        $bb = round(max($loadedMax - $idleMed, 0), 1);
    }

    $stats = [
        'down_avg' => round(array_sum($down) / count($down), 1),
        'down_min' => min($down), 'down_max' => max($down),
        'down_p10' => pct($down, 10),  // slowest-decile floor
        'up_avg'   => round(array_sum($up) / count($up), 1),
        'up_min'   => min($up), 'up_max' => max($up),
        'lat_avg'  => $lat ? round(array_sum($lat) / count($lat), 1) : null,
        'lat_max'  => $lat ? max($lat) : null,
        'jit_avg'  => $jit ? round(array_sum($jit) / count($jit), 1) : null,
        'bufferbloat_ms' => $bb,
        'ping_avg' => $ping ? round(array_sum($ping) / count($ping), 1) : null,
        'loss_avg' => $loss ? round(array_sum($loss) / count($loss), 2) : (count($loss) ? 0.0 : null),
        'loss_max' => $loss ? max($loss) : null,
        'failures' => count($rows) - count($ok),
        'tests'    => count($rows),
        'success_pct' => count($rows) ? round(100 * count($ok) / count($rows), 1) : null,
    ];

    $withBytes = array_values(array_filter($ok, fn($r) => $r['bytes_down'] !== null || $r['bytes_up'] !== null));
    if (count($withBytes) > 0) {
        $last5 = array_slice($withBytes, -5);
        $last5List = array_map(fn($r) => [
            'ts'      => $r['ts'],
            'down_mb' => round(($r['bytes_down'] ?? 0) / 1e6, 1),
            'up_mb'   => round(($r['bytes_up'] ?? 0) / 1e6, 1),
            'total_mb'=> round((($r['bytes_down'] ?? 0) + ($r['bytes_up'] ?? 0)) / 1e6, 1),
        ], $last5);

        $sumBytes = fn($rows) => array_sum(array_map(
            fn($r) => ($r['bytes_down'] ?? 0) + ($r['bytes_up'] ?? 0), $rows));

        $rangeTotal = $sumBytes($withBytes);
        $avgPerTest = $rangeTotal / count($withBytes);

        $spanSec = max(end($withBytes)['ts'] - $withBytes[0]['ts'], 1);
        $estDailyGb = count($withBytes) > 1
            ? round(($rangeTotal / $spanSec) * 86400 / 1e9, 2)
            : null;

        $usage = [
            'last5'           => $last5List,
            'last5_total_mb'  => round($sumBytes($last5) / 1e6, 1),
            'range_total_gb'  => round($rangeTotal / 1e9, 2),
            'avg_per_test_mb' => round($avgPerTest / 1e6, 1),
            'est_daily_gb'    => $estDailyGb,
        ];
    }
}

// ---- Tests table ----
$tests = array_map(fn($r) => [
    'ts'       => $r['ts'],
    'down'     => $r['down_mbps'],
    'up'       => $r['up_mbps'],
    'lat'      => $r['latency_ms'],
    'jit'      => $r['jitter_ms'],
    'lat_d'    => $r['lat_down_ms'],
    'lat_u'    => $r['lat_up_ms'],
    'ping'     => $r['ping_ms'],
    'loss'     => $r['ping_loss'],
    'mb_down'  => $r['bytes_down'] !== null ? round($r['bytes_down'] / 1e6, 1) : null,
    'mb_up'    => $r['bytes_up'] !== null ? round($r['bytes_up'] / 1e6, 1) : null,
    'mb_total' => ($r['bytes_down'] !== null || $r['bytes_up'] !== null)
                    ? round((($r['bytes_down'] ?? 0) + ($r['bytes_up'] ?? 0)) / 1e6, 1) : null,
    'colo'     => $r['colo'],
    'ip'       => $r['client_ip'],
    'retries'  => $r['retries'],
    'error'    => $r['error'],
], array_reverse($rows));
$tests = array_slice($tests, 0, 500);

// ---- WAN IP history with cached PTR ----
function ptr_lookup(string $ip): string {
    static $cache = null;
    $cacheFile = CFSPEED_DATA . '/ptr_cache.json';
    if ($cache === null) {
        $cache = is_file($cacheFile)
            ? (json_decode((string) file_get_contents($cacheFile), true) ?: [])
            : [];
    }
    if (!isset($cache[$ip])) {
        $host = @gethostbyaddr($ip);
        $cache[$ip] = ($host !== false && $host !== $ip) ? $host : '';
        @file_put_contents($cacheFile, json_encode($cache));
    }
    return $cache[$ip];
}

$ipHistory = [];
$ipRes = $db->query(
    'SELECT client_ip AS ip, MIN(ts) AS first_seen, MAX(ts) AS last_seen,
            COUNT(*) AS tests
     FROM results
     WHERE client_ip IS NOT NULL AND client_ip != ""
     GROUP BY client_ip
     ORDER BY last_seen DESC
     LIMIT 100'
);
$currentIp = count($ok) ? $ok[count($ok) - 1]['client_ip'] : null;
while ($r = $ipRes->fetchArray(SQLITE3_ASSOC)) {
    $r['hostname'] = ptr_lookup($r['ip']);
    $r['current']  = ($r['ip'] === $currentIp);
    $ipHistory[] = $r;
}

// ---- Dish history (bucketed averages) ----
$dish = null;
$hasDish = $db->querySingle(
    "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='dish'");
if ($hasDish) {
    $dstmt = $db->prepare(
        'SELECT (ts / :b) * :b AS tb,
                AVG(down_bps) AS down_bps,
                AVG(up_bps) AS up_bps,
                AVG(pop_latency_ms) AS pop_ms,
                AVG(drop_rate_60s) AS drop_rate,
                SUM(outage_s_60s) AS outage_s,
                AVG(fraction_obstructed) AS fo
         FROM dish
         WHERE ts >= :since AND error IS NULL
         GROUP BY tb ORDER BY tb ASC'
    );
    $dstmt->bindValue(':b', $bucket, SQLITE3_INTEGER);
    $dstmt->bindValue(':since', $since, SQLITE3_INTEGER);
    $dres = $dstmt->execute();
    $drows = [];
    while ($r = $dres->fetchArray(SQLITE3_ASSOC)) {
        $drows[] = [
            'ts'        => (int) $r['tb'],
            'down_mbps' => round($r['down_bps'] / 1e6, 2),
            'up_mbps'   => round($r['up_bps'] / 1e6, 2),
            'pop_ms'    => round($r['pop_ms'], 1),
            'drop_pct'  => $r['drop_rate'] !== null ? round($r['drop_rate'] * 100, 2) : null,
            'outage_s'  => $r['outage_s'] !== null ? (int) $r['outage_s'] : null,
            'obstr_pct' => $r['fo'] !== null ? round($r['fo'] * 100, 3) : null,
        ];
    }
    $dlatest = $db->querySingle(
        'SELECT ts, uptime_s, sw, alerts, gps_sats, eth_mbps, tilt, azim, elev
         FROM dish WHERE error IS NULL ORDER BY ts DESC LIMIT 1', true) ?: null;
    $outageTotal = 0;
    foreach ($drows as $r) { $outageTotal += $r['outage_s'] ?? 0; }
    $dish = [
        'rows'          => $drows,
        'latest'        => $dlatest,
        'outage_total_s'=> $outageTotal,
        'bucket'        => $bucket,
    ];
}

// ---- Satellites overhead (TLE-inferred) ----
$sats = null;
$hasSats = $db->querySingle(
    "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='sats'");
if ($hasSats) {
    $slatest = $db->querySingle(
        'SELECT ts, visible_count, best_name, best_norad, best_az, best_el,
                best_range_km, best_sep_deg, top3, bs_az, bs_el
         FROM sats WHERE error IS NULL ORDER BY ts DESC LIMIT 1', true) ?: null;

    $seen = [];
    $sstmt = $db->prepare(
        'SELECT best_name AS name, best_norad AS norad,
                COUNT(*) AS minutes,
                MIN(ts) AS first_seen, MAX(ts) AS last_seen,
                MIN(best_sep_deg) AS min_sep, MAX(best_el) AS max_el,
                MIN(best_range_km) AS min_range
         FROM sats
         WHERE ts >= :since AND best_name IS NOT NULL AND error IS NULL
         GROUP BY best_name
         ORDER BY last_seen DESC
         LIMIT 300'
    );
    $sstmt->bindValue(':since', $since, SQLITE3_INTEGER);
    $sres = $sstmt->execute();
    while ($r = $sres->fetchArray(SQLITE3_ASSOC)) {
        $seen[] = $r;
    }

    $avgVisible = $db->querySingle(
        'SELECT AVG(visible_count) FROM sats
         WHERE ts >= ' . (int)$since . ' AND error IS NULL');

    $sats = [
        'latest'      => $slatest,
        'seen'        => $seen,
        'distinct'    => count($seen),
        'avg_visible' => $avgVisible !== null ? round($avgVisible, 1) : null,
    ];
}

$cfgSafe = null;
$cfgFile = CFSPEED_DATA . '/config.json';
if (is_file($cfgFile)) {
    $c = json_decode((string) file_get_contents($cfgFile), true) ?: [];
    $cfgSafe = ['live_poll_s' => max(1, min(30, (int) ($c['intervals']['live_poll_s'] ?? 2)))];
    // Coarse observer position, only so the UI can show the distance to the
    // Cloudflare edge. Rounded by the same publish_precision the sky map uses,
    // so the dashboard never exposes a more exact location than that does.
    $loc = $c['location'] ?? [];
    if (isset($loc['lat'], $loc['lon']) && $loc['lat'] !== null && $loc['lon'] !== null) {
        $prec = max(0, min(5, (int) ($loc['publish_precision'] ?? 2)));
        $cfgSafe['qth'] = ['lat' => round((float) $loc['lat'], $prec),
                           'lon' => round((float) $loc['lon'], $prec)];
    }
}

echo json_encode([
    'range'      => $range,
    'latest'     => count($ok) ? $ok[count($ok) - 1] : null,
    'stats'      => $stats,
    'usage'      => $usage,
    'rows'       => $ok,
    'tests'      => $tests,
    'ip_history' => $ipHistory,
    'dish'       => $dish,
    'sats'       => $sats,
    'cfg'        => $cfgSafe,
]);
