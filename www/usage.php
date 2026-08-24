<?php
// cfspeed data usage and outage timeline — www/usage.php
//
// Usage comes from bytes the dish actually moved (dish_collector integrates
// the per-second throughput ring buffer), not from sampling a rate. Outages
// are classified by where they happened, which is the useful part: a dish the
// Pi cannot reach is a different problem from a satellite link that dropped,
// and both differ from the internet being down past the PoP.
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

$cfg = is_file($CFG) ? (json_decode((string) file_get_contents($CFG), true) ?: []) : [];
$billDay = max(1, min(28, (int) ($cfg['usage']['cycle_day'] ?? 1)));
$capGb   = max(0, (float) ($cfg['usage']['cap_gb'] ?? 0));
$tz      = date_default_timezone_get();

try {
    $db = new SQLite3($DB, SQLITE3_OPEN_READONLY);
} catch (Exception $e) {
    out(['error' => 'no database yet']);
}
$db->busyTimeout(3000);

$hasBytes = false;
$r = $db->query("PRAGMA table_info(dish)");
while ($c = $r->fetchArray(SQLITE3_ASSOC)) if ($c['name'] === 'bytes_down') $hasBytes = true;

$range = $_GET['range'] ?? '30d';
$spans = ['24h' => 86400, '7d' => 7 * 86400, '30d' => 30 * 86400, '90d' => 90 * 86400];
$span  = $spans[$range] ?? $spans['30d'];
$now   = time();
$since = $now - $span;

/* ---------------- data usage ---------------- */
$usage = null;
if ($hasBytes) {
    // Bucket by local day so "today" matches what the user's calendar says.
    $q = $db->prepare(
        "SELECT date(ts, 'unixepoch', 'localtime') AS d,
                SUM(COALESCE(bytes_down,0)) AS dn,
                SUM(COALESCE(bytes_up,0))   AS up,
                SUM(COALESCE(sample_s,0))   AS cov
         FROM dish WHERE ts >= :s AND error IS NULL
         GROUP BY d ORDER BY d");
    $q->bindValue(':s', $since, SQLITE3_INTEGER);
    $res = $q->execute();
    $days = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $days[] = ['day' => $row['d'],
                   'down' => (int) $row['dn'], 'up' => (int) $row['up'],
                   'total' => (int) ($row['dn'] + $row['up']),
                   'covered_s' => (int) $row['cov']];
    }

    // Current billing cycle: from day N of this month, or last month if we
    // have not reached day N yet.
    $today = (int) date('j');
    $cycleStart = $today >= $billDay
        ? mktime(0, 0, 0, (int) date('n'), $billDay, (int) date('Y'))
        : mktime(0, 0, 0, (int) date('n') - 1, $billDay, (int) date('Y'));
    $cq = $db->prepare(
        "SELECT SUM(COALESCE(bytes_down,0)) dn, SUM(COALESCE(bytes_up,0)) up,
                SUM(COALESCE(sample_s,0)) cov
         FROM dish WHERE ts >= :s AND error IS NULL");
    $cq->bindValue(':s', $cycleStart, SQLITE3_INTEGER);
    $c = $cq->execute()->fetchArray(SQLITE3_ASSOC) ?: [];
    $cycleTotal = (int) (($c['dn'] ?? 0) + ($c['up'] ?? 0));
    $elapsed = max(1, $now - $cycleStart);
    $cycleEnd = strtotime('+1 month', $cycleStart);
    $projected = (int) round($cycleTotal / $elapsed * ($cycleEnd - $cycleStart));

    $todayStart = strtotime('today');
    $tq = $db->prepare(
        "SELECT SUM(COALESCE(bytes_down,0)) dn, SUM(COALESCE(bytes_up,0)) up
         FROM dish WHERE ts >= :s AND error IS NULL");
    $tq->bindValue(':s', $todayStart, SQLITE3_INTEGER);
    $t = $tq->execute()->fetchArray(SQLITE3_ASSOC) ?: [];

    // How much of that was Stardashy testing itself. Speed tests are the one
    // consumer whose traffic this tool causes, so it should own up to it.
    $sq = $db->prepare(
        "SELECT SUM(COALESCE(bytes_down,0) + COALESCE(bytes_up,0)) b
         FROM results WHERE ts >= :s AND error IS NULL");
    $sq->bindValue(':s', $cycleStart, SQLITE3_INTEGER);
    $selfBytes = (int) (($sq->execute()->fetchArray(SQLITE3_ASSOC)['b']) ?? 0);

    $usage = [
        'days'          => $days,
        'today'         => (int) (($t['dn'] ?? 0) + ($t['up'] ?? 0)),
        'today_down'    => (int) ($t['dn'] ?? 0),
        'today_up'      => (int) ($t['up'] ?? 0),
        'cycle_total'   => $cycleTotal,
        'cycle_start'   => $cycleStart,
        'cycle_end'     => $cycleEnd,
        'cycle_day'     => $billDay,
        'projected'     => $projected,
        'cap_gb'        => $capGb ?: null,
        'coverage_pct'  => $elapsed > 0
            ? round(100 * min(1, (float) ($c['cov'] ?? 0) / $elapsed), 1) : null,
        'selftest'      => $selfBytes,
        'selftest_pct'  => $cycleTotal > 0 ? round(100 * $selfBytes / $cycleTotal, 1) : null,
    ];
}

/* ---------------- outage timeline ---------------- */
/* Walk the per-minute dish rows once and fold consecutive bad minutes into
   incidents. Severity ordering matters: if the Pi cannot reach the dish we
   cannot say anything about the link, so that classification wins. */
$q = $db->prepare(
    "SELECT ts, error, outage_s_60s, drop_rate_60s, fraction_obstructed
     FROM dish WHERE ts >= :s ORDER BY ts");
$q->bindValue(':s', $since, SQLITE3_INTEGER);
$res = $q->execute();

$dropThr = max(0.01, (float) ($cfg['alerts']['drop_pct'] ?? 5) / 100);
$events = [];
$open = null;

$flush = function () use (&$open, &$events) {
    if ($open) { $events[] = $open; $open = null; }
};

while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
    $ts = (int) $row['ts'];
    $kind = null;
    if ($row['error'] !== null)                       $kind = 'dish_unreachable';
    elseif ((int) ($row['outage_s_60s'] ?? 0) > 0)    $kind = 'link_outage';
    elseif ((float) ($row['drop_rate_60s'] ?? 0) >= $dropThr) $kind = 'degraded';

    if ($kind === null) { $flush(); continue; }

    // A gap longer than a few minutes is a separate incident, not one long one.
    if ($open && ($open['kind'] !== $kind || $ts - $open['last'] > 300)) $flush();

    if (!$open) {
        $open = ['kind' => $kind, 'start' => $ts, 'last' => $ts,
                 'minutes' => 0, 'outage_s' => 0, 'worst_drop' => 0.0,
                 'detail' => $kind === 'dish_unreachable'
                     ? preg_replace('/\s+/', ' ', substr((string) $row['error'], 0, 120)) : null];
    }
    $open['last'] = $ts;
    $open['minutes']++;
    $open['outage_s'] += (int) ($row['outage_s_60s'] ?? 0);
    $open['worst_drop'] = max($open['worst_drop'], (float) ($row['drop_rate_60s'] ?? 0));
}
$flush();

foreach ($events as &$e) {
    $e['end'] = $e['last'] + 60;
    $e['duration_s'] = $e['kind'] === 'link_outage' && $e['outage_s'] > 0
        ? $e['outage_s']                       // seconds actually lost
        : $e['end'] - $e['start'];
    $e['worst_drop'] = round($e['worst_drop'] * 100, 2);
    unset($e['last']);
}
unset($e);

$totals = ['dish_unreachable' => 0, 'link_outage' => 0, 'degraded' => 0];
foreach ($events as $e) $totals[$e['kind']] += $e['duration_s'];

/* Availability and coverage are deliberately separate numbers.

   When the collector cannot reach the dish we do not know whether the
   satellite link was up — only that we were not watching. Folding that into
   downtime would blame Starlink for what may be a LAN or Pi problem, and
   would make a monitoring gap look like an outage. So: availability is
   measured over the time we actually observed, and coverage says how much of
   the window that was. A low coverage figure is the signal to distrust the
   availability figure. */
$observedMin = (int) ($db->querySingle(
    "SELECT COUNT(*) FROM dish WHERE ts >= " . (int) $since
    . " AND error IS NULL") ?: 0);
$blindMin = (int) ($db->querySingle(
    "SELECT COUNT(*) FROM dish WHERE ts >= " . (int) $since
    . " AND error IS NOT NULL") ?: 0);
$observed = $observedMin * 60;
$blind    = $blindMin * 60;
$lost     = $totals['link_outage'];

out([
    'range'     => $range,
    'since'     => $since,
    'now'       => $now,
    'tz'        => $tz,
    'usage'     => $usage,
    'has_bytes' => $hasBytes,
    'events'    => array_reverse($events),      // newest first for the list
    'totals'    => $totals,
    'observed_s'    => $observed,
    'blind_s'       => $blind,
    'lost_s'        => $lost,
    'availability'  => $observed > 0
        ? round(100 * max(0, $observed - $lost) / $observed, 4) : null,
    'coverage_pct'  => ($observed + $blind) > 0
        ? round(100 * $observed / ($observed + $blind), 2) : null,
]);
