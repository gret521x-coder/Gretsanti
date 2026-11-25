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
- `pindutan-v18.html` — current Pindutan build; the human-vs-human rival board is finally visible.
- `labs/td-v6.html` — current Pixel Orcs tower defense with embedded field-guide art, self-contained canvas game.
- `labs/` — TD v6, TD v5/v6 design art, Pindutan design refs, Call Spider, transcript extension, auto-clicker APK.
- `agent-widget.js`, `api-ai.php`, `agent-demo.html` — floating agent.
- `tns.html`, `Screenshot.html`, `save.php` — dispatch and collectibles vault.
- `previous/` — archived launchers, chat/APIs, Aurora v2-v3, Pindutan v14-v16, Fortress, Castle Defense, old diag.

## Changes

- **Repo public, no Pages site.** `has_pages: false` — nothing published on `github.io`.
- **Old builds moved to `previous/`.** Current work stays in the root/labs; v18 supersedes Pindutan v17, TD v6 supersedes v5, and v14-v17/Fortress/Castle Defense are archived.
- **Live-site fixes.** Bardagulan → `gc-v5.html`, Aurora → `Aurora.html`; saved grids upgraded off dead `gc.html` / `aurora-v4.html`. Launcher must sit in the host **root** — `/labs/index.html` breaks its relative cards.
- **PLD-07 Connection check.** Probes `arena.ai`, the PHP host and the public web, says whether the block is the network or Arena, and stores an `http(s)` VPN-portal link. Own `arena1x` lock screen with LOCK AGAIN.
- **Three gates.** Launcher `kupal`, Bardagulan `sheryl`, diag `arena1x`; v6 keeps its own unlock/grid keys.
- **Pindutan v18.** The 1v1/group rival board is now visible side by side with yours: the old opponents panel was stuck on `display:none`. Live board snapshots (including the falling piece), rival score/lines/sent tabs, spectator view and saved-grid upgrade from v17; v17 lobby redesign and engine kept.
- **TD v6 added.** Pixel Orcs is live as PLD-08 in `labs/` — one HTML file, no external assets. v5 is archived.
- **TD v5 tuned.** Per-tower target priority (CLOSE/FIRST/LAST/STRONG), pause + 1-3× speed, best wave/loop saved locally, stacking poison, level-scaled frost, Tesla chain falloff, generated intel table and a wave-overlap guard.
- **TD v5 design + modes.** Pixel HUD with shop/bestiary panels; CLASSIC / RUSH / KUPAL difficulty (lives, gold, enemy HP/speed, spawn rate, loop size), saved on device. Reduced-motion aware.
- **TD v5 art pass.** Card-based shop with live tower previews, 7 themed maps with edges/deco/portals, brighter monster palettes and a distinct sky boss, rebuilt towers with level pips and muzzle flashes.
- **TD v6 art + changes.** Embedded battlefield briefing image, animated map glows/deco, boss health bar, next-wave preview with air and warlord warnings. AUTO wave is ON by default, a wave-clear prompt explains manual mode, PLAY AGAIN restarts without refresh, and a stuck-enemy watchdog keeps waves from hanging; 37 TD checks plus a 7-check flow test pass.
- **Aurora v4 / chat v5.** FreeModel-only, key stays on the server; CORS wall and dead relay named; KDL // MISSION CONTROL skin with lightning storm and sky.
- **Security.** No real keys in the repo; `.fmkey.php` begins with `<?php exit; ?>`; pasted keys treated as leaked.

## Checks

Node/jsdom: chat 71/37/41/14/11, skin 31, launcher 70, Aurora 49+54, Pindutan v18 16 + two-client 12, TD v6 37 + flow 7. PHP parsed with a grammar, CSS through PostCSS. Live check: `api-v5.php?action=health` ok; chat, Arena diag, Pindutan v18, TD v6, Aurora, TNS and Collectibles load.
