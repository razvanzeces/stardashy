<?php
// cfspeed sky snapshot — www/sky.php
header('Content-Type: application/json');
header('Cache-Control: no-store');

$f = dirname(__DIR__) . '/data/sky.json';
if (!is_file($f)) {
    http_response_code(503);
    echo json_encode(['error' => 'no sky data yet']);
    exit;
}
readfile($f);
