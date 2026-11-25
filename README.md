# Gretsanti

Launcher, group chat and Aurora — plain HTML files, no build step, no framework.

**The live copies run on the PHP host.** The repository is private, and a private repo stops publishing
Pages on a free account, so `github.io` serves nothing.

| What | Address |
|---|---|
| Launcher | `https://aidekupal.unaux.com/index.html` |
| Aurora | `https://aidekupal.unaux.com/Aurora.html` |
| Group chat | `https://aidekupal.unaux.com/gc-v5.html` |
| FreeModel relay | `https://aidekupal.unaux.com/api-free.php` |

## Files

| File | Size | What |
|---|---|---|
| `index.html` | 31 KB | Launcher gated by `kupal` (an upload of `index-v5.html`) |
| `index-v5.html` | 54 KB | Launcher in use — motion, and cards that open the file name the host actually has |
| `index-v6.html` | 32 KB | Launcher variant gated by `kupal` — same deck, with the PLD-07 connection check |
| `gc-v5.html` | 139 KB | Group chat — KDL skin, lightning storm, sky of stars/satellites/rockets |
| `api-v5.php` | 43 KB | Backend for the chat: log, presence, uploads, single admin slot |
| `aurora-v4.html` | 118 KB | Aurora — FreeModel only, KDL skin |
| `api-free.php` | 10 KB | FreeModel relay — key typed once in a browser form, streamed answers |
| `worker-free.js` | 5 KB | Same relay as a free Cloudflare Worker, for a host that blocks cross-site calls |
| `aurora-v3.html` · `aurora-v2.html` · `Aurora.html` | 96 · 83 · 53 KB | Older Aurora builds, kept as fallback |
| `tns.html` | 33 KB | TNS — Safety Dispatch |
| `pindutan.html` | 43 KB | Pindutan v14 arcade game |
| `Screenshot.html` · `save.php` | 3 · 1 KB | Collectibles vault — password-gated image board |
| `index-v1…v4.html` · `gc`/`gc-v2…v4` · `api-v2…v4.php` | — | Archive, one design generation each |
| `agent-widget.js` · `api-ai.php` · `agent-demo.html` | 22 · 13 · 4 KB | Floating agent bubble any page can load |
| `diag.html` | 8 KB | Link diagnostic — says whether the PHP host answers, and why not |
| `arena-diag.html` | 9 KB | Arena access check gated by `arena1x` — reachability test + saved VPN-portal link |
| `labs/` | 7 KB + | Experiments: Castle Defense, Call Spider (one file), the transcript extension, the auto clicker APK |

## Latest changes

**Aurora v4 — FreeModel only.** One platform, `https://freemodel.in/v1`, default `deepseek-v4.1-flash`.
Every other provider name is gone from the page and a test greps for 13 of them. A key inside an HTML file
cannot be hidden — hashing is one-way — so the key lives in `api-free.php` on the server and the page sends
no `Authorization` header at all.

**Why "Failed to fetch".** FreeModel does not let a page on another site call it. The page now says that
plainly instead of blaming the key, the Proxy box refuses FreeModel's own address, and **🩺 Test connection**
checks both doors — the model list, then one real chat request — and saves the address it proved.

**Launcher.** Cards resolve the real file name on the host (`Aurora.html` vs `aurora-v4.html`), remember the
answer, and never read a refused probe as a missing file. Relative cards point at the PHP host, so the
launcher works wherever it is opened from.

**Chat.** The KDL // MISSION CONTROL skin — pure black deck, hairline grid, square corners, mono caps, one
cyan accent. A cyan lightning storm fires on send and on arrival, and a sky runs behind the messages: stars
drifting, satellites on slow orbits, a rocket every few seconds. Paused when the tab hides, off under
`prefers-reduced-motion`.

**Connection check.** A new launcher card opens `arena-diag.html`. It probes `arena.ai`, the PHP host and the
public web with `no-cors` fetches, then says whether the failure is the network or just Arena. It cannot make
a VPN — a browser page has no such power — so it stores your company VPN portal link on the device instead,
and only accepts `http://` or `https://` addresses. Older saved launcher grids get the card once; deleting it
afterwards keeps it deleted.

**v6 launcher and the three gates.** `index-v6.html` is a separate copy with PLD-07 in the grid; its unlock,
grid and card-flag keys are all `v6`, so it neither inherits nor overwrites the older launcher's session.
There are three different codes: **launcher `kupal`**, **Bardagulan `sheryl`** (the existing signature already
matched that word), and **diag `arena1x`** behind its own lock screen with a LOCK AGAIN button. All three are
client-side checks — obstacles, not real security; anyone with the files can read or bypass them.

**Security.** The key never enters the repository and never enters the page. Pasted keys are treated as
leaked — revoke them. `.fmkey.php` on the server starts with `<?php exit; ?>`, so it reads blank over the web.

## Checks

All suites run in Node with jsdom, no browser needed.

- **Chat v5** — 71 · **v4 features** 37 · **v3 features** 41 · **API errors** 14 · **Pages** 11
- **Chat skin and sky** — 31: palette, square corners, mono caps, storm on send and arrival, canvas seeds
  stars and satellites, loop runs, survives a missing canvas, gone under reduced motion
- **Launcher** — 70: every card resolves to the PHP host, `Aurora.html` found when `aurora-v4.html` is
  missing, refused probe never read as missing, chosen name remembered
- **Aurora v4** — 49 + 54: only FreeModel survives, proxy sends no key, CORS wall named, dead relay gets its
  own message, Test connection proves a chat request
- `api-free.php` and `api-v5.php` are parsed with a real PHP grammar (no PHP binary in the sandbox), and
  every page's CSS goes through PostCSS before it ships
