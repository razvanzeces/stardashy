<?php
// cfspeed debug & settings backend — www/debug.php
// All actions POST JSON: {action: "...", ...}. Session-gated except
// login/status/setup_password.
//
// Auth: no password ships with the project. On first visit the UI asks you
// to choose an admin password, stored as a password_hash() in data/auth.json.
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

define('CFSPEED_DATA', dirname(__DIR__) . '/data');
define('CONFIG', CFSPEED_DATA . '/config.json');
define('AUTH_FILE', CFSPEED_DATA . '/auth.json');
define('ASN_CACHE', CFSPEED_DATA . '/asn_cache.json');

$in = json_decode((string) file_get_contents('php://input'), true) ?: [];
$action = $in['action'] ?? ($_GET['action'] ?? '');

function out($d){ echo json_encode($d); exit; }
function fail($m, $code = 400){ http_response_code($code); out(['error' => $m]); }

function default_config(): array {
    return [
        'location' => [
            'lat'   => null,
            'lon'   => null,
            'alt_m' => 0,
            'publish_precision' => 2,
        ],
        'dish' => [
            'target'  => '192.168.100.1:9200',
            'grpcurl' => '',
        ],
        'intervals' => [
            'speedtest_min' => 30,
            'dish_s'        => 60,
            'sats_s'        => 60,
            'live_poll_s'   => 2,
        ],
        'speedtest' => [
            'min_sane_mbps' => 5,
            'max_attempts'  => 3,
        ],
        'icmp' => [
            'enabled'    => true,
            'interval_s' => 30,
            'count'      => 5,
            'good_ms'    => 40,
            'warn_ms'    => 100,
            'targets'    => [
                ['label' => 'Cloudflare', 'host' => '1.1.1.1'],
                ['label' => 'Google',     'host' => '8.8.8.8'],
            ],
        ],
        'retention' => [
            'days' => 0,
        ],
        'energy' => [
            'price_per_kwh' => 0,   // 0 hides the cost panel
            'currency'      => 'EUR',
        ],
        'usage' => [
            'cycle_day' => 1,     // day of month the billing cycle restarts
            'cap_gb'    => 0,     // 0 = no cap, only informational either way
        ],
        'telegram' => [
            'token'   => '',
            'chat_id' => '',
        ],
        'alerts' => [
            'test_fail'      => true,
            'retry'          => true,
            'low_speed'      => false,
            'low_speed_mbps' => 20,
            'dish_down'      => true,
            'dish_hw'        => true,
            'high_drop'      => true,
            'drop_pct'       => 5,
            'new_ip'         => true,
        ],
    ];
}
function load_config(): array {
    $c = is_file(CONFIG) ? (json_decode((string) file_get_contents(CONFIG), true) ?: []) : [];
    return array_replace_recursive(default_config(), $c);
}

// ---------------- auth ----------------
function auth_hash(): ?string {
    if (!is_file(AUTH_FILE)) return null;
    $j = json_decode((string) file_get_contents(AUTH_FILE), true) ?: [];
    return $j['hash'] ?? null;
}
function auth_store(string $password): bool {
    $tmp = AUTH_FILE . '.tmp';
    $body = json_encode(['hash' => password_hash($password, PASSWORD_DEFAULT)]);
    if (@file_put_contents($tmp, $body) === false) return false;
    @chmod($tmp, 0640);
    return rename($tmp, AUTH_FILE);
}
function require_auth(){
    if (empty($_SESSION['cfspeed_auth'])) fail('unauthorized', 401);
}

function valid_target(string $t): bool {
    return $t !== '' && strlen($t) <= 253
        && preg_match('/^[A-Za-z0-9]([A-Za-z0-9.\-:]*[A-Za-z0-9])?$/', $t) === 1;
}
function valid_tg_token(string $t): bool {
    return preg_match('/^\d+:[A-Za-z0-9_\-]{20,64}$/', $t) === 1;
}

function run(array $argv, int $timeout = 30): array {
    $cmd = implode(' ', array_map('escapeshellarg', $argv));
    // GNU timeout is standard on Linux; skip the wrapper where it's missing
    static $hasTimeout = null;
    if ($hasTimeout === null)
        $hasTimeout = trim((string) @shell_exec('command -v timeout')) !== '';
    if ($hasTimeout) $cmd = "timeout {$timeout} {$cmd}";
    $p = proc_open($cmd,
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($p)) return [1, '', 'proc_open failed'];
    $o = stream_get_contents($pipes[1]);
    $e = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $rc = proc_close($p);
    return [$rc, $o, $e];
}

// ---------------- ASN via Team Cymru DNS ----------------
function asn_lookup(string $ip): array {
    static $cache = null;
    if ($cache === null) {
        $cache = is_file(ASN_CACHE)
            ? (json_decode((string) file_get_contents(ASN_CACHE), true) ?: []) : [];
    }
    if (isset($cache[$ip])) return $cache[$ip];

    $res = ['asn' => null, 'org' => null, 'prefix' => null, 'cc' => null];
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
        && !preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.|100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.|169\.254\.)/', $ip)) {
        $rev = implode('.', array_reverse(explode('.', $ip)));
        $txt = @dns_get_record($rev . '.origin.asn.cymru.com', DNS_TXT);
        if ($txt && isset($txt[0]['txt'])) {
            $parts = array_map('trim', explode('|', $txt[0]['txt']));
            $asn = (int) explode(' ', $parts[0])[0];
            $res['asn'] = $asn;
            $res['prefix'] = $parts[1] ?? null;
            $res['cc'] = $parts[2] ?? null;
            $t2 = @dns_get_record('AS' . $asn . '.asn.cymru.com', DNS_TXT);
            if ($t2 && isset($t2[0]['txt'])) {
                $p2 = array_map('trim', explode('|', $t2[0]['txt']));
                $res['org'] = $p2[4] ?? null;
            }
        }
    }
    if ($res['org'] !== null) {
        $org = substr(trim((string) preg_replace('/[<>"\'\x00-\x1F\x7F]/', '', $res['org'])), 0, 120);
        $res['org'] = $org === '' ? null : $org;
    }
    if ($res['cc'] !== null && !preg_match('/^[A-Za-z]{2}$/', $res['cc']))
        $res['cc'] = null;
    if ($res['prefix'] !== null && !preg_match('/^[0-9A-Fa-f.:\/]{1,64}$/', $res['prefix']))
        $res['prefix'] = null;
    $cache[$ip] = $res;
    @file_put_contents(ASN_CACHE, json_encode($cache));
    return $res;
}

// ================= actions =================
switch ($action) {

case 'status':
    out(['auth'  => !empty($_SESSION['cfspeed_auth']),
         'setup' => auth_hash() === null]);

case 'setup_password': {
    if (auth_hash() !== null) fail('password already set', 403);
    $pw = (string) ($in['password'] ?? '');
    if (strlen($pw) < 8) fail('password must be at least 8 characters');
    if (!auth_store($pw))
        fail('cannot write auth file (permissions on data/)', 500);
    session_regenerate_id(true);
    $_SESSION['cfspeed_auth'] = 1;
    out(['ok' => true]);
}

case 'login': {
    $hash = auth_hash();
    if ($hash === null) fail('no password set yet', 409);
    $pw = (string) ($in['password'] ?? '');
    usleep(300000); // slow brute force a bit
    if (password_verify($pw, $hash)) {
        session_regenerate_id(true);
        $_SESSION['cfspeed_auth'] = 1;
        out(['ok' => true]);
    }
    fail('wrong password', 403);
}

case 'change_password': {
    require_auth();
    $pw = (string) ($in['password'] ?? '');
    if (strlen($pw) < 8) fail('password must be at least 8 characters');
    if (!auth_store($pw))
        fail('cannot write auth file (permissions on data/)', 500);
    out(['ok' => true]);
}

case 'logout':
    $_SESSION = [];
    session_destroy();
    out(['ok' => true]);

case 'ping': {
    require_auth();
    $t = trim((string) ($in['target'] ?? ''));
    if (!valid_target($t)) fail('invalid target');
    $count = max(3, min(30, (int) ($in['count'] ?? 10)));
    [, $o, $e] = run(['ping', '-c', (string) $count, '-i', '0.25', '-W', '2', $t], 25);
    if ($o === '') fail('ping failed: ' . trim($e));
    preg_match_all('/icmp_seq=(\d+).*?time=([\d.]+) ms/', $o, $m, PREG_SET_ORDER);
    $rtts = [];
    foreach ($m as $x) $rtts[(int) $x[1]] = (float) $x[2];
    $seq = [];
    for ($i = 1; $i <= $count; $i++) $seq[] = $rtts[$i] ?? null; // null = lost
    $sum = [];
    if (preg_match('/(\d+) packets transmitted, (\d+)(?: packets)? received.*?([\d.]+)% packet loss/', $o, $x))
        $sum = ['sent' => (int) $x[1], 'recv' => (int) $x[2], 'loss' => (float) $x[3]];
    if (preg_match('/= ([\d.]+)\/([\d.]+)\/([\d.]+)\/([\d.]+)/', $o, $x))
        $sum += ['min' => (float) $x[1], 'avg' => (float) $x[2],
                 'max' => (float) $x[3], 'mdev' => (float) $x[4]];
    if (preg_match('/PING [^ ]+ \(([^)]+)\)/', $o, $x)) $sum['ip'] = $x[1];
    out(['target' => $t, 'rtts' => $seq, 'summary' => $sum]);
}

case 'mtr': {
    require_auth();
    $t = trim((string) ($in['target'] ?? ''));
    if (!valid_target($t)) fail('invalid target');
    $count = max(3, min(20, (int) ($in['count'] ?? 8)));
    $hops = [];
    [, $o, ] = run(['mtr', '--json', '-n', '-c', (string) $count, $t], 60);
    $j = json_decode($o, true);
    if ($j && isset($j['report']['hubs'])) {
        foreach ($j['report']['hubs'] as $h) {
            $hops[] = [
                'hop'  => (int) $h['count'],
                'ip'   => $h['host'],
                'loss' => round((float) $h['Loss%'], 1),
                'last' => round((float) $h['Last'], 1),
                'avg'  => round((float) $h['Avg'], 1),
                'best' => round((float) $h['Best'], 1),
                'wrst' => round((float) $h['Wrst'], 1),
            ];
        }
    } else {
        // fallback: traceroute
        [, $o2, $e2] = run(['traceroute', '-n', '-q', '1', '-w', '2', '-m', '30', $t], 60);
        if ($o2 === '') fail('mtr and traceroute unavailable: ' . trim($e2));
        foreach (explode("\n", $o2) as $line) {
            if (preg_match('/^\s*(\d+)\s+([\d.a-fA-F:]+)\s+([\d.]+) ms/', $line, $x))
                $hops[] = ['hop' => (int) $x[1], 'ip' => $x[2], 'loss' => 0.0,
                           'last' => (float) $x[3], 'avg' => (float) $x[3],
                           'best' => (float) $x[3], 'wrst' => (float) $x[3]];
            elseif (preg_match('/^\s*(\d+)\s+\*/', $line, $x))
                $hops[] = ['hop' => (int) $x[1], 'ip' => null, 'loss' => 100.0,
                           'last' => null, 'avg' => null, 'best' => null, 'wrst' => null];
        }
    }
    foreach ($hops as &$h) {
        if ($h['ip'] && $h['ip'] !== '???') {
            $h['ptr'] = (function($ip){ $p = @gethostbyaddr($ip);
                return ($p !== false && $p !== $ip
                    && preg_match('/^[A-Za-z0-9]([A-Za-z0-9._\-]{0,251}[A-Za-z0-9])?$/', $p))
                    ? $p : null; })($h['ip']);
            $h += asn_lookup($h['ip']);
        } else {
            $h['ip'] = null; $h['ptr'] = null;
            $h += ['asn' => null, 'org' => null, 'prefix' => null, 'cc' => null];
        }
    }
    out(['target' => $t, 'hops' => $hops]);
}

case 'dns': {
    require_auth();
    $t = trim((string) ($in['target'] ?? ''));
    if (!valid_target($t)) fail('invalid target');
    $res = [];
    foreach (['A' => DNS_A, 'AAAA' => DNS_AAAA, 'CNAME' => DNS_CNAME, 'MX' => DNS_MX] as $name => $type) {
        $t0 = microtime(true);
        $r = @dns_get_record($t, $type);
        $ms = round((microtime(true) - $t0) * 1000, 1);
        $vals = [];
        foreach ($r ?: [] as $rec) {
            $vals[] = $rec['ip'] ?? $rec['ipv6'] ?? $rec['target'] ?? '';
        }
        if ($vals) $res[] = ['type' => $name, 'ms' => $ms, 'values' => $vals];
    }
    out(['target' => $t, 'records' => $res]);
}

case 'http': {
    require_auth();
    $u = trim((string) ($in['url'] ?? ''));
    if (!preg_match('#^https?://[A-Za-z0-9.\-]+(:\d+)?(/[^\s"\']*)?$#', $u)) fail('invalid url');
    [, $o, $e] = run(['curl', '-o', '/dev/null', '-s', '-L', '--max-time', '15',
        '-w', '%{time_namelookup} %{time_connect} %{time_appconnect} %{time_starttransfer} %{time_total} %{http_code} %{remote_ip} %{size_download} %{speed_download}',
        $u], 20);
    $p = preg_split('/\s+/', trim($o));
    if (count($p) < 9) fail('curl failed: ' . trim($e));
    $ms = fn($v) => round((float) $v * 1000, 1);
    out(['url' => $u,
        'dns' => $ms($p[0]),
        'tcp' => round(($p[1] - $p[0]) * 1000, 1),
        'tls' => $p[2] > 0 ? round(($p[2] - $p[1]) * 1000, 1) : 0,
        'ttfb' => round(($p[3] - max($p[2], $p[1])) * 1000, 1),
        'transfer' => round(($p[4] - $p[3]) * 1000, 1),
        'total' => $ms($p[4]),
        'code' => (int) $p[5], 'ip' => $p[6],
        'bytes' => (int) $p[7], 'mbps' => round($p[8] * 8 / 1e6, 2)]);
}

case 'get_config':
    require_auth();
    out(load_config());

case 'save_config': {
    require_auth();
    /* Dish endpoints are deliberately not writable from here.
       A dish entry can carry an "exec" prefix that the collectors run as
       root, so accepting one over HTTP would turn the dashboard password
       into a root shell. Endpoints are edited in data/config.json by
       someone who already has root on the box. */
    $c = load_config();
    $n = $in['config'] ?? [];
    $iv = $n['intervals'] ?? [];
    $c['intervals']['speedtest_min'] = max(2, min(120, (int) ($iv['speedtest_min'] ?? 30)));
    $c['intervals']['dish_s']      = max(15, min(3600, (int) ($iv['dish_s'] ?? 60)));
    $c['intervals']['sats_s']      = max(30, min(3600, (int) ($iv['sats_s'] ?? 60)));
    $c['intervals']['live_poll_s'] = max(1, min(30, (int) ($iv['live_poll_s'] ?? 2)));
    $sp = $n['speedtest'] ?? [];
    $c['speedtest']['min_sane_mbps'] = max(0, min(100, (float) ($sp['min_sane_mbps'] ?? 5)));
    $c['speedtest']['max_attempts']  = max(1, min(5, (int) ($sp['max_attempts'] ?? 3)));
    if (isset($n['location'])) {           // absent key = leave untouched
        $lo = $n['location'];
        $lat = $lo['lat'] ?? null;
        $lon = $lo['lon'] ?? null;
        $c['location']['lat'] = ($lat === null || $lat === '')
            ? null : max(-90, min(90, (float) $lat));
        $c['location']['lon'] = ($lon === null || $lon === '')
            ? null : max(-180, min(180, (float) $lon));
        $c['location']['alt_m'] = max(0, min(9000, (float) ($lo['alt_m'] ?? 0)));
    }
    if (isset($n['retention']))
        $c['retention']['days'] = max(0, min(3650, (int) ($n['retention']['days'] ?? 0)));
    $tg = $n['telegram'] ?? [];
    $c['telegram']['token']   = preg_replace('/[^A-Za-z0-9:_\-]/', '', (string) ($tg['token'] ?? ''));
    $c['telegram']['chat_id'] = preg_replace('/[^0-9\-]/', '', (string) ($tg['chat_id'] ?? ''));
    $al = $n['alerts'] ?? [];
    foreach (['test_fail','retry','low_speed','dish_down','dish_hw','high_drop','new_ip'] as $k)
        $c['alerts'][$k] = !empty($al[$k]);
    $c['alerts']['low_speed_mbps'] = max(1, min(500, (float) ($al['low_speed_mbps'] ?? 20)));
    $c['alerts']['drop_pct']       = max(1, min(100, (float) ($al['drop_pct'] ?? 5)));

    if (isset($n['icmp']) && is_array($n['icmp'])) {
        $ni = $n['icmp'];
        $c['icmp']['enabled']    = !empty($ni['enabled']);
        $c['icmp']['interval_s'] = max(10, min(3600, (int) ($ni['interval_s'] ?? 30)));
        $c['icmp']['count']      = max(1, min(20, (int) ($ni['count'] ?? 5)));
        $c['icmp']['good_ms']    = max(1, min(2000, (float) ($ni['good_ms'] ?? 40)));
        $c['icmp']['warn_ms']    = max($c['icmp']['good_ms'] + 1,
                                       min(5000, (float) ($ni['warn_ms'] ?? 100)));
        // Hosts end up in a ping argument list, so only IPs and hostnames pass.
        $targets = [];
        foreach ((array) ($ni['targets'] ?? []) as $t) {
            $host = trim((string) ($t['host'] ?? ''));
            if ($host === '' || strlen($host) > 253) continue;
            if (!preg_match('/^[A-Za-z0-9]([A-Za-z0-9.\-:]*[A-Za-z0-9])?$/', $host)) continue;
            $label = trim((string) ($t['label'] ?? '')) ?: $host;
            $targets[] = ['label' => substr($label, 0, 40), 'host' => $host];
            if (count($targets) >= 8) break;
        }
        if ($targets) $c['icmp']['targets'] = $targets;
    }

    if (isset($n['energy']) && is_array($n['energy'])) {
        $c['energy']['price_per_kwh'] = max(0, min(100,
            (float) ($n['energy']['price_per_kwh'] ?? 0)));
        // Currency is only ever printed, so strip anything that is not a
        // letter, a currency sign or a separator.
        $cu = preg_replace('/[^\p{L}\p{Sc}. ]/u', '',
                           (string) ($n['energy']['currency'] ?? 'EUR'));
        $c['energy']['currency'] = substr(trim($cu), 0, 8);
    }

    if (isset($n['usage']) && is_array($n['usage'])) {
        $c['usage']['cycle_day'] = max(1, min(28, (int) ($n['usage']['cycle_day'] ?? 1)));
        $c['usage']['cap_gb']    = max(0, min(100000, (float) ($n['usage']['cap_gb'] ?? 0)));
    }

    // Preserve, never accept: whatever is on disk for dishes stays as-is.
    unset($c['__reject']);
    $tmp = CONFIG . '.tmp';
    if (@file_put_contents($tmp, json_encode($c, JSON_PRETTY_PRINT)) === false)
        fail('cannot write config (permissions on data/)', 500);
    rename($tmp, CONFIG);
    @chmod(CONFIG, 0664);
    out(['ok' => true, 'config' => $c,
         'note' => 'saved — systemd timers are being updated in the background']);
}

case 'tg_get_me': {
    require_auth();
    $tok = trim((string) ($in['token'] ?? load_config()['telegram']['token']));
    if (!valid_tg_token($tok)) fail('invalid token format');
    $r = @file_get_contents("https://api.telegram.org/bot{$tok}/getMe",
        false, stream_context_create(['http' => ['timeout' => 10]]));
    if ($r === false) fail('telegram unreachable');
    out(json_decode($r, true));
}

case 'tg_updates': {
    require_auth();
    $tok = trim((string) ($in['token'] ?? load_config()['telegram']['token']));
    if (!valid_tg_token($tok)) fail('invalid token format');
    $r = @file_get_contents("https://api.telegram.org/bot{$tok}/getUpdates?limit=30",
        false, stream_context_create(['http' => ['timeout' => 10]]));
    if ($r === false) fail('telegram unreachable');
    $j = json_decode($r, true);
    $chats = [];
    foreach (($j['result'] ?? []) as $u) {
        $ch = $u['message']['chat'] ?? $u['channel_post']['chat'] ?? $u['my_chat_member']['chat'] ?? null;
        if ($ch) $chats[$ch['id']] = [
            'id' => $ch['id'],
            'name' => $ch['title'] ?? trim(($ch['first_name'] ?? '') . ' ' . ($ch['last_name'] ?? ''))
                      ?: ($ch['username'] ?? ''),
            'type' => $ch['type'],
        ];
    }
    out(['chats' => array_values($chats),
         'hint' => $chats ? null
             : 'No updates. Send any message to your bot in Telegram first, then retry.']);
}

case 'tg_test': {
    require_auth();
    $cfg = load_config();
    $tok = trim((string) ($in['token'] ?? $cfg['telegram']['token']));
    $chat = trim((string) ($in['chat_id'] ?? $cfg['telegram']['chat_id']));
    if (!valid_tg_token($tok)) fail('invalid token format');
    if ($chat === '' || !preg_match('/^-?\d+$/', $chat)) fail('invalid chat_id');
    $msg = "\u{1F6F0} STARLINK MONITORING\nTest message — Telegram integration works.";
    $ctx = stream_context_create(['http' => [
        'method' => 'POST', 'timeout' => 10,
        'header' => "Content-Type: application/json\r\n",
        'content' => json_encode(['chat_id' => $chat, 'text' => $msg]),
    ]]);
    $r = @file_get_contents("https://api.telegram.org/bot{$tok}/sendMessage", false, $ctx);
    if ($r === false) fail('telegram unreachable');
    out(json_decode($r, true));
}

default:
    fail('unknown action', 404);
}
