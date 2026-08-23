<?php
// cfspeed TLE passthrough — www/tles.php
// Serves the CelesTrak TLE cache maintained by sat_tracker.py so the browser
// can propagate satellites client-side.
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: max-age=1800');

$f = dirname(__DIR__) . '/data/starlink.tle';
if (!is_file($f)) {
    http_response_code(503);
    echo "no TLE cache yet\n";
    exit;
}
readfile($f);
