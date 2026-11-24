<?php
/**
 * api-free.php — the only place the FreeModel key exists.
 *
 * Upload this to your PHP host and open its address in a browser **once**. A small form
 * asks for your fm_live_… key and saves it on the server. That is the whole setup — no
 * code editing, no FTP, no turning on anything.
 *
 *   FreeModel endpoint  : https://freemodel.in/v1  (OpenAI-compatible)
 *   Aurora → this file  : POST  (chat, streamed)
 *   ?models=1           : GET   (the catalogue, so "list models" works)
 *   open it in a browser: the one-time key form
 *
 * Where the key goes: a file called .fmkey.php sitting next to this one. It starts with
 * `<?php exit; ?>`, so anyone who guesses its address gets a blank page — the web server
 * runs it instead of printing it.
 *
 * Prefer editing? Put the key in FREEMODEL_KEY below instead; that wins over the form.
 * Nothing is logged and no key is ever echoed back.
 */

declare(strict_types=1);

/* ------------------------------------------------------------------ configure */
const FREEMODEL_KEY   = 'PASTE-YOUR-fm_live_KEY-HERE';   /* optional — the form is easier */
const FREEMODEL_BASE  = 'https://freemodel.in/v1';
const DEFAULT_MODEL   = 'deepseek-v4.1-flash';
const KEY_FILE        = __DIR__ . '/.fmkey.php';
const PLACEHOLDER     = 'PASTE-YOUR-fm_live_KEY-HERE';

/* Browsers only honour a CORS reply from an origin you list here. Add or remove as needed. */
const ALLOWED_ORIGINS = [
    'https://gret521x-coder.github.io',
    'https://aidekupal.unaux.com',      /* also the host of this file, so a copy of the page here just works */
    'http://localhost',
    'http://127.0.0.1',
];

/* Same origin never needs asking: if the page is served from this very host, it is allowed. */
function is_self_origin(string $origin): bool
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($origin === '' || $host === '') {
        return false;
    }
    return $origin === 'https://' . $host || $origin === 'http://' . $host;
}

/* ------------------------------------------------------------------- plumbing */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && (is_self_origin($origin) || in_array($origin, ALLOWED_ORIGINS, true) || preg_match('#^https?://localhost(:\d+)?$#', $origin))) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Title, Authorization');
header('Access-Control-Max-Age: 86400');
header('X-Content-Type-Options: nosniff');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/* ----------------------------------------------------------------------- key */
function saved_key(): string
{
    if (!is_file(KEY_FILE)) {
        return '';
    }
    $raw = (string) @file_get_contents(KEY_FILE);
    $raw = preg_replace('#^\s*<\?php\s*exit;?\s*\?>#i', '', $raw);
    return trim((string) $raw);
}

function active_key(): string
{
    $edited = trim(FREEMODEL_KEY);
    if ($edited !== '' && $edited !== PLACEHOLDER) {
        return $edited;                 /* the line in the file wins when it is filled in */
    }
    return saved_key();                 /* otherwise the one-time form */
}

function json_out(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body);
    exit;
}

/* -------------------------------------------------------------- setup screen */
function setup_screen(string $note = '', bool $good = false): void
{
    $colour = $good ? '#00E676' : '#D96570';
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>FreeModel relay — key</title><style>'
       . 'body{margin:0;min-height:100vh;display:grid;place-items:center;background:#08090B;color:#E6E8EB;'
       . 'font:15px/1.6 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;padding:22px}'
       . 'form{width:100%;max-width:430px;background:#101216;border:1px solid #1E222A;border-radius:16px;padding:22px}'
       . 'h1{margin:0 0 6px;font-size:18px}p{margin:8px 0;color:#9AA3AE;font-size:13px}'
       . 'input{width:100%;box-sizing:border-box;margin:12px 0;padding:12px 14px;border-radius:10px;'
       . 'border:1px solid #2A2F38;background:#0B0D10;color:#E6E8EB;font-size:14px}'
       . 'button{width:100%;padding:12px;border:0;border-radius:10px;background:#38BDF8;color:#06131C;'
       . 'font-weight:600;font-size:14px;cursor:pointer}code{color:#38BDF8}'
       . '</style></head><body><form method="post" action="?setup=1">'
       . '<h1>FreeModel relay</h1>'
       . '<p>One time only. Paste your <code>fm_live_…</code> key. It is saved on this server, '
       . 'never in the Aurora page.</p>'
       . '<input type="password" name="key" placeholder="fm_live_…" autocomplete="off" required>'
       . '<button type="submit">Save key</button>';

    if ($note !== '') {
        echo '<p style="color:' . $colour . '">' . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    echo '</form></body></html>';
    exit;
}

if ($method === 'POST' && isset($_GET['setup'])) {
    if (active_key() !== '') {
        setup_screen('A key is already saved here. To replace it, delete .fmkey.php on the server and reload this page.', true);
    }
    $typed = trim((string) ($_POST['key'] ?? ''));
    if (!preg_match('/^fm_[A-Za-z0-9_\-]{8,}$/', $typed)) {
        setup_screen('That does not look like a FreeModel key — they start with fm_live_. Nothing was saved.');
    }
    if (!is_writable(__DIR__)) {
        setup_screen('This host will not let php write the file. Open api-free.php and put the key in FREEMODEL_KEY instead.');
    }
    $written = @file_put_contents(KEY_FILE, "<?php exit; ?>\n" . $typed . "\n", LOCK_EX);
    if ($written === false) {
        setup_screen('The key could not be saved. Check the folder permissions, or put it in FREEMODEL_KEY inside the file.');
    }
    @chmod(KEY_FILE, 0600);
    setup_screen('Saved. Your key now lives on this server only. Go back to Aurora → Settings → 🩺 Test connection.', true);
}

$key = active_key();

/* No key yet? A browser gets the form; the page gets a JSON answer it can show. */
if ($key === '') {
    if ($method === 'GET' && !isset($_GET['models'])) {
        setup_screen();
    }
    json_out(500, ['error' => ['message' => 'This relay has no key yet. Open its address in a browser and paste your fm_live_ key.']]);
}

/* ----------------------------------------------------------------- upstream */
function upstream(string $method, string $url, ?array $body, bool $stream): void
{
    $headers = [
        'Authorization: Bearer ' . active_key(),
        'Content-Type: application/json',
        'Accept: ' . ($stream ? 'text/event-stream' : 'application/json'),
        'X-Title: Aurora',
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_HEADER         => false,
        CURLOPT_HEADERFUNCTION => function ($ch, string $raw) {
            $trim = trim($raw);
            if ($trim === '' || str_starts_with(strtolower($trim), 'http/')) {
                return strlen($raw);
            }
            $lower = strtolower($trim);
            /* let the browser see the useful headers, never the upstream's cookie noise */
            if (str_starts_with($lower, 'content-type:') || str_starts_with($lower, 'cache-control:') || str_starts_with($lower, 'x-ratelimit')) {
                header($trim);
            }
            return strlen($raw);
        },
        CURLOPT_WRITEFUNCTION  => function ($ch, string $chunk) {
            echo $chunk;
            if (function_exists('ob_flush')) { @ob_flush(); }
            @flush();
            return strlen($chunk);
        },
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $ok = curl_exec($ch);
    if ($ok === false) {
        http_response_code(502);
        header('Content-Type: application/json');
        echo json_encode(['error' => ['message' => 'Could not reach FreeModel: ' . curl_error($ch)]]);
    }
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($status >= 400 && $status !== 0) {
        http_response_code($status);
    }
    curl_close($ch);
}

/* --------------------------------------------------------------------- routes */
if ($method === 'GET' && isset($_GET['models'])) {
    header('Content-Type: application/json');
    unset($_GET['models']);
    upstream('GET', FREEMODEL_BASE . '/models', null, false);
    exit;
}

if ($method !== 'POST') {
    json_out(405, ['error' => ['message' => 'POST a chat body here, or GET ?models=1 for the catalogue.']]);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '[]', true);
if (!is_array($payload)) {
    json_out(400, ['error' => ['message' => 'The body was not JSON.']]);
}

/* Fill in what the page is allowed to leave out; refuse to let it override the endpoint. */
if (empty($payload['model']) || !is_string($payload['model']) || in_array($payload['model'], ['auto', 'default'], true)) {
    $payload['model'] = DEFAULT_MODEL;
}
unset($payload['baseUrl'], $payload['apiKey'], $payload['provider']);
$payload['stream'] = true;                    /* the relay always streams */

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('X-Accel-Buffering: no');              /* nginx: do not buffer the stream */

while (ob_get_level() > 0) { @ob_end_flush(); }

upstream('POST', FREEMODEL_BASE . '/chat/completions', $payload, true);
