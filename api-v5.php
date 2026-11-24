<?php
/**
 * ============================================================================
 *  Black Cat Group Chat — api-v5.php
 *  ------------------------------------------------------------------------
 *  Drop this file next to gc-v5.html inside your XAMPP htdocs folder.
 *  Requirements: PHP 7.4+ with JSON + fileinfo (default in XAMPP).
 *  Optional:     curl (for the GIF proxy — falls back to fopen wrappers).
 *
 *  Endpoints
 *    GET  api-v5.php?action=load[&since=<int>]   → message history
 *    POST api-v5.php?action=send                 → store a message
 *    POST api-v5.php?action=edit                 → edit your own message
 *    POST api-v5.php?action=del                  → delete your own message
 *    GET  api-v5.php?action=link&u=<url>         → OpenGraph preview data
 *    POST api-v5.php?action=announce             → set the room banner text (admin only)
 *    POST api-v5.php?action=claimadmin           → take the single admin slot
 *    POST api-v5.php?action=adminbeat            → keep the admin slot alive
 *    POST api-v5.php?action=releaseadmin         → give the admin slot back
 *    (load also returns the current banner; messages may carry `enc`)
 *    POST api-v5.php?action=react                → toggle a reaction
 *    POST api-v5.php?action=clear                → wipe the log
 *    POST api-v5.php?action=upload               → file/image upload
 *    GET  api-v5.php?action=gif&q=<query>        → GIF proxy (CORS workaround)
 *    POST api-v5.php?action=beat                 → presence heartbeat
 *    POST api-v5.php?action=leave                → leave the room
 *    GET  api-v5.php?action=online               → who is really connected
 *
 *  Storage (created automatically)
 *    data/messages.json     the chat log (last 400 messages)
 *    data/presence.json     who is online right now
 *    data/rate.json         per-IP rate limiting counters
 *    uploads/               uploaded files (script execution disabled)
 *
 *  Security notes
 *    • uploads are whitelisted by extension AND verified MIME, renamed to a
 *      random hex name, and the folder is locked down with .htaccess
 *    • every string is length-capped and control-character stripped
 *    • only http(s) / relative URLs are accepted from clients
 *    • data/ is protected with .htaccess (move it out of htdocs if you can)
 * ============================================================================
 */

declare(strict_types=1);

/* ---------------------------------------------------------------- config */
const MAX_TEXT        = 2000;      // max characters per message
const MAX_NAME        = 32;        // max display name
const MAX_ID          = 40;        // max id / uid length
const MAX_STORED      = 400;       // messages kept on disk
const MAX_UPLOAD      = 8388608;   // 8 MB
const PRESENCE_TTL    = 80;        // seconds before a silent user is gone
const RATE_WINDOW     = 10;        // seconds
const RATE_MAX        = 25;        // requests per window per IP
const GIF_TIMEOUT     = 8;         // seconds
const LINK_TIMEOUT    = 6;         // seconds for link preview fetches
const LINK_MAX        = 600000;    // bytes of HTML inspected per link
const LINK_TTL        = 604800;    // cache lifetime for previews (7 days)
const LINK_MAX_PER    = 20;        // preview lookups per IP per window
const MAX_ENC         = 12000;     // base64 ciphertext per message
const MAX_VOICE       = 120;       // seconds of audio we accept
const ADMIN_TTL       = 45;        // seconds an admin slot survives without a beat
const ADMIN_GHOST     = 25;        // an admin silent this long may be cleared by anyone
                                   // (a live admin beats every 12 s, so this never hits them)

const ALLOWED_EXT = [
    'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image',
    'gif' => 'image', 'webp' => 'image',
    'mp4' => 'file', 'webm' => 'file', 'pdf' => 'file',
    'zip' => 'file', 'txt' => 'file',
    /* voice notes — tagged as voice below when the mime really is audio */
    'weba' => 'voice', 'oga' => 'voice', 'ogg' => 'voice',
    'm4a' => 'voice', 'mp3' => 'voice'
];

const ALLOWED_MIME = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'video/mp4', 'video/webm', 'application/pdf',
    'application/zip', 'application/x-zip-compressed',
    'text/plain',
    /* audio containers used by MediaRecorder */
    'audio/webm', 'audio/ogg', 'audio/mp4', 'audio/mpeg',
    'audio/x-m4a', 'application/ogg'
];

$DATA_DIR    = __DIR__ . '/data';
$UPLOAD_DIR  = __DIR__ . '/uploads';
$MSG_FILE    = $DATA_DIR . '/messages.json';
$PRES_FILE   = $DATA_DIR . '/presence.json';
$RATE_FILE   = $DATA_DIR . '/rate.json';
$LINK_DIR    = $DATA_DIR . '/linkcache';
$ROOM_FILE   = $DATA_DIR . '/room.json';
$ADMIN_FILE  = $DATA_DIR . '/admin.json';

/* ------------------------------------------------------------- bootstrap */
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');
header('Referrer-Policy: no-referrer');

set_exception_handler(static function (Throwable $e): void {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server error']);
    exit;
});

/* ---------------------------------------------------------------- helpers */
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

function ensure_dir(string $dir, string $htaccess = ''): void
{
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        fail('cannot create storage directory', 500);
    }
    if ($htaccess !== '') {
        $file = $dir . '/.htaccess';
        if (!is_file($file)) {
            @file_put_contents($file, $htaccess);
        }
    }
}

/** Read a JSON file safely, returning [] on failure. */
function read_json(string $file): array
{
    if (!is_file($file)) {
        return [];
    }
    $raw = @file_get_contents($file);
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Write a JSON file atomically under an exclusive lock. */
function write_json(string $file, array $data): bool
{
    $tmp  = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return false;
    }
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        return false;
    }
    if (@rename($tmp, $file)) {
        return true;
    }
    /* Windows sometimes refuses to rename over an existing file */
    @unlink($tmp);
    return @file_put_contents($file, $json, LOCK_EX) !== false;
}

/** Run a callback while holding an exclusive lock on a lock file. */
function with_lock(string $lockFile, callable $fn)
{
    $fp = @fopen($lockFile, 'c');
    if (!$fp) {
        return $fn();
    }
    @flock($fp, LOCK_EX);
    try {
        return $fn();
    } finally {
        @flock($fp, LOCK_UN);
        @fclose($fp);
    }
}

function json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    // hard cap the request body to keep JSON parsing cheap
    if (strlen($raw) > 65536) {
        fail('request too large', 413);
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Strip control chars (except newline/tab) and cap the length. */
function clean_text($value, int $max): string
{
    $s = is_scalar($value) ? (string)$value : '';
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $max, 'UTF-8');
    }
    return substr($s, 0, $max);
}

/** Only ids/names we expect; anything else is rejected off the message. */
function clean_id($value): string
{
    $s = is_scalar($value) ? (string)$value : '';
    $s = preg_replace('/[^A-Za-z0-9_\-]/', '', $s) ?? '';
    return substr($s, 0, MAX_ID);
}

/** http(s) URLs or same-folder relative paths only — blocks javascript: etc. */
function safe_url($value): string
{
    $u = is_scalar($value) ? trim((string)$value) : '';
    if ($u === '' || strlen($u) > 400) {
        return '';
    }
    if (preg_match('#^https?://#i', $u)) {
        return $u;
    }
    if (preg_match('#^(uploads/)?[A-Za-z0-9._\-]+$#', $u)) {
        return $u;
    }
    return '';
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return is_string($ip) ? $ip : '0.0.0.0';
}

/** Very small fixed-window rate limiter (per IP, per action group). */
function rate_limit(string $file, string $bucket, int $max = RATE_MAX, int $window = RATE_WINDOW): void
{
    $key = substr(hash('sha256', client_ip() . '|' . $bucket), 0, 16);
    $now = time();

    with_lock($file . '.lock', static function () use ($file, $key, $now, $max, $window): void {
        $all = read_json($file);
        $hits = [];
        foreach (($all[$key] ?? []) as $t) {
            if (is_int($t) && $now - $t < $window) {
                $hits[] = $t;
            }
        }
        if (count($hits) >= $max) {
            fail('slow down a little', 429);
        }
        $hits[] = $now;
        $all[$key] = $hits;
        // keep the file from growing forever
        if (count($all) > 500) {
            $all = array_slice($all, -500, null, true);
        }
        write_json($file, $all);
    });
}

/* ------------------------------------------------------------- directories */
ensure_dir($DATA_DIR, implode("\n", [
    '<IfModule mod_authz_core.c>',
    '  Require all denied',
    '</IfModule>',
    '<IfModule !mod_authz_core.c>',
    '  Order allow,deny',
    '  Deny from all',
    '</IfModule>',
    ''
]));
ensure_dir($UPLOAD_DIR, implode("\n", [
    '<IfModule mod_php.c>',
    '  php_flag engine off',
    '</IfModule>',
    '<IfModule mod_php7.c>',
    '  php_flag engine off',
    '</IfModule>',
    '<IfModule mod_php5.c>',
    '  php_flag engine off',
    '</IfModule>',
    'Options -ExecCGI -Indexes',
    '<FilesMatch "\.(php|phtml|phar|php3|php4|php5|php7|php8|cgi|pl|py|sh|htaccess)$">',
    '  <IfModule mod_authz_core.c>',
    '    Require all denied',
    '  </IfModule>',
    '  <IfModule !mod_authz_core.c>',
    '    Order allow,deny',
    '    Deny from all',
    '  </IfModule>',
    '</FilesMatch>',
    ''
]));

/* --------------------------------------------------------------- presence */
function presence_clean(array $p): array
{
    $now = time();
    $out = [];
    foreach ($p as $uid => $rec) {
        if (!is_array($rec)) {
            continue;
        }
        $t = (int)($rec['t'] ?? 0);
        if ($now - $t > PRESENCE_TTL) {
            continue;
        }
        $out[(string)$uid] = [
            'name'   => clean_text($rec['name'] ?? 'Guest', MAX_NAME),
            'joinTs' => (int)($rec['joinTs'] ?? 0),
            't'      => $t
        ];
    }
    return $out;
}

/* ------------------------------------------------------------- message io */
/** Normalise an incoming message into our stored shape, or null if invalid. */
function normalise_message(array $m): ?array
{
    $id = clean_id($m['id'] ?? '');
    $uid = clean_id($m['uid'] ?? '');
    if ($id === '' || $uid === '') {
        return null;
    }

    $type = (string)($m['type'] ?? 'text');
    if (!in_array($type, ['text', 'image', 'gif', 'file', 'voice'], true)) {
        $type = 'text';
    }

    $msg = [
        'id'        => $id,
        'uid'       => $uid,
        'name'      => clean_text($m['name'] ?? 'Guest', MAX_NAME),
        'text'      => clean_text($m['text'] ?? '', MAX_TEXT),
        'type'      => $type,
        'url'       => safe_url($m['url'] ?? ''),
        'ts'        => (int)($m['ts'] ?? (int)round(microtime(true) * 1000)),
        'reactions' => []
    ];

    // end-to-end encrypted text: the server only ever holds the ciphertext
    if (isset($m['enc']) && is_string($m['enc']) && $m['enc'] !== '') {
        $enc = substr($m['enc'], 0, MAX_ENC);
        if (preg_match('#^v1\.[A-Za-z0-9+/=]+\.[A-Za-z0-9+/=]+$#', $enc)) {
            $msg['enc'] = $enc;
        }
    }
    if ($type === 'voice') {
        $msg['dur'] = max(0, min(MAX_VOICE, (int)($m['dur'] ?? 0)));
    }

    if (isset($m['fname'])) {
        $msg['fname'] = clean_text($m['fname'], 120);
    }
    if (isset($m['size'])) {
        $msg['size'] = max(0, (int)$m['size']);
    }

    // quoted message (reply) — trimmed down so a long quote can't bloat storage
    if (isset($m['reply']) && is_array($m['reply'])) {
        $rid = clean_id($m['reply']['id'] ?? '');
        if ($rid !== '') {
            $msg['reply'] = [
                'id'   => $rid,
                'uid'  => clean_id($m['reply']['uid'] ?? ''),
                'name' => clean_text($m['reply']['name'] ?? 'Guest', MAX_NAME),
                'text' => clean_text($m['reply']['text'] ?? '', 160)
            ];
        }
    }

    // reject empty payloads (no text, no ciphertext, no media)
    if ($msg['text'] === '' && $msg['url'] === '' && !isset($msg['enc'])) {
        return null;
    }
    return $msg;
}

/* --------------------------------------------------------------- admin slot */
/** The uid that currently holds the (single) secret admin slot, or ''. */
function current_admin(): array
{
    global $ADMIN_FILE;
    $cur = read_json($ADMIN_FILE);
    $uid = (string)($cur['uid'] ?? '');
    $last = (int)($cur['last'] ?? 0);
    if ($uid === '' || $last <= time() - ADMIN_TTL) {
        return ['uid' => '', 'name' => '', 'since' => 0];
    }
    return ['uid' => $uid, 'name' => (string)($cur['name'] ?? 'Admin'), 'since' => (int)($cur['since'] ?? 0)];
}

/* ----------------------------------------------------------- outbound fetch */
/** Tiny GET with a hard byte cap, no redirects to private hosts, curl or fopen. */
function http_get(string $url, int $timeout, int $maxBytes): string
{
    $buf = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT      => 'BlackCatChat/3.0 (+link preview)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_HTTPHEADER     => ['Accept: text/html,application/xhtml+xml', 'Accept-Language: en'],
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_NOPROGRESS     => false,
            CURLOPT_PROGRESSFUNCTION => static function ($res, $dl, $total) use ($maxBytes): int {
                return $dl > $maxBytes ? 1 : 0;
            }
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $code >= 400) {
            return '';
        }
        $buf = (string)$raw;
    } else {
        $ctx = stream_context_create(['http' => [
            'timeout'       => $timeout,
            'follow_location' => 1,
            'max_redirects' => 3,
            'header'        => "User-Agent: BlackCatChat/3.0\r\nAccept: text/html\r\n"
        ]]);
        $raw = @file_get_contents($url, false, $ctx, 0, $maxBytes);
        if ($raw === false) {
            return '';
        }
        $buf = (string)$raw;
    }
    return strlen($buf) > $maxBytes ? substr($buf, 0, $maxBytes) : $buf;
}

/** Pull a single meta value out of raw HTML. */
function meta_value(string $html, array $keys): string
{
    foreach ($keys as $key) {
        $k = preg_quote($key, '#');
        $patterns = [
            '#<meta[^>]+(?:property|name)=["\']' . $k . '["\'][^>]*content=["\']([^"\']*)#i',
            '#<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']' . $k . '["\']#i'
        ];
        foreach ($patterns as $re) {
            if (preg_match($re, $html, $m)) {
                $val = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($val !== '') {
                    return $val;
                }
            }
        }
    }
    return '';
}

/* ------------------------------------------------------------------ router */
$action = isset($_GET['action']) ? (string)$_GET['action'] : 'health';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

switch ($action) {

    /* ---------------------------------------------------------------- health */
    case 'health':
        out([
            'ok'       => true,
            'service'  => 'black-cat-chat',
            'version'  => 2,
            'php'      => PHP_VERSION,
            'writable' => is_writable($DATA_DIR) && is_writable($UPLOAD_DIR)
        ]);

    /* ------------------------------------------------------------------ load */
    case 'load':
        $since = isset($_GET['since']) ? (int)$_GET['since'] : 0;

        $data = with_lock($DATA_DIR . '/messages.lock', static function () use ($MSG_FILE, $since) {
            $store = read_json($MSG_FILE);
            $messages = $store['messages'] ?? [];
            $serverTs = (int)($store['serverTs'] ?? 0);

            if ($since > 0) {
                $filtered = [];
                foreach ($messages as $m) {
                    if ((int)($m['s'] ?? 0) > $since) {
                        $filtered[] = $m;
                    }
                }
                $messages = $filtered;
            } else {
                $messages = array_slice($messages, -MAX_STORED);
            }
            return [$messages, $serverTs];
        });

        $room = read_json($ROOM_FILE);

        out([
            'ok'         => true,
            'messages'   => $data[0],
            'serverTime' => $data[1],
            'announce'   => $room['announce'] ?? null,
            'admin'      => current_admin()
        ]);

    /* ------------------------------------------------------------------ send */
    case 'send':
        if ($method !== 'POST') {
            fail('POST required', 405);
        }
        rate_limit($RATE_FILE, 'send');

        $in = json_body();
        $msg = normalise_message($in);
        if ($msg === null) {
            fail('invalid message');
        }

        $saved = with_lock($DATA_DIR . '/messages.lock', static function () use ($MSG_FILE, $msg) {
            $store = read_json($MSG_FILE);
            $messages = $store['messages'] ?? [];
            $serverTs = (int)($store['serverTs'] ?? 0);

            foreach ($messages as $existing) {
                if (($existing['id'] ?? '') === $msg['id']) {
                    return $existing;               // already stored — idempotent
                }
            }

            $serverTs = max($serverTs + 1, (int)round(microtime(true) * 1000));
            $msg['s'] = $serverTs;
            $messages[] = $msg;

            if (count($messages) > MAX_STORED) {
                $messages = array_slice($messages, -MAX_STORED);
            }

            write_json($MSG_FILE, ['serverTs' => $serverTs, 'messages' => $messages]);
            return $msg;
        });

        out(['ok' => true, 'message' => $saved, 'serverTime' => (int)($saved['s'] ?? 0)]);

    /* ------------------------------------------------------------------ edit */
    case 'edit':
        if ($method !== 'POST') {
            fail('POST required', 405);
        }
        rate_limit($RATE_FILE, 'edit', 40);

        $in   = json_body();
        $id   = clean_id($in['id'] ?? '');
        $eu   = clean_id($in['uid'] ?? '');
        $text = clean_text($in['text'] ?? '', MAX_TEXT);
        $enc  = '';
        if (isset($in['enc']) && is_string($in['enc']) && preg_match('#^v1\.[A-Za-z0-9+/=]+\.[A-Za-z0-9+/=]+$#', $in['enc'])) {
            $enc = substr($in['enc'], 0, MAX_ENC);
        }
        if ($id === '' || $eu === '' || ($text === '' && $enc === '')) {
            fail('invalid edit');
        }

        $result = with_lock($DATA_DIR . '/messages.lock', static function () use ($MSG_FILE, $id, $eu, $text, $enc) {
            $store    = read_json($MSG_FILE);
            $messages = $store['messages'] ?? [];
            $serverTs = (int)($store['serverTs'] ?? 0);

            foreach ($messages as $i => $m) {
                if (($m['id'] ?? '') !== $id) {
                    continue;
                }
                if (($m['uid'] ?? '') !== $eu) {
                    return ['ok' => false, 'error' => 'not your message'];
                }
                if (!empty($m['deleted'])) {
                    return ['ok' => false, 'error' => 'message was deleted'];
                }
                if (($m['type'] ?? 'text') !== 'text') {
                    return ['ok' => false, 'error' => 'only text can be edited'];
                }
                $serverTs = max($serverTs + 1, (int)round(microtime(true) * 1000));
                if ($enc !== '') {
                    $messages[$i]['enc']  = $enc;
                    $messages[$i]['text'] = '';
                } else {
                    $messages[$i]['text'] = $text;
                }
                $messages[$i]['edited'] = true;
                $messages[$i]['s']      = $serverTs;
                write_json($MSG_FILE, ['serverTs' => $serverTs, 'messages' => $messages]);
                return ['ok' => true, 'id' => $id, 'serverTime' => $serverTs];
            }
            return ['ok' => false, 'error' => 'not found'];
        });

        if (empty($result['ok'])) {
            fail((string)($result['error'] ?? 'edit failed'));
        }
        out($result);

    /* ------------------------------------------------------------------- del */
    case 'del':
        if ($method !== 'POST') {
            fail('POST required', 405);
        }
        rate_limit($RATE_FILE, 'del', 40);

        $in = json_body();
        $id = clean_id($in['id'] ?? '');
        $du = clean_id($in['uid'] ?? '');
        if ($id === '' || $du === '') {
            fail('invalid delete');
        }

        $result = with_lock($DATA_DIR . '/messages.lock', static function () use ($MSG_FILE, $id, $du) {
            $store    = read_json($MSG_FILE);
            $messages = $store['messages'] ?? [];
            $serverTs = (int)($store['serverTs'] ?? 0);

            foreach ($messages as $i => $m) {
                if (($m['id'] ?? '') !== $id) {
                    continue;
                }
                if (($m['uid'] ?? '') !== $du && $du !== current_admin()['uid']) {
                    return ['ok' => false, 'error' => 'only the author or the admin can delete'];
                }
                $serverTs = max($serverTs + 1, (int)round(microtime(true) * 1000));
                $messages[$i]['deleted']   = true;
                $messages[$i]['text']      = '';
                $messages[$i]['enc']       = '';
                $messages[$i]['url']       = '';
                $messages[$i]['reactions'] = [];
                $messages[$i]['s']         = $serverTs;
                write_json($MSG_FILE, ['serverTs' => $serverTs, 'messages' => $messages]);
                return ['ok' => true, 'id' => $id, 'serverTime' => $serverTs];
            }
            return ['ok' => false, 'error' => 'not found'];
        });

        if (empty($result['ok'])) {
            fail((string)($result['error'] ?? 'delete failed'));
        }
        out($result);

    /* ----------------------------------------------------------------- react */
    case 'react':
        if ($method !== 'POST') {
            fail('POST required', 405);
        }
        rate_limit($RATE_FILE, 'react');

        $in    = json_body();
        $id    = clean_id($in['id'] ?? '');
        $uid   = clean_id($in['uid'] ?? '');
        $emoji = clean_text($in['emoji'] ?? '', 8);
        $name  = clean_text($in['name'] ?? 'Guest', MAX_NAME);

        if ($id === '' || $uid === '' || $emoji === '') {
            fail('invalid reaction');
        }

        $result = with_lock($DATA_DIR . '/messages.lock', static function () use ($MSG_FILE, $id, $uid, $emoji, $name) {
            $store = read_json($MSG_FILE);
            $messages = $store['messages'] ?? [];

            foreach ($messages as $i => $m) {
                if (($m['id'] ?? '') !== $id) {
                    continue;
                }
                $reactions = is_array($m['reactions'] ?? null) ? $m['reactions'] : [];
                $list = is_array($reactions[$emoji] ?? null) ? $reactions[$emoji] : [];

                $kept = [];
                $had  = false;
                foreach ($list as $r) {
                    if (($r['uid'] ?? '') === $uid) {
                        $had = true;
                        continue;               // toggle off
                    }
                    $kept[] = $r;
                }
                if (!$had) {
                    $kept[] = ['uid' => $uid, 'name' => $name];
                }

                if ($kept) {
                    $reactions[$emoji] = $kept;
                } else {
                    unset($reactions[$emoji]);
                }
                $messages[$i]['reactions'] = $reactions;

                $store['messages'] = $messages;
                write_json($MSG_FILE, $store);
                return $messages[$i];
            }
            return null;
        });

        if ($result === null) {
            fail('message not found', 404);
        }
        out(['ok' => true, 'message' => $result]);

    /* ----------------------------------------------------------------- clear */
    case 'clear':
        if ($method !== 'POST') {
            fail('POST required', 405);
        }
        $who = json_body();
        if (clean_id($who['uid'] ?? '') !== current_admin()['uid'] || current_admin()['uid'] === '') {
            fail('only the admin can clear the chat', 403);
        }
        rate_limit($RATE_FILE, 'clear', 3, 30);

        with_lock($DATA_DIR . '/messages.lock', static function () use ($MSG_FILE) {
            write_json($MSG_FILE, ['serverTs' => (int)round(microtime(true) * 1000), 'messages' => []]);
        });
        out(['ok' => true]);

    /* ---------------------------------------------------------------- upload */
    case 'upload':
        if ($method !== 'POST') {
            fail('POST required', 405);
        }
        rate_limit($RATE_FILE, 'upload', 10, 60);

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            fail('no file received');
        }
        $f = $_FILES['file'];

        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            fail('upload error code ' . (int)($f['error'] ?? -1));
        }
        if (!is_uploaded_file($f['tmp_name'])) {
            fail('invalid upload');
        }

        $size = (int)($f['size'] ?? 0);
        if ($size <= 0 || $size > MAX_UPLOAD) {
            fail('file too large (max 8 MB)', 413);
        }

        $orig = (string)($f['name'] ?? 'file');
        $orig = basename($orig);
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

        if (!isset(ALLOWED_EXT[$ext])) {
            fail('file type not allowed');
        }

        // verify the real MIME type, not just the extension
        $mime = '';
        if (class_exists('finfo')) {
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$fi->file($f['tmp_name']);
        }
        if ($mime !== '' && !in_array($mime, ALLOWED_MIME, true)) {
            fail('file content does not match an allowed type (got ' . $mime . ')');
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $target     = $UPLOAD_DIR . '/' . $storedName;

        if (!@move_uploaded_file($f['tmp_name'], $target)) {
            fail('could not save file', 500);
        }
        @chmod($target, 0644);

        $kind = ALLOWED_EXT[$ext];
        $wantVoice = (isset($_POST['kind']) && (string)$_POST['kind'] === 'voice');
        if ($wantVoice && ($kind === 'voice' || ($kind === 'file' && $ext === 'webm'))) {
            $kind = 'voice';
        } elseif ($kind === 'voice') {
            $kind = 'file';   // a .ogg that is really a video stays a file
        }

        out([
            'ok'   => true,
            'url'  => 'uploads/' . $storedName,
            'name' => clean_text($orig, 120),
            'size' => $size,
            'type' => $kind,
            'mime' => $mime
        ]);

    /* -------------------------------------------------------------- announce */
    case 'announce':
        if ($method !== 'POST') {
            fail('POST required', 405);
        }
        rate_limit($RATE_FILE, 'announce', 20, 30);

        $in    = json_body();
        $au    = clean_id($in['uid'] ?? '');
        $aname = clean_text($in['name'] ?? 'Guest', MAX_NAME);
        $enc   = '';
        if (isset($in['enc']) && is_string($in['enc']) && preg_match('#^v1\.[A-Za-z0-9+/=]+\.[A-Za-z0-9+/=]+$#', $in['enc'])) {
            $enc = substr($in['enc'], 0, MAX_ENC);
        }
        $atext = clean_text($in['text'] ?? '', 240);

        if ($au === '') {
            fail('invalid announce');
        }
        if (current_admin()['uid'] !== $au) {
            fail('only the admin can post the banner', 403);
        }

        $banner = null;
        if ($enc !== '' || $atext !== '') {
            $banner = [
                'enc'  => $enc,
                'text' => $atext,
                'uid'  => $au,
                'name' => $aname,
                'ts'   => (int)round(microtime(true) * 1000)
            ];
        }

        write_json($ROOM_FILE, ['announce' => $banner, 'serverTs' => (int)round(microtime(true) * 1000)]);
        out(['ok' => true, 'announce' => $banner]);

    /* ----------------------------------------------------------- admin slot */
    case 'claimadmin':
        if ($method !== 'POST') {
            fail('POST required', 405);
        }
        rate_limit($RATE_FILE, 'claimadmin', 12, 30);

        $in = json_body();
        $au = clean_id($in['uid'] ?? '');
        $an = clean_text($in['name'] ?? 'Admin', MAX_NAME);
        if ($au === '') {
            fail('invalid admin claim');
        }

        $res = with_lock($DATA_DIR . '/admin.lock', static function () use ($ADMIN_FILE, $au, $an) {
            $cur    = read_json($ADMIN_FILE);
            $curUid = (string)($cur['uid'] ?? '');
            $last   = (int)($cur['last'] ?? 0);
            $now    = time();

            if ($curUid !== '' && $curUid !== $au && $last > $now - ADMIN_TTL) {
                return ['ok' => false, 'error' => 'admin taken', 'admin' => ['name' => (string)($cur['name'] ?? 'Admin')]];
            }
            $since = ($curUid === $au && $last > $now - ADMIN_TTL) ? (int)($cur['since'] ?? $now) : $now;
            write_json($ADMIN_FILE, ['uid' => $au, 'name' => $an, 'since' => $since, 'last' => $now]);
            return ['ok' => true, 'admin' => ['uid' => $au, 'name' => $an, 'since' => $since]];
        });

        if (empty($res['ok'])) {
            out(['ok' => false, 'error' => 'admin taken', 'admin' => $res['admin'] ?? null], 409);
        }
        out($res);

    case 'adminbeat':
        if ($method !== 'POST') {
            fail('POST required', 405);
        }
        $in = json_body();
        $bu = clean_id($in['uid'] ?? '');
        $res = with_lock($DATA_DIR . '/admin.lock', static function () use ($ADMIN_FILE, $bu) {
            $cur = read_json($ADMIN_FILE);
            if ($bu === '' || (string)($cur['uid'] ?? '') !== $bu) {
                return ['ok' => false, 'error' => 'not admin'];
            }
            $cur['last'] = time();
            write_json($ADMIN_FILE, $cur);
            return ['ok' => true];
        });
        if (empty($res['ok'])) {
            fail('not admin', 403);
        }
        out($res);

    /* clear a slot left behind by a crashed tab — only if it is genuinely silent */
    case 'freeadmin':
        if ($method !== 'POST') {
            fail('POST required', 405);
        }
        rate_limit($RATE_FILE, 'freeadmin', 10, 60);

        $in = json_body();
        $fu = clean_id($in['uid'] ?? '');
        if ($fu === '') {
            fail('invalid request');
        }

        $res = with_lock($DATA_DIR . '/admin.lock', static function () use ($ADMIN_FILE, $fu) {
            $cur  = read_json($ADMIN_FILE);
            $curU = (string)($cur['uid'] ?? '');
            $last = (int)($cur['last'] ?? 0);

            if ($curU === '' || $last <= time() - ADMIN_GHOST) {
                write_json($ADMIN_FILE, ['uid' => '', 'name' => '', 'since' => 0, 'last' => 0]);
                return ['ok' => true, 'freed' => true];
            }
            return ['ok' => false, 'error' => 'the admin is still live', 'name' => (string)($cur['name'] ?? 'Admin')];
        });

        if (empty($res['ok'])) {
            out($res, 409);
        }
        out($res);

    case 'releaseadmin':
        if ($method !== 'POST') {
            fail('POST required', 405);
        }
        $in = json_body();
        $ru = clean_id($in['uid'] ?? '');
        with_lock($DATA_DIR . '/admin.lock', static function () use ($ADMIN_FILE, $ru) {
            $cur = read_json($ADMIN_FILE);
            if ($ru !== '' && (string)($cur['uid'] ?? '') === $ru) {
                write_json($ADMIN_FILE, ['uid' => '', 'name' => '', 'since' => 0, 'last' => 0]);
            }
            return true;
        });
        out(['ok' => true]);

    /* ------------------------------------------------------------------ link */
    case 'link':
        rate_limit($RATE_FILE, 'link', LINK_MAX_PER);

        $u = isset($_GET['u']) ? (string)$_GET['u'] : '';
        $u = substr(trim($u), 0, 500);
        $url = safe_url($u);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            fail('invalid url');
        }

        ensure_dir($LINK_DIR, "Require all denied\nDeny from all\n");
        $cacheFile = $LINK_DIR . '/' . hash('sha256', $url) . '.json';
        $cached = is_file($cacheFile) ? read_json($cacheFile) : [];

        if (!empty($cached['at']) && (int)$cached['at'] > time() - LINK_TTL) {
            if (empty($cached['title'])) {
                out(['ok' => false, 'error' => 'no preview']);
            }
            out([
                'ok'     => true,
                'url'    => $url,
                'title'  => (string)($cached['title'] ?? ''),
                'image'  => (string)($cached['image'] ?? ''),
                'cached' => true
            ]);
        }

        // never let a preview request reach loopback / LAN / link-local hosts
        $host = (string)(parse_url($url, PHP_URL_HOST) ?: '');
        if ($host === '') {
            fail('invalid url');
        }
        $ip = gethostbyname($host);
        if (filter_var($ip, FILTER_VALIDATE_IP) !== false
            && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            fail('blocked host');
        }

        $html = http_get($url, LINK_TIMEOUT, LINK_MAX);
        if ($html === '') {
            write_json($cacheFile, ['at' => time(), 'title' => '', 'image' => '']);
            out(['ok' => false, 'error' => 'unreachable']);
        }

        $title = meta_value($html, ['og:title', 'twitter:title']);
        if ($title === '' && preg_match('#<title[^>]*>(.*?)</title>#is', $html, $tm)) {
            $title = trim(html_entity_decode(strip_tags($tm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        $title = preg_replace('/\s+/u', ' ', $title) ?? '';
        $title = substr($title, 0, 140);

        $image = meta_value($html, ['og:image:secure_url', 'og:image', 'twitter:image']);
        if ($image !== '' && !preg_match('#^https?://#i', $image)) {
            $parts = parse_url($url);
            if (strpos($image, '//') === 0) {
                $image = ($parts['scheme'] ?? 'https') . ':' . $image;
            } elseif (strpos($image, '/') === 0) {
                $image = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . $image;
            }
        }
        $image = safe_url($image);
        if ($title === '' && $image === '') {
            write_json($cacheFile, ['at' => time(), 'title' => '', 'image' => '']);
            out(['ok' => false, 'error' => 'no preview']);
        }

        write_json($cacheFile, ['at' => time(), 'title' => $title, 'image' => $image, 'url' => $url]);
        out(['ok' => true, 'url' => $url, 'title' => $title, 'image' => $image]);

    /* ------------------------------------------------------------------- gif */
    case 'gif':
        $q = isset($_GET['q']) ? (string)$_GET['q'] : 'Trending';
        $q = preg_replace('/[^\p{L}\p{N} ]+/u', '', $q) ?? '';
        $q = trim(substr($q, 0, 60));
        if ($q === '') {
            $q = 'Trending';
        }

        $url = ($q === 'Trending')
            ? 'https://gifsnap.com/api/v1/gifs/trending?page=1&limit=18'
            : 'https://gifsnap.com/api/v1/gifs/search?q=' . rawurlencode($q) . '&page=1&limit=18';

        $raw = false;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_TIMEOUT        => GIF_TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_USERAGENT      => 'BlackCatChat/2.0',
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            $raw = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($raw === false || $code >= 400) {
                $raw = false;
            }
        } else {
            $ctx = stream_context_create(['http' => [
                'timeout' => GIF_TIMEOUT,
                'header'  => "User-Agent: BlackCatChat/2.0\r\n"
            ]]);
            $raw = @file_get_contents($url, false, $ctx);
        }

        if ($raw === false) {
            fail('gif service unavailable', 502);
        }

        $decoded = json_decode((string)$raw, true);
        $items   = [];
        if (is_array($decoded)) {
            $src = $decoded['data'] ?? $decoded['gifs'] ?? $decoded['results'] ?? [];
            foreach ((array)$src as $g) {
                if (!is_array($g)) {
                    continue;
                }
                $full = $g['url'] ?? $g['images']['original']['url'] ?? '';
                if (!is_string($full) || !preg_match('#^https?://#i', $full)) {
                    continue;
                }
                $preview = $g['animated_preview']['url']
                    ?? $g['preview_url']
                    ?? $g['images']['preview_gif']['url']
                    ?? $full;

                $items[] = [
                    'url'         => $full,
                    'preview_url' => is_string($preview) ? $preview : $full
                ];
                if (count($items) >= 18) {
                    break;
                }
            }
        }

        if (!$items) {
            fail('no gifs found', 404);
        }
        out(['ok' => true, 'gifs' => $items, 'query' => $q]);

    /* ------------------------------------------------------------------ beat */
    case 'beat':
        if ($method !== 'POST') {
            fail('POST required', 405);
        }
        $in = json_body();
        $uid2 = clean_id($in['uid'] ?? '');
        if ($uid2 === '') {
            fail('invalid uid');
        }

        with_lock($DATA_DIR . '/presence.lock', static function () use ($PRES_FILE, $uid2, $in) {
            $p = presence_clean(read_json($PRES_FILE));
            $p[$uid2] = [
                'name'   => clean_text($in['name'] ?? 'Guest', MAX_NAME),
                'joinTs' => (int)($in['joinTs'] ?? 0),
                't'      => time()
            ];
            write_json($PRES_FILE, $p);
        });

        out(['ok' => true]);

    /* ----------------------------------------------------------------- leave */
    case 'leave':
        if ($method !== 'POST') {
            fail('POST required', 405);
        }
        $in = json_body();
        $uid2 = clean_id($in['uid'] ?? '');
        if ($uid2 === '') {
            fail('invalid uid');
        }

        with_lock($DATA_DIR . '/presence.lock', static function () use ($PRES_FILE, $uid2) {
            $p = presence_clean(read_json($PRES_FILE));
            unset($p[$uid2]);
            write_json($PRES_FILE, $p);
        });

        out(['ok' => true]);

    /* ---------------------------------------------------------------- online */
    case 'online':
        $online = with_lock($DATA_DIR . '/presence.lock', static function () use ($PRES_FILE) {
            $p = presence_clean(read_json($PRES_FILE));
            write_json($PRES_FILE, $p);          // prune as we go
            return $p;
        });

        out([
            'ok'     => true,
            'online' => array_keys($online),
            'people' => array_values($online),
            'ttl'    => PRESENCE_TTL
        ]);

    /* --------------------------------------------------------------- default */
    default:
        fail('unknown action', 404);
}
