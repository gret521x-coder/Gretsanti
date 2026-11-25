<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  Pindutan leaderboard — api-leaderboard.php
 *  ------------------------------------------------------------------------
 *  Upload this one file next to the game on aidekupal.unaux.com.
 *  Requirements: PHP 7.4+ (nothing else — no MySQL, no composer, no extensions
 *  beyond the JSON ones that ship with PHP by default).
 *
 *  Storage (created automatically, next to this file)
 *    data/pindutan-leaderboard.json     the table
 *    data/pindutan-lb-rate.json         per-IP rate limiting counters
 *
 *  Endpoints
 *    GET  api-leaderboard.php?action=top&mode=all|duel|group&limit=25
 *    POST api-leaderboard.php?action=report   {name, mode, win, score, lines, ts, sig}
 *    POST api-leaderboard.php?action=reset    {key}
 *
 *  What counts: human battles only. A player reports their own side of a
 *  1v1 ("duel") or a group battle — a win or a loss — plus the score they
 *  finished with. Solo runs and VS CPU runs are never sent.
 *
 *  Honest security note: the signature below keeps casual players honest and
 *  stops dumb curl spam, but the shared secret ships inside the HTML, so a
 *  determined person can forge a result. Treat this as a scoreboard for
 *  friends, not a ranked ladder with prizes.
 * ============================================================================
 */

/* ---------------------------------------------------------------- config */
/**
 * Where the ladder lives.
 *
 * Default: a "data" folder beside this file. That is fine, but anything inside
 * the web root can be requested by a browser, so the folder is guarded by an
 * .htaccess and an empty index.html.
 *
 * Safer, and one line: put it outside the web root, for example
 *     const DATA_DIR = __DIR__ . '/../pindutan-data';
 * On XAMPP that is the difference between htdocs\game\data (reachable) and
 * htdocs\pindutan-data (not reachable at all). No .htaccess needed then, and
 * no "hidden file" tricks — the file simply is not on the web.
 */
define('DATA_DIR', __DIR__ . '/data');

const LB_SECRET    = 'kdl-pindutan-lb-2026-7f3a';
const MIN_NAME     = 2;
const MAX_NAME     = 14;
const MAX_SCORE    = 5000000;
const MAX_PLAYERS  = 900;          // rows kept; the weakest are dropped
const MAX_LIMIT    = 100;
const RATE_WINDOW  = 60;           // seconds
const RATE_REPORTS = 30;           // reports per window per IP
const RATE_READS   = 120;          // reads per window per IP
const TS_SKEW      = 900;          // seconds a report may be off before it is stale

/* Names that get the crown next to them on the ladder, for everyone to see.
   Lowercase, exactly as it is typed in the game. This is not a permission —
   it is decoration — so it lives here instead of in the game. */
const CREATOR_NAMES = ['ace'];

/* ------------------------------------------------------------------ CORS */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Max-Age: 86400');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* --------------------------------------------------------------- helpers */
function out(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $message, int $code = 400): void
{
    out(['ok' => false, 'error' => $message], $code);
}

function data_dir(): string
{
    $dir = DATA_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        fail('storage unavailable', 500);
    }

    /* Apache 2.4 and 2.2 speak different languages; each block is ignored by
       the other generation instead of turning the folder into a 500 */
    $guard = $dir . '/.htaccess';
    if (!file_exists($guard)) {
        @file_put_contents(
            $guard,
            "# keep the ladder off the web\n" .
            "<IfModule mod_authz_core.c>\n" .
            "  Require all denied\n" .
            "</IfModule>\n" .
            "<IfModule !mod_authz_core.c>\n" .
            "  Order allow,deny\n" .
            "  Deny from all\n" .
            "</IfModule>\n"
        );
    }

    /* a blank index stops a directory listing even when .htaccess is ignored */
    $index = $dir . '/index.html';
    if (!file_exists($index)) {
        @file_put_contents($index, "<!doctype html><title>not here</title>");
    }

    return $dir;
}

function read_json(string $file, array $fallback): array
{
    if (!is_file($file)) {
        return $fallback;
    }
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') {
        return $fallback;
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $fallback;
}

function write_json(string $file, array $data): bool
{
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $raw = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($raw === false) {
        return false;
    }
    if (@file_put_contents($tmp, $raw, LOCK_EX) === false) {
        return false;
    }
    return @rename($tmp, $file);
}

function clean_name(string $raw): string
{
    $raw = preg_replace('/[\x00-\x1F\x7F]/u', '', $raw) ?? '';
    $raw = preg_replace('/[^\p{L}0-9 ._-]/u', '', $raw) ?? '';
    $raw = preg_replace('/\s+/u', ' ', $raw) ?? '';
    $raw = trim($raw);
    if (function_exists('mb_substr')) {
        $raw = mb_substr($raw, 0, MAX_NAME);
    } else {
        $raw = substr($raw, 0, MAX_NAME);
    }
    return $raw;
}

function name_key(string $name): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
}

function client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (empty($_SERVER[$key])) {
            continue;
        }
        $first = trim(explode(',', (string) $_SERVER[$key])[0]);
        if ($first !== '') {
            return $first;
        }
    }
    return 'unknown';
}

function rate_limit(int $max): void
{
    $file = data_dir() . '/pindutan-lb-rate.json';
    $now = time();
    $state = read_json($file, []);
    $ip = client_ip();

    /* keep the file small: drop windows that have already expired */
    foreach ($state as $key => $entry) {
        if (!is_array($entry) || ($now - (int) ($entry['since'] ?? 0)) > RATE_WINDOW) {
            unset($state[$key]);
        }
    }

    $entry = $state[$ip] ?? ['since' => $now, 'count' => 0];
    if (($now - (int) $entry['since']) > RATE_WINDOW) {
        $entry = ['since' => $now, 'count' => 0];
    }
    $entry['count'] = (int) $entry['count'] + 1;
    $state[$ip] = $entry;
    if (count($state) > 4000) {
        $state = array_slice($state, -2000, null, true);
    }
    write_json($file, $state);

    if ($entry['count'] > $max) {
        fail('slow down — too many requests', 429);
    }
}

/**
 * The same 64-bit digest the game computes in JavaScript (lbDigest), written so
 * no intermediate ever leaves a 64-bit integer: h1 is FNV-1a 32-bit (the largest
 * product is ~7.2e16), h2 is a plain u32 rolling hash (largest product ~5.6e11).
 * This is deliberately not SHA-256: a game served over plain http on a LAN
 * address has no crypto.subtle, so both sides need an algorithm they can both
 * compute everywhere.
 */
function lb_hash(string $str): string
{
    $h1 = 0x811c9dc5;
    $h2 = 0;
    $len = strlen($str);
    for ($i = 0; $i < $len; $i++) {
        $c = ord($str[$i]) & 0xFF;
        $h1 = (($h1 ^ $c) * 16777619) & 0xFFFFFFFF;
        $h2 = (($h2 * 131) + $c) & 0xFFFFFFFF;
    }
    return sprintf('%08x%08x', $h1, $h2);
}

function signature(array $r): string
{
    $base = $r['name'] . '|' . $r['mode'] . '|' . $r['win'] . '|' . $r['score'] . '|' . $r['lines'] . '|' . $r['ts'];
    return lb_hash($base . '|' . LB_SECRET);
}

/* ------------------------------------------------------------ the table */
function blank_player(string $name): array
{
    return [
        'name' => $name,
        'wins' => 0,
        'losses' => 0,
        'best' => 0,
        'streak' => 0,
        'bestStreak' => 0,
        'last' => 0,
        'duel' => ['wins' => 0, 'losses' => 0, 'best' => 0],
        'group' => ['wins' => 0, 'losses' => 0, 'best' => 0]
    ];
}

function view_row(string $key, array $p, string $mode, int $rank): array
{
    $slice = $mode === 'all'
        ? ['wins' => (int) $p['wins'], 'losses' => (int) $p['losses'], 'best' => (int) $p['best']]
        : [
            'wins' => (int) ($p[$mode]['wins'] ?? 0),
            'losses' => (int) ($p[$mode]['losses'] ?? 0),
            'best' => (int) ($p[$mode]['best'] ?? 0)
        ];
    $played = $slice['wins'] + $slice['losses'];
    return [
        'rank' => $rank,
        'name' => (string) $p['name'],
        'crown' => in_array(name_key((string) $p['name']), CREATOR_NAMES, true),
        'wins' => $slice['wins'],
        'losses' => $slice['losses'],
        'best' => $slice['best'],
        'played' => $played,
        'rate' => $played > 0 ? round($slice['wins'] * 100 / $played) : 0,
        'streak' => (int) $p['streak'],
        'bestStreak' => (int) $p['bestStreak'],
        'last' => (int) $p['last']
    ];
}

function sorted_rows(array $players, string $mode, int $limit): array
{
    $rows = [];
    foreach ($players as $key => $p) {
        if (!is_array($p) || !isset($p['name'])) {
            continue;
        }
        $wins = $mode === 'all' ? (int) $p['wins'] : (int) ($p[$mode]['wins'] ?? 0);
        if ($wins <= 0) {
            continue;                       /* nobody with zero wins is ranked */
        }
        $rows[] = [$key, $p, $wins];
    }
    usort($rows, static function (array $a, array $b) use ($mode): int {
        if ($a[2] !== $b[2]) {
            return $b[2] <=> $a[2];         /* most wins first */
        }
        $ab = $mode === 'all' ? (int) $a[1]['best'] : (int) ($a[1][$mode]['best'] ?? 0);
        $bb = $mode === 'all' ? (int) $b[1]['best'] : (int) ($b[1][$mode]['best'] ?? 0);
        if ($ab !== $bb) {
            return $bb <=> $ab;             /* then the higher best score */
        }
        return strcmp((string) $a[1]['name'], (string) $b[1]['name']);
    });

    $out = [];
    $rank = 0;
    foreach ($rows as $entry) {
        $rank++;
        if ($rank > $limit) {
            break;
        }
        $out[] = view_row($entry[0], $entry[1], $mode, $rank);
    }
    return $out;
}

function rank_of(array $players, string $mode, string $key): array
{
    $rows = sorted_rows($players, $mode, MAX_PLAYERS);
    foreach ($rows as $row) {
        if (name_key($row['name']) === $key) {
            return [$row['rank'], count($rows)];
        }
    }
    return [0, count($rows)];
}

/* --------------------------------------------------------------- routing */
$action = strtolower((string) ($_GET['action'] ?? 'top'));
$mode = strtolower((string) ($_GET['mode'] ?? 'all'));
if (!in_array($mode, ['all', 'duel', 'group'], true)) {
    fail('unknown mode');
}

$dir = data_dir();
$table_file = $dir . '/pindutan-leaderboard.json';
$table = read_json($table_file, ['updated' => 0, 'players' => []]);
if (!isset($table['players']) || !is_array($table['players'])) {
    $table = ['updated' => 0, 'players' => []];
}

/* --------------------------------------------------------------- read it */
if ($action === 'top') {
    rate_limit(RATE_READS);
    $limit = (int) ($_GET['limit'] ?? 25);
    $limit = max(1, min(MAX_LIMIT, $limit));
    out([
        'ok' => true,
        'mode' => $mode,
        'updated' => (int) $table['updated'],
        'players' => count($table['players']),
        'rows' => sorted_rows($table['players'], $mode, $limit)
    ]);
}

/* ------------------------------------------------------------- one result */
if ($action === 'report') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        fail('report must be POST', 405);
    }
    rate_limit(RATE_REPORTS);

    $body = file_get_contents('php://input') ?: '';
    $input = json_decode($body, true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    $name = clean_name((string) ($input['name'] ?? ''));
    $mode_in = strtolower((string) ($input['mode'] ?? ''));
    $win = (int) ($input['win'] ?? 0) === 1 ? 1 : 0;
    $score = (int) ($input['score'] ?? 0);
    $lines = (int) ($input['lines'] ?? 0);
    $ts = (int) ($input['ts'] ?? 0);
    $sig = strtolower(substr((string) ($input['sig'] ?? ''), 0, 16));

    if (function_exists('mb_strlen')) {
        $name_len = mb_strlen($name);
    } else {
        $name_len = strlen($name);
    }
    if ($name_len < MIN_NAME || $name_len > MAX_NAME) {
        fail('name must be ' . MIN_NAME . '–' . MAX_NAME . ' characters');
    }
    if (!in_array($mode_in, ['duel', 'group'], true)) {
        fail('mode must be duel or group');
    }
    if ($score < 0 || $score > MAX_SCORE) {
        fail('score out of range');
    }
    if ($lines < 0 || $lines > 100000) {
        fail('lines out of range');
    }
    $now = time();
    if ($ts < $now - TS_SKEW || $ts > $now + TS_SKEW) {
        fail('stale report');
    }

    $check = ['name' => $name, 'mode' => $mode_in, 'win' => $win, 'score' => $score, 'lines' => $lines, 'ts' => $ts];
    if (!hash_equals(signature($check), $sig)) {
        fail('bad signature', 403);
    }

    $key = name_key($name);
    $player = $table['players'][$key] ?? blank_player($name);

    /* one report per battle: the same name + second twice is ignored */
    if ((int) $player['last'] >= $ts) {
        out(['ok' => true, 'duplicate' => true, 'name' => $player['name']]);
    }

    $player['name'] = $name;
    $player['last'] = $ts;
    if (!isset($player['duel'])) {
        $player['duel'] = ['wins' => 0, 'losses' => 0, 'best' => 0];
    }
    if (!isset($player['group'])) {
        $player['group'] = ['wins' => 0, 'losses' => 0, 'best' => 0];
    }

    if ($win === 1) {
        $player['wins'] = (int) $player['wins'] + 1;
        $player[$mode_in]['wins'] = (int) $player[$mode_in]['wins'] + 1;
        $player['streak'] = (int) $player['streak'] + 1;
        if ($player['streak'] > (int) $player['bestStreak']) {
            $player['bestStreak'] = (int) $player['streak'];
        }
    } else {
        $player['losses'] = (int) $player['losses'] + 1;
        $player[$mode_in]['losses'] = (int) $player[$mode_in]['losses'] + 1;
        $player['streak'] = 0;
    }
    if ($score > (int) $player['best']) {
        $player['best'] = $score;
    }
    if ($score > (int) $player[$mode_in]['best']) {
        $player[$mode_in]['best'] = $score;
    }

    $table['players'][$key] = $player;
    $table['updated'] = $now;

    /* keep the file bounded — drop the weakest players when it overflows */
    if (count($table['players']) > MAX_PLAYERS) {
        uasort($table['players'], static function (array $a, array $b): int {
            if ((int) $a['wins'] !== (int) $b['wins']) {
                return (int) $b['wins'] <=> (int) $a['wins'];
            }
            return (int) $b['best'] <=> (int) $a['best'];
        });
        $table['players'] = array_slice($table['players'], 0, MAX_PLAYERS, true);
    }

    if (!write_json($table_file, $table)) {
        fail('could not save the table', 500);
    }

    [$rank, $of] = rank_of($table['players'], $mode_in, $key);
    out([
        'ok' => true,
        'duplicate' => false,
        'rank' => $rank,
        'of' => $of,
        'you' => view_row($key, $player, $mode_in, $rank),
        'rows' => sorted_rows($table['players'], $mode_in, 10)
    ]);
}

/* ----------------------------------------------------------------- wipe it */
if ($action === 'reset') {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        fail('reset must be POST', 405);
    }
    $posted = $_POST['key'] ?? null;
    if ($posted === null) {
        $raw = json_decode(file_get_contents('php://input') ?: '', true);
        $posted = is_array($raw) ? ($raw['key'] ?? '') : '';
    }
    $key = (string) $posted;
    if (!hash_equals(LB_SECRET, $key)) {
        fail('nope', 403);
    }
    write_json($table_file, ['updated' => time(), 'players' => []]);
    out(['ok' => true, 'cleared' => true]);
}

fail('unknown action', 404);
