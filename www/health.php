<?php
// cfspeed ICMP health — www/health.php
// Recent ping samples per target, read straight from the DB. The dashboard
// polls this often, so it only reads rows; the pinging itself is done by
// icmp_collector.py on a systemd timer.
header('Content-Type: application/json');
header('Cache-Control: no-store');

define('CFSPEED_BASE', dirname(__DIR__));
$DB = CFSPEED_BASE . '/data/speed.db';
$CFG = CFSPEED_BASE . '/data/config.json';

const SAMPLES = 60;      // strip length shown in the UI
const WINDOW_S = 3600;   // how far back a sample may be to count as recent

function out($d){
    $j = json_encode($d, JSON_INVALID_UTF8_SUBSTITUTE);
    echo $j === false ? json_encode(['error' => 'encode failed']) : $j;
    exit;
}

$cfg = is_file($CFG) ? (json_decode((string) file_get_contents($CFG), true) ?: []) : [];
$ic = $cfg['icmp'] ?? [];
if (($ic['enabled'] ?? true) === false) out(['enabled' => false, 'targets' => []]);

$goodMs = max(1, (float) ($ic['good_ms'] ?? 40));
$warnMs = max($goodMs + 1, (float) ($ic['warn_ms'] ?? 100));
$interval = max(5, (int) ($ic['interval_s'] ?? 30));

try {
    $db = new SQLite3($DB, SQLITE3_OPEN_READONLY);
} catch (Exception $e) {
    out(['enabled' => true, 'targets' => [], 'error' => 'no database yet']);
}
$db->busyTimeout(3000);

$hasTable = $db->querySingle(
    "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='icmp'");
if (!$hasTable) out(['enabled' => true, 'targets' => [], 'waiting' => true]);

// Configured order wins; fall back to whatever the DB has seen recently.
$wanted = [];
foreach (($ic['targets'] ?? []) as $t) {
    $h = trim((string) ($t['host'] ?? ''));
    if ($h !== '') $wanted[] = ['host' => $h, 'label' => (string) ($t['label'] ?? $h)];
}
if (!$wanted) {
    $r = $db->query('SELECT host, label, MAX(ts) t FROM icmp GROUP BY host ORDER BY t DESC LIMIT 8');
    while ($row = $r->fetchArray(SQLITE3_ASSOC))
        $wanted[] = ['host' => $row['host'], 'label' => $row['label'] ?: $row['host']];
}

$now = time();
$since = $now - WINDOW_S;
$targets = [];

$stmt = $db->prepare(
    'SELECT ts, rtt_avg, rtt_min, rtt_max, loss, error
     FROM icmp WHERE host = :h AND ts >= :s
     ORDER BY ts DESC LIMIT :n');

foreach ($wanted as $w) {
    $stmt->reset();
    $stmt->bindValue(':h', $w['host'], SQLITE3_TEXT);
    $stmt->bindValue(':s', $since, SQLITE3_INTEGER);
    $stmt->bindValue(':n', SAMPLES, SQLITE3_INTEGER);
    $res = $stmt->execute();

    $rows = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) $rows[] = $row;
    $rows = array_reverse($rows);           // oldest -> newest for the strip

    $samples = array_map(fn($r) => [
        'ts'   => (int) $r['ts'],
        'rtt'  => $r['rtt_avg'] !== null ? round((float) $r['rtt_avg'], 1) : null,
        'loss' => $r['loss'] !== null ? (float) $r['loss'] : null,
        'err'  => $r['error'] !== null,
    ], $rows);

    $last = $rows ? $rows[count($rows) - 1] : null;
    $ok = array_values(array_filter($rows,
        fn($r) => $r['rtt_avg'] !== null && $r['error'] === null));
    $rtts = array_map(fn($r) => (float) $r['rtt_avg'], $ok);

    $rtt = $last && $last['rtt_avg'] !== null ? round((float) $last['rtt_avg'], 1) : null;
    $loss = $last && $last['loss'] !== null ? (float) $last['loss'] : null;
    $stale = !$last || ($now - (int) $last['ts']) > max(180, $interval * 4);

    // Loss dominates: a fast reply that drops packets is not healthy.
    if ($stale)                          $state = 'stale';
    elseif ($rtt === null || $loss >= 100) $state = 'down';
    elseif ($loss > 0)                   $state = 'warn';
    elseif ($rtt <= $goodMs)             $state = 'good';
    elseif ($rtt <= $warnMs)             $state = 'warn';
    else                                 $state = 'bad';

    $targets[] = [
        'label'   => $w['label'],
        'host'    => $w['host'],
        'rtt'     => $rtt,
        'loss'    => $loss,
        'state'   => $state,
        'min'     => $rtts ? round(min($rtts), 1) : null,
        'max'     => $rtts ? round(max($rtts), 1) : null,
        'avg'     => $rtts ? round(array_sum($rtts) / count($rtts), 1) : null,
        'uptime'  => $rows ? round(100 * count($ok) / count($rows), 1) : null,
        'last_ts' => $last ? (int) $last['ts'] : null,
        'samples' => $samples,
    ];
}

out([
    'enabled'    => true,
    'ts'         => $now,
    'interval_s' => $interval,
    'good_ms'    => $goodMs,
    'warn_ms'    => $warnMs,
    'window_s'   => WINDOW_S,
    'targets'    => $targets,
]);
