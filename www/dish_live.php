<?php
// cfspeed dish live proxy — www/dish_live.php
// Calls grpcurl get_status and returns a compact JSON for the live UI.
header('Content-Type: application/json');
header('Cache-Control: no-store');

$cfgFile = dirname(__DIR__) . '/data/config.json';
$dishCfg = [];
if (is_file($cfgFile)) {
    $dishCfg = (json_decode((string) file_get_contents($cfgFile), true) ?: [])['dish'] ?? [];
}
$target  = $dishCfg['target'] ?? '192.168.100.1:9200';
$grpcurl = $dishCfg['grpcurl'] ?? '';
if ($grpcurl === '' || !is_executable($grpcurl)) {
    foreach (['/usr/local/bin/grpcurl', '/usr/bin/grpcurl', '/opt/homebrew/bin/grpcurl'] as $g) {
        if (is_executable($g)) { $grpcurl = $g; break; }
    }
}
if (!preg_match('/^[A-Za-z0-9.\-]+:\d+$/', $target) || $grpcurl === '') {
    http_response_code(502);
    echo json_encode(['error' => 'grpcurl or dish target not configured']);
    exit;
}

$cmd = escapeshellarg($grpcurl) . ' -plaintext -max-time 3 -d ' .
       escapeshellarg('{"get_status":{}}') . ' ' .
       escapeshellarg($target) . ' SpaceX.API.Device.Device/Handle 2>/dev/null';

$out = shell_exec($cmd);
if (!$out) {
    http_response_code(502);
    echo json_encode(['error' => 'dish unreachable']);
    exit;
}

$j = json_decode($out, true);
$st = $j['dishGetStatus'] ?? null;
if (!$st) {
    http_response_code(502);
    echo json_encode(['error' => 'bad response']);
    exit;
}

$alerts = [];
foreach (($st['alerts'] ?? []) as $k => $v) {
    if ($v) $alerts[] = $k;
}

echo json_encode([
    'ts'        => time(),
    'down_mbps' => round(($st['downlinkThroughputBps'] ?? 0) / 1e6, 2),
    'up_mbps'   => round(($st['uplinkThroughputBps'] ?? 0) / 1e6, 2),
    'pop_ms'    => round($st['popPingLatencyMs'] ?? 0, 1),
    'obstr_pct' => round(($st['obstructionStats']['fractionObstructed'] ?? 0) * 100, 3),
    'gps_sats'  => $st['gpsStats']['gpsSats'] ?? null,
    'gps_valid' => $st['gpsStats']['gpsValid'] ?? null,
    'eth_mbps'  => $st['ethSpeedMbps'] ?? null,
    'uptime_s'  => (int)($st['deviceState']['uptimeS'] ?? 0),
    'tilt'      => round($st['alignmentStats']['tiltAngleDeg'] ?? 0, 1),
    'azim'      => round($st['alignmentStats']['boresightAzimuthDeg'] ?? 0, 1),
    'elev'      => round($st['alignmentStats']['boresightElevationDeg'] ?? 0, 1),
    'sw'        => $st['deviceInfo']['softwareVersion'] ?? '',
    'hw'        => $st['deviceInfo']['hardwareVersion'] ?? '',
    'snr_ok'    => $st['isSnrAboveNoiseFloor'] ?? null,
    'alerts'    => $alerts,
]);
