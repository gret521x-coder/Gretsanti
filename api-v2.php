<?php
/**
 * ============================================================================
 *  Black Cat Group Chat — api-v2.php
 *  ------------------------------------------------------------------------
 *  Drop this file next to gc-v2.html inside your XAMPP htdocs folder.
 *  Requirements: PHP 7.4+ with JSON + fileinfo (default in XAMPP).
 *  Optional:     curl (for the GIF proxy — falls back to fopen wrappers).
 *
 *  Endpoints
 *    GET  api-v2.php?action=load[&since=<int>]   → message history
 *    POST api-v2.php?action=send                 → store a message
 *    POST api-v2.php?action=react                → toggle a reaction
 *    POST api-v2.php?action=clear                → wipe the log
 *    POST api-v2.php?action=upload               → file/image upload
 *    GET  api-v2.php?action=gif&q=<query>        → GIF proxy (CORS workaround)
 *    POST api-v2.php?action=beat                 → presence heartbeat
 *    POST api-v2.php?action=leave                → leave the room
 *    GET  api-v2.php?action=online               → who is really connected
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

const ALLOWED_EXT = [
    'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image',
    'gif' => 'image', 'webp' => 'image',
    'mp4' => 'file', 'webm' => 'file', 'pdf' => 'file',
    'zip' => 'file', 'txt' => 'file'
];

const ALLOWED_MIME = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'video/mp4', 'video/webm', 'application/pdf',
    'application/zip', 'application/x-zip-compressed',
    'text/plain'
];

$DATA_DIR    = __DIR__ . '/data';
$UPLOAD_DIR  = __DIR__ . '/uploads';
$MSG_FILE    = $DATA_DIR . '/messages.json';
$PRES_FILE   = $DATA_DIR . '/presence.json';
$RATE_FILE   = $DATA_DIR . '/rate.json';

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
    if (!in_array($type, ['text', 'image', 'gif', 'file'], true)) {
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

    if (isset($m['fname'])) {
        $msg['fname'] = clean_text($m['fname'], 120);
    }
    if (isset($m['size'])) {
        $msg['size'] = max(0, (int)$m['size']);
    }

    // reject empty payloads (no text and no media)
    if ($msg['text'] === '' && $msg['url'] === '') {
        return null;
    }
    return $msg;
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

        out([
            'ok'         => true,
            'messages'   => $data[0],
            'serverTime' => $data[1]
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

        out([
            'ok'   => true,
            'url'  => 'uploads/' . $storedName,
            'name' => clean_text($orig, 120),
            'size' => $size,
            'type' => $kind,
            'mime' => $mime
        ]);

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
