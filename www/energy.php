<?php
// cfspeed energy — www/energy.php
//
// The dish reports its input power once a second in the history ring buffer;
// dish_collector integrates that into watt-hours per row. Everything here is
// aggregation over those rows, so it stays cheap enough to poll.
header('Content-Type: application/json');
header('Cache-Control: no-store');

define('CFSPEED_BASE', dirname(__DIR__));
$DB  = CFSPEED_BASE . '/data/speed.db';
$CFG = CFSPEED_BASE . '/data/config.json';

function out($d){
    $j = json_encode($d, JSON_INVALID_UTF8_SUBSTITUTE);
    echo $j === false ? json_encode(['error' => 'encode failed']) : $j;
    exit;
}

$cfg   = is_file($CFG) ? (json_decode((string) file_get_contents($CFG), true) ?: []) : [];
$en    = $cfg['energy'] ?? [];
$price = max(0, (float) ($en['price_per_kwh'] ?? 0));
$cur   = preg_replace('/[^\p{L}\p{Sc}. ]/u', '', (string) ($en['currency'] ?? '')) ?: '';
$cur   = mb_strcut($cur, 0, 8);

try {
    $db = new SQLite3($DB, SQLITE3_OPEN_READONLY);
} catch (Exception $e) {
    out(['error' => 'no database yet']);
}
$db->busyTimeout(3000);

$has = false;
$r = $db->query("PRAGMA table_info(dish)");
while ($c = $r->fetchArray(SQLITE3_ASSOC)) if ($c['name'] === 'power_wh') $has = true;
if (!$has) out(['supported' => false]);

// Does this firmware actually report power, or is the column just empty?
$anyPower = (int) ($db->querySingle(
    "SELECT COUNT(*) FROM dish WHERE power_wh IS NOT NULL") ?: 0);
if ($anyPower === 0) out(['supported' => false, 'waiting' => true]);

$ranges = ['24h' => 86400, '7d' => 7 * 86400, '30d' => 30 * 86400, '90d' => 90 * 86400];
$range  = $_GET['range'] ?? '7d';
$span   = $ranges[$range] ?? $ranges['7d'];
$now    = time();
$since  = $now - $span;

/* ---- latest sample ---- */
$latest = $db->querySingle(
    "SELECT ts, power_w, dish_power_w, router_power_w, heating
     FROM dish WHERE power_w IS NOT NULL AND error IS NULL
     ORDER BY ts DESC LIMIT 1", true) ?: null;

/* ---- totals over fixed windows ---- */
function wh(SQLite3 $db, int $from, ?int $to = null): float {
    $q = $db->prepare("SELECT SUM(power_wh) w FROM dish
                       WHERE ts >= :a" . ($to !== null ? " AND ts < :b" : "")
                      . " AND error IS NULL");
    $q->bindValue(':a', $from, SQLITE3_INTEGER);
    if ($to !== null) $q->bindValue(':b', $to, SQLITE3_INTEGER);
    return (float) (($q->execute()->fetchArray(SQLITE3_ASSOC)['w']) ?? 0);
}

$todayStart = strtotime('today');
$monthStart = strtotime('first day of this month 00:00');
$today      = wh($db, $todayStart);
$yesterday  = wh($db, strtotime('yesterday'), $todayStart);
$month      = wh($db, $monthStart);

/* Project the month from the daily rate so far, not from a flat guess. */
$elapsedDays = max(0.04, ($now - $monthStart) / 86400);
$daysInMonth = (int) date('t');
$projected   = $month / $elapsedDays * $daysInMonth;

/* ---- series, bucketed so the chart stays readable at any range ---- */
$bucket = $span <= 86400 ? 300 : ($span <= 7 * 86400 ? 1800 : 7200);
$q = $db->prepare(
    "SELECT (ts / :b) * :b tb,
            AVG(power_w) w, MAX(power_w) wmax, SUM(power_wh) wh,
            MAX(heating) heat
     FROM dish
     WHERE ts >= :s AND error IS NULL AND power_w IS NOT NULL
     GROUP BY tb ORDER BY tb");
$q->bindValue(':b', $bucket, SQLITE3_INTEGER);
$q->bindValue(':s', $since, SQLITE3_INTEGER);
$res = $q->execute();
$series = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $series[] = ['ts' => (int) $row['tb'],
                 'w' => round((float) $row['w'], 1),
                 'wmax' => round((float) $row['wmax'], 1),
                 'wh' => round((float) $row['wh'], 3),
                 'heating' => (int) $row['heat'] === 1];
}

/* ---- per-day totals ---- */
$q = $db->prepare(
    "SELECT date(ts,'unixepoch','localtime') d, SUM(power_wh) wh,
            AVG(power_w) w, MAX(power_w) wmax,
            SUM(CASE WHEN heating=1 THEN 60 ELSE 0 END) heat_s
     FROM dish WHERE ts >= :s AND error IS NULL
     GROUP BY d ORDER BY d");
$q->bindValue(':s', $since, SQLITE3_INTEGER);
$res = $q->execute();
$days = [];
while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $days[] = ['day' => $row['d'], 'wh' => round((float) $row['wh'], 1),
               'avg_w' => round((float) $row['w'], 1),
               'max_w' => round((float) $row['wmax'], 1),
               'heat_s' => (int) $row['heat_s']];
}

/* ---- statistics over the range ---- */
$st = $db->prepare(
    "SELECT MIN(power_w) mn, AVG(power_w) av, MAX(power_w) mx,
            SUM(power_wh) total, COUNT(*) n,
            SUM(CASE WHEN heating=1 THEN 1 ELSE 0 END) heat_min
     FROM dish WHERE ts >= :s AND error IS NULL AND power_w IS NOT NULL");
$st->bindValue(':s', $since, SQLITE3_INTEGER);
$s = $st->execute()->fetchArray(SQLITE3_ASSOC) ?: [];

/* Idle draw is the 5th percentile: the floor the dish sits at when nothing
   much is happening, which is more useful than an absolute minimum that may
   just be one odd sample. */
$idle = null;
$iq = $db->prepare(
    "SELECT power_w FROM dish
     WHERE ts >= :s AND error IS NULL AND power_w IS NOT NULL
     ORDER BY power_w LIMIT 1 OFFSET
       (SELECT COUNT(*)/20 FROM dish
        WHERE ts >= :s AND error IS NULL AND power_w IS NOT NULL)");
$iq->bindValue(':s', $since, SQLITE3_INTEGER);
$ir = $iq->execute()->fetchArray(SQLITE3_ASSOC);
if ($ir) $idle = round((float) $ir['power_w'], 1);

/* Rate per day, derived from the time actually covered rather than by
   averaging calendar days. The first and last day of a range are almost
   always partial, and averaging those in drags the figure down — which then
   propagates into a badly wrong per-year number. */
$cov = $db->prepare(
    "SELECT SUM(COALESCE(sample_s, 60)) c, SUM(power_wh) w
     FROM dish WHERE ts >= :s AND error IS NULL AND power_wh IS NOT NULL");
$cov->bindValue(':s', $since, SQLITE3_INTEGER);
$cv = $cov->execute()->fetchArray(SQLITE3_ASSOC) ?: [];
$coveredS = max(1, (int) ($cv['c'] ?? 0));
$avgDayWh = (float) ($cv['w'] ?? 0) / $coveredS * 86400;

out([
    'supported' => true,
    'range'     => $range,
    'now'       => $now,
    'bucket'    => $bucket,
    'price'     => $price ?: null,
    'currency'  => $cur,
    'latest'    => $latest,
    'today_wh'      => round($today, 1),
    'yesterday_wh'  => round($yesterday, 1),
    'month_wh'      => round($month, 1),
    'projected_wh'  => round($projected, 1),
    'avg_day_wh'    => round($avgDayWh, 1),
    'covered_s'     => $coveredS,
    'stats' => [
        'min_w'  => $s['mn'] !== null ? round((float) $s['mn'], 1) : null,
        'idle_w' => $idle,
        'avg_w'  => $s['av'] !== null ? round((float) $s['av'], 1) : null,
        'max_w'  => $s['mx'] !== null ? round((float) $s['mx'], 1) : null,
        'total_wh' => round((float) ($s['total'] ?? 0), 1),
        'samples'  => (int) ($s['n'] ?? 0),
        'heating_s' => (int) ($s['heat_min'] ?? 0) * 60,
    ],
    'series' => $series,
    'days'   => $days,
]);
