<?php
// cfspeed updater API — www/update.php
//
// Version check + update trigger. Everything except 'check' requires the
// admin session created by debug.php. The web user never touches program
// files itself: 'apply' only writes data/update_request.json, which a
// systemd .path unit picks up and runs update.py as root.
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store');

define('CFSPEED_BASE', dirname(__DIR__));
define('CFSPEED_DATA', CFSPEED_BASE . '/data');
define('VERSION_FILE', CFSPEED_BASE . '/VERSION');
define('CHECK_CACHE', CFSPEED_DATA . '/update_check.json');
define('REQUEST_FILE', CFSPEED_DATA . '/update_request.json');
define('STATUS_FILE', CFSPEED_DATA . '/update_status.json');

const REPO_OWNER = 'razvanzeces';
const REPO_NAME  = 'stardashy';
const REPO_URL   = 'https://github.com/' . REPO_OWNER . '/' . REPO_NAME . '.git';
const CACHE_TTL  = 21600;   // 6 h between GitHub API calls

$in = json_decode((string) file_get_contents('php://input'), true) ?: [];
$action = $in['action'] ?? ($_GET['action'] ?? 'check');

function out($d){ echo json_encode($d); exit; }
function fail($m, $code = 400){ http_response_code($code); out(['error' => $m]); }
function require_auth(){
    if (empty($_SESSION['cfspeed_auth'])) fail('unauthorized', 401);
}

function local_version(): string {
    $v = is_file(VERSION_FILE) ? trim((string) file_get_contents(VERSION_FILE)) : '';
    return $v !== '' ? $v : '0.0.0';
}

/** Normalise "v3.1.0" / "3.1.0" to a version_compare-friendly string. */
function norm(string $v): string {
    return ltrim(trim($v), 'vV');
}

function gh_get(string $url) {
    $ctx = stream_context_create(['http' => [
        'timeout' => 10,
        'header'  => "User-Agent: cfspeed-updater\r\nAccept: application/vnd.github+json\r\n",
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) return null;
    $code = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $code = (int) $m[1];
    }
    if ($code !== 200) return null;
    return json_decode($body, true);
}

/** Ask GitHub for the newest release, falling back to the newest tag. */
function fetch_latest(): ?array {
    $base = 'https://api.github.com/repos/' . REPO_OWNER . '/' . REPO_NAME;

    $rel = gh_get($base . '/releases/latest');
    if (is_array($rel) && !empty($rel['tag_name'])) {
        return [
            'version'      => norm($rel['tag_name']),
            'tag'          => $rel['tag_name'],
            'name'         => $rel['name'] ?? $rel['tag_name'],
            'notes'        => mb_substr((string) ($rel['body'] ?? ''), 0, 4000),
            'url'          => $rel['html_url'] ?? '',
            'published_at' => $rel['published_at'] ?? null,
        ];
    }

    $tags = gh_get($base . '/tags?per_page=30');
    if (is_array($tags) && $tags) {
        $best = null;
        foreach ($tags as $t) {
            $n = norm((string) ($t['name'] ?? ''));
            if ($n === '' || !preg_match('/^\d+(\.\d+)*/', $n)) continue;
            if ($best === null || version_compare($n, norm($best['name']), '>')) $best = $t;
        }
        if ($best) return [
            'version'      => norm($best['name']),
            'tag'          => $best['name'],
            'name'         => $best['name'],
            'notes'        => '',
            'url'          => 'https://github.com/' . REPO_OWNER . '/' . REPO_NAME
                              . '/releases/tag/' . rawurlencode($best['name']),
            'published_at' => null,
        ];
    }
    return null;
}

function read_status(): ?array {
    if (!is_file(STATUS_FILE)) return null;
    return json_decode((string) file_get_contents(STATUS_FILE), true) ?: null;
}

switch ($action) {

// Cached version check. Safe to call on every dashboard load: GitHub is
// only contacted once per CACHE_TTL (or when force is set by an admin).
case 'check': {
    $cur   = local_version();
    $force = !empty($in['force']) && !empty($_SESSION['cfspeed_auth']);

    $cache = is_file(CHECK_CACHE)
        ? (json_decode((string) file_get_contents(CHECK_CACHE), true) ?: []) : [];
    $fresh = isset($cache['ts']) && (time() - (int) $cache['ts'] < CACHE_TTL);

    if ($force || !$fresh) {
        $latest = fetch_latest();
        if ($latest !== null) {
            $cache = ['ts' => time(), 'latest' => $latest];
            $tmp = CHECK_CACHE . '.tmp';
            if (@file_put_contents($tmp, json_encode($cache)) !== false) {
                @rename($tmp, CHECK_CACHE);
                @chmod(CHECK_CACHE, 0664);
            }
        } elseif (!$cache) {
            out(['current' => $cur, 'latest' => null, 'update_available' => false,
                 'checked_at' => null, 'offline' => true,
                 'status' => read_status()]);
        }
    }

    $latest = $cache['latest'] ?? null;
    $avail = $latest && version_compare($latest['version'], norm($cur), '>');
    out([
        'current'          => $cur,
        'latest'           => $latest,
        'update_available' => (bool) $avail,
        'checked_at'       => $cache['ts'] ?? null,
        'repo'             => 'https://github.com/' . REPO_OWNER . '/' . REPO_NAME,
        'status'           => read_status(),
    ]);
}

case 'status':
    out(['current' => local_version(), 'status' => read_status()]);

// Queue an update. The systemd path unit does the actual work as root.
case 'apply': {
    require_auth();
    $cache = is_file(CHECK_CACHE)
        ? (json_decode((string) file_get_contents(CHECK_CACHE), true) ?: []) : [];
    $latest = $cache['latest'] ?? null;
    if (!$latest) fail('run a version check first');

    $tag = (string) ($in['ref'] ?? $latest['tag']);
    // Only the tag we actually advertised may be installed — never a
    // caller-supplied ref that was not vetted by the check step.
    if ($tag !== $latest['tag']) fail('unknown version requested');
    if (!preg_match('/^[A-Za-z0-9._-]{1,64}$/', $tag)) fail('invalid version tag');

    $st = read_status();
    if ($st && ($st['state'] ?? '') === 'running'
        && time() - (int) ($st['ts'] ?? 0) < 900)
        fail('an update is already running', 409);

    $body = json_encode(['ref' => $tag, 'repo' => REPO_URL,
                         'requested_at' => time()]);
    $tmp = REQUEST_FILE . '.tmp';
    if (@file_put_contents($tmp, $body) === false)
        fail('cannot write update request (permissions on data/)', 500);
    @chmod($tmp, 0664);
    if (!@rename($tmp, REQUEST_FILE))
        fail('cannot queue update request', 500);

    // Seed a status so the UI has something to poll immediately, even if
    // the systemd unit is not installed (in which case it stays queued).
    $seed = json_encode(['state' => 'running', 'ts' => time(),
                         'message' => 'Update queued…',
                         'from_version' => local_version(), 'to_ref' => $tag]);
    @file_put_contents(STATUS_FILE, $seed);
    @chmod(STATUS_FILE, 0664);

    out(['ok' => true, 'queued' => $tag,
         'note' => 'update queued — this page will report progress']);
}

default:
    fail('unknown action', 404);
}
