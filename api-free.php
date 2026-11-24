<?php
/**
 * api-free.php — the only place the FreeModel key exists.
 *
 * Upload this next to gc.html on your PHP host. The key is filled in below, on the server.
 * Aurora then talks to this file, so the browser never receives, stores or needs the key.
 *
 *   FreeModel endpoint  : https://freemodel.in/v1  (OpenAI-compatible)
 *   Aurora → this file  : POST  (chat, streamed)
 *   ?models=1           : GET   (the catalogue, so "list models" works)
 *
 * Nothing is logged. No key is ever echoed back.
 */

declare(strict_types=1);

/* ------------------------------------------------------------------ configure */
const FREEMODEL_KEY   = 'PASTE-YOUR-fm_live_KEY-HERE';   /* keep this line out of GitHub */
const FREEMODEL_BASE  = 'https://freemodel.in/v1';
const DEFAULT_MODEL   = 'deepseek-v4.1-flash';

/* Browsers only honour a CORS reply from an origin you list here. Add or remove as needed. */
const ALLOWED_ORIGINS = [
    'https://gret521x-coder.github.io',
    'http://localhost',
    'http://127.0.0.1',
];

/* ------------------------------------------------------------------- plumbing */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && (in_array($origin, ALLOWED_ORIGINS, true) || preg_match('#^https?://localhost(:\d+)?$#', $origin))) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Title, Authorization');
header('Access-Control-Max-Age: 86400');
header('X-Content-Type-Options: nosniff');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (FREEMODEL_KEY === 'PASTE-YOUR-fm_live_KEY-HERE') {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => ['message' => 'api-free.php has no key yet — open the file and fill in FREEMODEL_KEY.']]);
    exit;
}

function upstream(string $method, string $url, ?array $body, bool $stream): void
{
    $headers = [
        'Authorization: Bearer ' . FREEMODEL_KEY,
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
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['models'])) {
    header('Content-Type: application/json');
    unset($_GET['models']);
    upstream('GET', FREEMODEL_BASE . '/models', null, false);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => ['message' => 'POST a chat body here, or GET ?models=1 for the catalogue.']]);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '[]', true);
if (!is_array($payload)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => ['message' => 'The body was not JSON.']]);
    exit;
}

/* Fill in what the page is allowed to leave out; refuse to let it override the endpoint. */
if (empty($payload['model']) || !is_string($payload['model'])) {
    $payload['model'] = DEFAULT_MODEL;
}
unset($payload['baseUrl'], $payload['apiKey']);
$payload['stream'] = true;                    /* the proxy always streams */

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('X-Accel-Buffering: no');              /* nginx: do not buffer the stream */

while (ob_get_level() > 0) { @ob_end_flush(); }

upstream('POST', FREEMODEL_BASE . '/chat/completions', $payload, true);
