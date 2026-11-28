<?php
declare(strict_types=1);
/**
 * ============================================================================
 *  KDL / Alpha — the feedback inbox — api-feedback.php
 *  ------------------------------------------------------------------------
 *  Upload this one file next to the game on aidekupal.unaux.com.
 *  Requirements: PHP 7.4+ (nothing else — no database, no composer).
 *
 *  Why it exists
 *    The feedback card used to fill a mail app with the maker's address in it,
 *    which put that address on screen for anyone who opened the page. This
 *    file keeps the address on the server: the page only knows this script's
 *    URL, and the script is the only thing that knows where the mail goes.
 *
 *  Two files to upload beside it (both optional, neither is in the repo)
 *    feedback-to.txt    one line: the address the reports are mailed to.
 *                       Without it, reports are still written to the log below,
 *                       they are just never mailed.
 *    feedback-key.txt   one line: a long random word. It unlocks ?recent=.
 *                       Without it, reading the log through the web is refused.
 *
 *  Storage (created automatically, next to this file)
 *    data/feedback.jsonl        every report, one JSON object per line
 *    data/feedback-rate.json    per-IP rate limiting counters
 *
 *  Endpoints
 *    POST api-feedback.php                  {subject, body, kind, text, name,
 *                                            build, page, room, screen, browser,
 *                                            when}  -> {ok: true}
 *    GET  api-feedback.php?count=1          -> {ok: true, count: n}   (a pulse)
 *    GET  api-feedback.php?recent=KEY       -> {ok: true, items: [...]} (last 20)
 *
 *  The page never sends an address, and this file never echoes one back.
 * ============================================================================
 */

/* ---------------------------------------------------------------- config */
const DATA_DIR   = __DIR__ . '/data';
const LOG_FILE   = DATA_DIR . '/feedback.jsonl';
const RATE_FILE  = DATA_DIR . '/feedback-rate.json';
const TO_FILE    = __DIR__ . '/feedback-to.txt';
const KEY_FILE   = __DIR__ . '/feedback-key.txt';

const MAX_TEXT       = 1200;
const MIN_TEXT       = 8;
const MAX_PER_HOUR   = 6;       /* per IP, so one person cannot flood the inbox */
const RECENT_LIMIT   = 20;

/* ---------------------------------------------------------------- helpers */
function out(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function clean(string $v, int $max): string
{
    $v = str_replace(["\r\n", "\r", "\0"], "\n", $v);
    $v = trim($v);
    return mb_substr($v, 0, $max);
}

function line_from(string $file): string
{
    if (!is_file($file)) {
        return '';
    }
    $raw = @file_get_contents($file);
    if ($raw === false) {
        return '';
    }
    $line = trim((string)strtok($raw, "\n"));
    return $line;
}

function ensure_data_dir(): void
{
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0755, true);
    }
    /* anything inside the web root can be requested: keep the log out of reach */
    if (!is_file(DATA_DIR . '/.htaccess')) {
        @file_put_contents(DATA_DIR . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    if (!is_file(DATA_DIR . '/index.html')) {
        @file_put_contents(DATA_DIR . '/index.html', "<!doctype html><title>.</title>\n");
    }
}

/* one report per IP, a handful per hour, so the inbox stays readable */
function rate_ok(string $ip): bool
{
    $now = time();
    $all = [];
    if (is_file(RATE_FILE)) {
        $raw = @file_get_contents(RATE_FILE);
        $decoded = $raw ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $all = $decoded;
        }
    }
    $hits = array_values(array_filter($all[$ip] ?? [], static fn ($t) => is_int($t) && $t > $now - 3600));
    if (count($hits) >= MAX_PER_HOUR) {
        $all[$ip] = $hits;
        @file_put_contents(RATE_FILE, json_encode($all));
        return false;
    }
    $hits[] = $now;
    $all[$ip] = $hits;
    foreach ($all as $k => $v) {
        if (!is_array($v) || !array_filter($v, static fn ($t) => is_int($t) && $t > $now - 7200)) {
            unset($all[$k]);
        }
    }
    @file_put_contents(RATE_FILE, json_encode($all));
    return true;
}

/* ---------------------------------------------------------------- CORS */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Max-Age: 86400');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

ensure_data_dir();

/* ---------------------------------------------------------------- reading */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $key = line_from(KEY_FILE);

    if (isset($_GET['count'])) {
        $n = 0;
        if (is_file(LOG_FILE)) {
            $n = max(0, substr_count((string)@file_get_contents(LOG_FILE), "\n"));
        }
        out(200, ['ok' => true, 'count' => $n]);
    }

    if (isset($_GET['recent'])) {
        if ($key === '' || !hash_equals($key, (string)$_GET['recent'])) {
            out(403, ['ok' => false, 'error' => 'no']);
        }
        $items = [];
        if (is_file(LOG_FILE)) {
            $lines = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach (array_slice(array_reverse($lines), 0, RECENT_LIMIT) as $line) {
                $row = json_decode($line, true);
                if (is_array($row)) {
                    $items[] = $row;
                }
            }
        }
        out(200, ['ok' => true, 'items' => $items]);
    }

    out(200, ['ok' => true, 'what' => 'feedback inbox', 'how' => 'POST a report']);
}

/* ---------------------------------------------------------------- writing */
$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 24000) {
    out(413, ['ok' => false, 'error' => 'too big']);
}
$in = json_decode((string)$raw, true);
if (!is_array($in) && !empty($_POST)) {
    $in = $_POST;               /* a plain form post is welcome too */
}
if (!is_array($in)) {
    out(400, ['ok' => false, 'error' => 'send json']);
}

$kind = in_array(($in['kind'] ?? ''), ['Bug', 'Idea', 'Praise'], true) ? $in['kind'] : 'Bug';
$text = clean((string)($in['text'] ?? ''), MAX_TEXT);
if (mb_strlen($text) < MIN_TEXT) {
    out(400, ['ok' => false, 'error' => 'a few more words, please']);
}

$name    = clean((string)($in['name'] ?? ''), 40);
$build   = clean((string)($in['build'] ?? ''), 60);
$page    = clean((string)($in['page'] ?? ''), 300);
$room    = clean((string)($in['room'] ?? ''), 60);
$where   = clean((string)($in['where'] ?? ''), 60);
$screen  = clean((string)($in['screen'] ?? ''), 40);
$browser = clean((string)($in['browser'] ?? ''), 180);
$when    = clean((string)($in['when'] ?? ''), 40);

$subject = clean((string)($in['subject'] ?? ''), 120);
if ($subject === '') {
    $first = trim((string)strtok($text, "\n"));
    $subject = '[' . $kind . '] ' . mb_substr($first, 0, 70);
}
$body = clean((string)($in['body'] ?? ''), 4000);
if ($body === '') {
    $body = $text;
}

$ip = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
$ip = trim(explode(',', $ip)[0]);
if (!rate_ok($ip)) {
    out(429, ['ok' => false, 'error' => 'that is plenty for one hour']);
}

$row = [
    'at'      => gmdate('c'),
    'kind'    => $kind,
    'name'    => $name,
    'subject' => $subject,
    'body'    => $body,
    'text'    => $text,
    'build'   => $build,
    'page'    => $page,
    'room'    => $room,
    'where'   => $where,
    'screen'  => $screen,
    'browser' => $browser,
    'when'    => $when,
];

/* the log is the safety net: even with no mail set up, nothing is lost */
@file_put_contents(
    LOG_FILE,
    json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
    FILE_APPEND | LOCK_EX
);

/* and the mail, if an address has been uploaded beside this file */
$to = line_from(TO_FILE);
$mailed = false;
if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'aidekupal.unaux.com');
    $host = preg_replace('/:\d+$/', '', $host) ?: 'aidekupal.unaux.com';
    $headers = [
        'From: KDL feedback <no-reply@' . $host . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: KDL feedback',
    ];
    $mailed = function_exists('mail')
        ? (bool)@mail($to, $subject, $body, implode("\r\n", $headers))
        : false;
}

out(200, ['ok' => true, 'mailed' => $mailed]);
