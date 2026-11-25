# Kupal works

Launcher, chat and Aurora — plain HTML/PHP, no build step. Repo is **public with Pages off**; live copies run on the PHP host.

| Live | URL |
|---|---|
| Launcher | `https://aidekupal.unaux.com/index.html` |
| Aurora | `https://aidekupal.unaux.com/Aurora.html` |
| Chat | `https://aidekupal.unaux.com/gc-v5.html` |
| FreeModel relay | `https://aidekupal.unaux.com/api-free.php` |

## Codes

| Gate | Code |
|---|---|
| Launcher (`index.html`, `index-v6.html`) | `kupal` |
| Bardagulan card | `sheryl` |
| `arena-diag.html` | `arena1x` |

Client-side checks — obstacles, not security.

## Files

- `index.html` — launcher, PLD-01…07; `index-v6.html` — separate gated copy with v6-only storage keys.
- `arena-diag.html` — reachability check + saved VPN-portal link.
- `gc-v5.html` / `api-v5.php` — chat + backend, KDL skin, storm and sky.
- `aurora-v4.html`, `Aurora.html`, `api-free.php`, `worker-free.js` — Aurora + FreeModel relay.
- `labs/pindutan-v17.html` — the only current Pindutan build.
- `labs/` — v17 design refs, Call Spider, transcript extension, auto-clicker APK.
- `agent-widget.js`, `api-ai.php`, `agent-demo.html` — floating agent.
- `tns.html`, `Screenshot.html`, `save.php` — dispatch and collectibles vault.
- `previous/` — archived launchers, chat/APIs, Aurora v2-v3, Pindutan v14-v16, Fortress, Castle Defense, old diag.

## Changes

- **Repo public, no Pages site.** `has_pages: false` — nothing published on `github.io`.
- **Old builds moved to `previous/`.** Current work stays in the root; v17 is the only live game, v15/v16/Fortress/Castle Defense archived.
- **Live-site fixes.** Bardagulan → `gc-v5.html`, Aurora → `Aurora.html`; saved grids upgraded off dead `gc.html` / `aurora-v4.html`. Launcher must sit in the host **root** — `/labs/index.html` breaks its relative cards.
- **PLD-07 Connection check.** Probes `arena.ai`, the PHP host and the public web, says whether the block is the network or Arena, and stores an `http(s)` VPN-portal link. Own `arena1x` lock screen with LOCK AGAIN.
- **Three gates.** Launcher `kupal`, Bardagulan `sheryl`, diag `arena1x`; v6 keeps its own unlock/grid keys.
- **Pindutan v17.** Lobby redesign — block wordmark, dot grid, tetromino avatars, 2×2 game cards; engine unchanged from v16.
- **Aurora v4 / chat v5.** FreeModel-only, key stays on the server; CORS wall and dead relay named; KDL // MISSION CONTROL skin with lightning storm and sky.
- **Security.** No real keys in the repo; `.fmkey.php` begins with `<?php exit; ?>`; pasted keys treated as leaked.

## Checks

Node/jsdom: chat 71/37/41/14/11, skin 31, launcher 70, Aurora 49+54. PHP parsed with a grammar, CSS through PostCSS. Live check: `api-v5.php?action=health` ok; chat, Arena diag, Pindutan v17, Aurora, TNS and Collectibles load.
