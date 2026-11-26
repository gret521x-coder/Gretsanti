/**
 * ============================================================================
 *  Pindutan leaderboard — Cloudflare Worker version
 *  ---------------------------------------------------------------------------
 *  The same board as api-leaderboard.php, but it runs on Cloudflare instead of
 *  a free PHP host: no 503s, no folder permissions, and nothing to keep alive.
 *
 *  What you need (the steps are in labs/LEADERBOARD-SETUP.md):
 *    - a free Cloudflare account
 *    - one KV namespace, bound to this Worker as   LADDER
 *
 *  Endpoints — identical to the PHP one, so the game needs no change:
 *    GET  ?action=top&mode=all|duel|group&limit=25
 *    POST ?action=report   {name, mode, win, score, lines, ts, sig, id}
 *    POST ?action=reset    {key}
 *
 *  Storage: one KV key, "table", holding the whole board as JSON.
 *  KV allows about one write per second to the same key, so a write that is
 *  refused is retried once after a short wait — plenty for a board for friends.
 *
 *  Honest security note: the signature keeps honest players honest and stops
 *  dumb spam, but the shared secret ships inside the game, so a determined
 *  person can forge a result. This is a scoreboard, not a ranked ladder.
 * ============================================================================
 */

const LB_SECRET = "kdl-pindutan-lb-2026-7f3a";
const MIN_NAME = 2;
const MAX_NAME = 14;
const MAX_SCORE = 5000000;
const MAX_PLAYERS = 900;
const MAX_LIMIT = 100;
const RATE_WINDOW = 60;          // seconds
const RATE_REPORTS = 30;         // reports per window per IP
const RATE_READS = 120;          // reads per window per IP
const TS_SKEW = 900;             // seconds a report may be off before it is stale

/* Names that get the crown next to them on the board, for everyone to see.
   Lowercase, exactly as it is typed in the game. Decoration, not permission. */
const CREATOR_NAMES = ["ace"];

const CORS = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Methods": "GET, POST, OPTIONS",
  "Access-Control-Allow-Headers": "Content-Type",
  "Access-Control-Max-Age": "86400",
  "X-Content-Type-Options": "nosniff",
  "Cache-Control": "no-store, max-age=0",
  "Content-Type": "application/json; charset=utf-8"
};

/* ---------------------------------------------------------------- helpers */

function out(payload, code = 200) {
  return new Response(JSON.stringify(payload), { status: code, headers: CORS });
}

function fail(message, code = 400) {
  return out({ ok: false, error: message }, code);
}

function cleanName(raw) {
  let s = String(raw == null ? "" : raw);
  s = s.replace(/[\u0000-\u001F\u007F]/gu, "");
  s = s.replace(/[^\p{L}0-9 ._-]/gu, "");
  s = s.replace(/\s+/gu, " ").trim();
  return Array.from(s).slice(0, MAX_NAME).join("");
}

function nameKey(name) {
  return String(name).toLowerCase();
}

function nameLength(name) {
  return Array.from(String(name)).length;
}

/** The same 64-bit digest the game computes (lbDigest) and api-leaderboard.php
 *  computes: FNV-1a 32-bit for the first half, a plain rolling hash for the
 *  second, printed as 16 hex characters. Not SHA-256 on purpose — the game has
 *  to be able to compute it everywhere, including over plain http. */
function lbHash(str) {
  let h1 = 0x811c9dc5;
  let h2 = 0;
  for (let i = 0; i < str.length; i++) {
    const c = str.charCodeAt(i) & 0xff;
    h1 = Math.imul(h1 ^ c, 16777619) >>> 0;
    h2 = (Math.imul(h2, 131) + c) >>> 0;
  }
  const hex = n => ("0000000" + (n >>> 0).toString(16)).slice(-8);
  return hex(h1) + hex(h2);
}

function signature(r) {
  return lbHash(r.name + "|" + r.mode + "|" + r.win + "|" + r.score + "|" + r.lines + "|" + r.ts + "|" + LB_SECRET);
}

function blankPlayer(name) {
  return {
    name: name,
    wins: 0, losses: 0, best: 0,
    streak: 0, bestStreak: 0, last: 0, ids: [],
    duel: { wins: 0, losses: 0, best: 0 },
    group: { wins: 0, losses: 0, best: 0 }
  };
}

function viewRow(p, mode, rank) {
  const slice = mode === "all"
    ? { wins: Number(p.wins) || 0, losses: Number(p.losses) || 0, best: Number(p.best) || 0 }
    : {
        wins: Number(p[mode] && p[mode].wins) || 0,
        losses: Number(p[mode] && p[mode].losses) || 0,
        best: Number(p[mode] && p[mode].best) || 0
      };
  const played = slice.wins + slice.losses;
  return {
    rank: rank,
    name: String(p.name),
    crown: CREATOR_NAMES.indexOf(nameKey(p.name)) >= 0,
    wins: slice.wins,
    losses: slice.losses,
    best: slice.best,
    played: played,
    rate: played > 0 ? Math.round(slice.wins * 100 / played) : 0,
    streak: Number(p.streak) || 0,
    bestStreak: Number(p.bestStreak) || 0,
    last: Number(p.last) || 0
  };
}

function sortedRows(players, mode, limit) {
  const rows = [];
  Object.keys(players).forEach(key => {
    const p = players[key];
    if (!p || typeof p !== "object" || p.name === undefined) return;
    const wins = mode === "all" ? Number(p.wins) || 0 : Number(p[mode] && p[mode].wins) || 0;
    if (wins <= 0) return;                 /* nobody with zero wins is ranked */
    rows.push([p, wins]);
  });
  rows.sort((a, b) => {
    if (a[1] !== b[1]) return b[1] - a[1];                                   /* most wins first */
    const ab = mode === "all" ? Number(a[0].best) || 0 : Number(a[0][mode] && a[0][mode].best) || 0;
    const bb = mode === "all" ? Number(b[0].best) || 0 : Number(b[0][mode] && b[0][mode].best) || 0;
    if (ab !== bb) return bb - ab;                                           /* then the higher best */
    return String(a[0].name).localeCompare(String(b[0].name));
  });
  return rows.slice(0, limit).map((entry, i) => viewRow(entry[0], mode, i + 1));
}

function rankOf(players, mode, key) {
  const rows = sortedRows(players, mode, MAX_PLAYERS);
  for (let i = 0; i < rows.length; i++) {
    if (nameKey(rows[i].name) === key) return [rows[i].rank, rows.length];
  }
  return [0, rows.length];
}

/* --------------------------------------------------------------- storage */

async function readTable(env) {
  let raw = null;
  try { raw = await env.LADDER.get("table"); } catch (e) { raw = null; }
  if (!raw) return { updated: 0, players: {} };
  let data = null;
  try { data = JSON.parse(raw); } catch (e) { data = null; }
  if (!data || typeof data !== "object" || !data.players || typeof data.players !== "object") {
    return { updated: 0, players: {} };
  }
  return data;
}

async function writeTable(env, table) {
  const raw = JSON.stringify(table);
  for (let attempt = 0; attempt < 2; attempt++) {
    try {
      await env.LADDER.put("table", raw);
      return true;
    } catch (e) {
      /* KV takes about one write a second per key; wait it out once */
      if (attempt === 0) await new Promise(r => setTimeout(r, 1200));
    }
  }
  return false;
}

/* best effort only: a KV write that is refused must never block a result */
async function rateLimit(env, ip, max, label) {
  const key = "rate:" + label + ":" + ip;
  const now = Math.floor(Date.now() / 1000);
  let rec = null;
  try { rec = await env.LADDER.get(key, "json"); } catch (e) { rec = null; }
  let entry = rec && typeof rec === "object" && now - Number(rec.since || 0) <= RATE_WINDOW
    ? { since: Number(rec.since) || now, count: Number(rec.count) || 0 }
    : { since: now, count: 0 };
  entry.count += 1;
  try {
    await env.LADDER.put(key, JSON.stringify(entry), { expirationTtl: RATE_WINDOW * 2 });
  } catch (e) { /* ignore: the counter is a courtesy, not a gate */ }
  return entry.count <= max;
}

/* --------------------------------------------------------------- routing */

async function handle(request, env) {
  if (request.method === "OPTIONS") {
    return new Response(null, { status: 204, headers: CORS });
  }
  if (!env || !env.LADDER) {
    return fail("the KV namespace LADDER is not bound to this worker", 500);
  }

  const url = new URL(request.url);
  const action = String(url.searchParams.get("action") || "top").toLowerCase();
  const mode = String(url.searchParams.get("mode") || "all").toLowerCase();
  const ip = request.headers.get("CF-Connecting-IP") || "unknown";

  if (["all", "duel", "group"].indexOf(mode) < 0) return fail("unknown mode");

  const table = await readTable(env);

  /* ---------------------------------------------------------------- read */
  if (action === "top") {
    await rateLimit(env, ip, RATE_READS, "top");
    const limit = Math.max(1, Math.min(MAX_LIMIT, parseInt(url.searchParams.get("limit") || "25", 10) || 25));
    return out({
      ok: true,
      mode: mode,
      updated: Number(table.updated) || 0,
      players: Object.keys(table.players).length,
      rows: sortedRows(table.players, mode, limit)
    });
  }

  /* ------------------------------------------------------------ one result */
  if (action === "report") {
    if (request.method !== "POST") return fail("report must be POST", 405);
    await rateLimit(env, ip, RATE_REPORTS, "report");

    let input = null;
    try { input = await request.json(); } catch (e) { input = null; }
    if (!input || typeof input !== "object") return fail("bad body");

    const reportId = String(input.id == null ? "" : input.id).replace(/[^A-Za-z0-9]/g, "").slice(0, 24);
    const name = cleanName(input.name);
    const modeIn = String(input.mode || "").toLowerCase();
    const win = Number(input.win) === 1 ? 1 : 0;
    const score = parseInt(input.score, 10) || 0;
    const lines = parseInt(input.lines, 10) || 0;
    const ts = parseInt(input.ts, 10) || 0;
    const sig = String(input.sig || "").slice(0, 16).toLowerCase();

    const len = nameLength(name);
    if (len < MIN_NAME || len > MAX_NAME) return fail("name must be " + MIN_NAME + "–" + MAX_NAME + " characters");
    if (["duel", "group"].indexOf(modeIn) < 0) return fail("mode must be duel or group");
    if (score < 0 || score > MAX_SCORE) return fail("score out of range");
    if (lines < 0 || lines > 100000) return fail("lines out of range");

    const now = Math.floor(Date.now() / 1000);
    if (ts < now - TS_SKEW || ts > now + TS_SKEW) return fail("stale report");

    const check = { name: name, mode: modeIn, win: win, score: score, lines: lines, ts: ts };
    if (signature(check) !== sig) return fail("bad signature", 403);

    const key = nameKey(name);
    const player = table.players[key] && typeof table.players[key] === "object"
      ? table.players[key]
      : blankPlayer(name);

    /* one report per battle, twice over: same id = same battle, and a
       timestamp that is not newer than the last one is a replay */
    const seenIds = Array.isArray(player.ids) ? player.ids : [];
    if (reportId !== "" && seenIds.indexOf(reportId) >= 0) {
      const [rank, of] = rankOf(table.players, modeIn, key);
      return out({ ok: true, duplicate: true, rank: rank, of: of, name: player.name });
    }
    if ((Number(player.last) || 0) >= ts) {
      const [rank, of] = rankOf(table.players, modeIn, key);
      return out({ ok: true, duplicate: true, rank: rank, of: of, name: player.name });
    }
    if (reportId !== "") player.ids = seenIds.concat([reportId]).slice(-20);

    player.name = name;
    player.last = ts;
    if (!player.duel) player.duel = { wins: 0, losses: 0, best: 0 };
    if (!player.group) player.group = { wins: 0, losses: 0, best: 0 };

    if (win === 1) {
      player.wins = (Number(player.wins) || 0) + 1;
      player[modeIn].wins = (Number(player[modeIn].wins) || 0) + 1;
      player.streak = (Number(player.streak) || 0) + 1;
      if (player.streak > (Number(player.bestStreak) || 0)) player.bestStreak = player.streak;
    } else {
      player.losses = (Number(player.losses) || 0) + 1;
      player[modeIn].losses = (Number(player[modeIn].losses) || 0) + 1;
      player.streak = 0;
    }
    if (score > (Number(player.best) || 0)) player.best = score;
    if (score > (Number(player[modeIn].best) || 0)) player[modeIn].best = score;

    table.players[key] = player;
    table.updated = now;

    /* keep the value bounded — drop the weakest players when it overflows */
    const keys = Object.keys(table.players);
    if (keys.length > MAX_PLAYERS) {
      keys.sort((a, b) => {
        const wa = Number(table.players[a].wins) || 0, wb = Number(table.players[b].wins) || 0;
        if (wa !== wb) return wb - wa;
        return (Number(table.players[b].best) || 0) - (Number(table.players[a].best) || 0);
      });
      const kept = {};
      keys.slice(0, MAX_PLAYERS).forEach(k => { kept[k] = table.players[k]; });
      table.players = kept;
    }

    if (!await writeTable(env, table)) return fail("could not save the table", 500);

    const [rank, of] = rankOf(table.players, modeIn, key);
    return out({
      ok: true,
      duplicate: false,
      rank: rank,
      of: of,
      you: viewRow(player, modeIn, rank),
      rows: sortedRows(table.players, modeIn, 10)
    });
  }

  /* ------------------------------------------------------------- wipe it */
  if (action === "reset") {
    if (request.method !== "POST") return fail("reset must be POST", 405);
    let body = null;
    try { body = await request.json(); } catch (e) { body = null; }
    const key = String((body && body.key) || url.searchParams.get("key") || "");
    if (key !== LB_SECRET) return fail("nope", 403);
    await writeTable(env, { updated: Math.floor(Date.now() / 1000), players: {} });
    return out({ ok: true, cleared: true });
  }

  return fail("unknown action", 404);
}

export default { fetch: handle };
