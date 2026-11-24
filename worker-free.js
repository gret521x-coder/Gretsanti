/**
 * worker-free.js — the FreeModel relay, Cloudflare Worker flavour.
 *
 * WHY THIS EXISTS
 * FreeModel will not let a page on another website call it (CORS). The browser stops
 * the call before it leaves, which shows up in Aurora as "Failed to fetch". A relay
 * fixes that: the server makes the call, the page talks to the server.
 *
 * `api-free.php` is the relay for a PHP host. This is the same relay for a host that
 * cannot do PHP — or for a free PHP host that blocks other websites calling it.
 *
 * HOW TO PUT IT ONLINE (about 3 minutes, no card, no domain)
 *  1. Go to dash.cloudflare.com, sign in, open "Workers & Pages", press "Create",
 *     choose "Worker", and name it something like aurora-free.
 *  2. Replace the sample code with everything in this file, then press "Deploy".
 *  3. Open the Worker's Settings → Variables → add a **Secret** named FREEMODEL_KEY
 *     and paste your fm_live_… key as its value. Save.
 *  4. Copy the Worker address it gives you (https://aurora-free.YOURNAME.workers.dev).
 *  5. In Aurora → Settings ⚙️, paste that address into "Proxy URL", press
 *     "🩺 Test connection", then "Save".
 *
 * The key never leaves Cloudflare. The page sends no Authorization header at all.
 */

const FREEMODEL_BASE = 'https://freemodel.in/v1';
const DEFAULT_MODEL = 'deepseek-v4.1-flash';

/* Sites allowed to call this relay. Add your own if you host Aurora elsewhere. */
const ALLOWED_HOSTS = [
  'gret521x-coder.github.io',
  'aidekupal.unaux.com',        /* if you host a copy of the page on unaux */
  'localhost',
  '127.0.0.1'
];

function corsHeaders(origin, allowed){
  const h = {
    'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type, X-Title',
    'Access-Control-Max-Age': '86400',
    'Vary': 'Origin'
  };
  if(allowed && origin) h['Access-Control-Allow-Origin'] = origin;
  return h;
}

function originAllowed(origin){
  if(!origin) return false;
  let host;
  try { host = new URL(origin).hostname; } catch(e){ return false; }
  return ALLOWED_HOSTS.some(h => host === h || host.endsWith('.' + h));
}

function json(obj, status, origin, allowed){
  return new Response(JSON.stringify(obj), {
    status,
    headers: Object.assign({ 'Content-Type': 'application/json; charset=utf-8' }, corsHeaders(origin, allowed))
  });
}

export default {
  async fetch(request, env){
    const origin = request.headers.get('Origin') || '';
    const allowed = originAllowed(origin);

    /* The browser asks permission before the real call. */
    if(request.method === 'OPTIONS') return new Response(null, { status: 204, headers: corsHeaders(origin, allowed) });

    if(origin && !allowed){
      return json({ error: 'This relay does not allow ' + origin + '. Add that host to ALLOWED_HOSTS.' }, 403, origin, false);
    }

    const key = (env && env.FREEMODEL_KEY) || '';
    if(!key || /PASTE|YOUR_KEY/i.test(key)){
      return json({ error: 'key not set — add FREEMODEL_KEY as a Secret in the Worker settings' }, 500, origin, allowed);
    }

    const url = new URL(request.url);

    /* The model list, so "List models from FreeModel" still works through the relay. */
    if(request.method === 'GET'){
      if(url.searchParams.get('models') !== '1') return json({ ok: true, relay: 'aurora-free', hint: 'GET ?models=1 for the model list, POST for chat' }, 200, origin, allowed);
      let upstream;
      try {
        upstream = await fetch(FREEMODEL_BASE + '/models', { headers: { Authorization: 'Bearer ' + key } });
      } catch(e){
        return json({ error: 'could not reach FreeModel: ' + e.message }, 502, origin, allowed);
      }
      const body = await upstream.text();
      return new Response(body, {
        status: upstream.status,
        headers: Object.assign({ 'Content-Type': 'application/json; charset=utf-8' }, corsHeaders(origin, allowed))
      });
    }

    if(request.method !== 'POST') return json({ error: 'POST a chat body here, or GET ?models=1' }, 405, origin, allowed);

    let payload;
    try { payload = await request.json(); }
    catch(e){ return json({ error: 'the body was not JSON' }, 400, origin, allowed); }

    /* Anything the page might send about where to go or which key to use is ignored —
       this relay decides both. */
    delete payload.baseUrl;
    delete payload.apiKey;
    delete payload.provider;
    if(!payload.model || /^(auto|default)$/i.test(payload.model)) payload.model = DEFAULT_MODEL;
    if(!Array.isArray(payload.messages)) return json({ error: 'messages must be a list' }, 400, origin, allowed);

    let upstream;
    try {
      upstream = await fetch(FREEMODEL_BASE + '/chat/completions', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': 'Bearer ' + key,
          'Accept': payload.stream === false ? 'application/json' : 'text/event-stream'
        },
        body: JSON.stringify(payload)
      });
    } catch(e){
      return json({ error: 'could not reach FreeModel: ' + e.message }, 502, origin, allowed);
    }

    /* Streaming passes straight through: no buffering, no re-encoding, so the words
       appear as FreeModel sends them. */
    return new Response(upstream.body, {
      status: upstream.status,
      headers: Object.assign({
        'Content-Type': upstream.headers.get('Content-Type') || 'text/event-stream; charset=utf-8',
        'Cache-Control': 'no-cache',
        'X-Accel-Buffering': 'no'
      }, corsHeaders(origin, allowed))
    });
  }
};
