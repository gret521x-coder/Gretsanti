# Gretsanti

Launcher, group chat, Aurora and Pindutan — plain HTML files, no build step, no framework.

**The live copies run on the PHP host.** Pages is not used: the files are uploaded to the host and opened
from there. The repository is public, which is what lets a hosted page read a build file from it.

| What | Address |
|---|---|
| Launcher | `https://aidekupal.unaux.com/index.html` |
| Aurora | `https://aidekupal.unaux.com/Aurora.html` |
| Group chat | `https://aidekupal.unaux.com/gc-v5.html` |
| Pindutan | `https://aidekupal.unaux.com/pindutan.html` |
| FreeModel relay | `https://aidekupal.unaux.com/api-free.php` |

## Files

| File | Size | What |
|---|---|---|
| `index.html` | 31 KB | Launcher (an upload of `index-v5.html`) |
| `index-v5.html` | 54 KB | Launcher in use — motion, and cards that open the file name the host actually has |
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
| `pindutan-run.html` | 8 KB | Pindutan runner — no game code; loads whichever build the feed names |
| `labs/pindutan-v21.html` | 192 KB | Pindutan v21 — the game, and the build that keeps itself current |
| `labs/pindutan-v20.html` | 192 KB | Previous Pindutan build, kept as the tested copy |
| `labs/pindutan-build.json` | 1 KB | The build pointer — which Pindutan is newest, and where |
| `labs/worker-leaderboard.js` | 14 KB | The ladder as a Cloudflare Worker, for when a PHP host is not wanted |
| `wrangler.jsonc` | 1 KB | Cloudflare build config — the worker, its entry file, and its KV box |
| `labs/index.html` | 12 KB | The experiment list |
| `labs/` | 7 KB + | Experiments: Castle Defense, Call Spider (one file), the transcript extension, the auto clicker APK |

## Latest changes

**Pindutan runner — one small file, always the newest game.** `pindutan-run.html` holds no game at all: it
reads `labs/pindutan-build.json` on `main` and then loads the build that file names, so a copy kept on a phone
never goes stale. If the repo cannot be reached it says so, offers a retry and a link to the hosted copy.

**Pindutan v21 — the page keeps itself current.** Open a copy and it reads one small file from this repo,
`labs/pindutan-build.json`; if that names something newer, it runs that instead. A hosted copy moves to the
new page — same address, same saved name, same ladder. A copy kept on a phone has the newer page written over
itself, after letting go of its relays, its lobby channel and its timers, so two versions never talk at once.
No signal, or a half download, and it simply plays the copy it has. **The game is uploaded to the host once
and never again**; publishing a build means putting it on `main` and bumping the tag in that one file.
Everything from the v20 line is inside it: the name gate and the hall of fame (wins from human battles only,
carried up to `api-leaderboard.php`), a **GLIDE** panel that sets how a held button moves — five presets and
four sliders, remembered on that phone — and two small pills beside RELAY, a green lamp then **K** or **I**,
that hand your own board to the Kupal brain or the Intermediate one in a 1v1, a group battle and against the
CPU, with the lit pill again to take your hands back. The three CPU levels were measured and rebuilt because
they had been playing the same best move: Beginner now settles for lesser spots and taps slowest,
Intermediate is brisk and leaves the odd hole, Kupal looks a piece ahead and barely waits.

**Pindutan v17 — you can see your opponent now.** In 1v1 and group battles the opponents panel was never
switched on (CSS hid it and nothing showed it again), and a SYNC without a board wiped the last board
anyway. Now, during a battle, a short strip under the board shows every rival's board live, about 4 frames a
second with the falling piece. The last one standing gets **YOU WIN** or **LAST ONE STANDING**, the rest get
**YOU LOSE** or **KNOCKED OUT**, and **Rematch** re-invites the same players. A group invite stays open for
its full window, and someone who joins late is added without restarting anyone. Boards travel as 200
characters on a new `pindutan/v17/` topic, so old v16 tabs stay in their own room.

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

**Security.** The key never enters the repository and never enters the page. Pasted keys are treated as
leaked — revoke them. `.fmkey.php` on the server starts with `<?php exit; ?>`, so it reads blank over the web.

## Checks

All suites run in Node with jsdom, no browser needed.

- **Chat v5** — 71 · **v4 features** 37 · **v3 features** 41 · **API errors** 14 · **Pages** 11
- **Chat skin and sky** — 31: palette, square corners, mono caps, storm on send and arrival, canvas seeds
  stars and satellites, loop runs, survives a missing canvas, gone under reduced motion
- **Launcher** — 70: every card resolves to the PHP host, `Aurora.html` found when `aurora-v4.html` is
  missing, refused probe never read as missing, chosen name remembered
- **Pindutan v21** — 193: engine, glance rules, rooms over four paths, the lobby (created by KDL under the
  wordmark, the room line beside the word Lobby, a card that is a name and Playing/Available and nothing
  else — the ladder is the only place that keeps score), the lobby chat (words
  ride the relay, never stored, markup shown as words, 80 characters, one message a second, floods turned
  away, five lines tall), the three-second countdown (a frozen board and a frozen CPU until GO), the storm
  (lightning for the whole room, a four-second fanfare when the maker arrives, a maker-only ⚡ beside the
  relays that broadcasts, a second bolt inside 600 ms held back), five themes that stick, the player card
  behind a row tap (wins, best, rate, streak, last seen — no losses), the ladder note that speaks of
  Cloudflare when the board lives there, the CPU rival and its three levels, the name gate,
  the hall of fame (the note names whatever host the feed points at), the GLIDE panel, hold-to-slide, the
  pills, and the self-update (nothing newer leaves it alone, a newer build comes over whole, a broken
  download refused, a swap lets go of the relays first, the feed tag must match the game's own)
- **Pindutan v17** — 48 · **labs pages** — 20 · **leaderboard PHP** — 23 · **leaderboard worker** — 19
- **Aurora v4** — 49 + 54: only FreeModel survives, proxy sends no key, CORS wall named, dead relay gets its
  own message, Test connection proves a chat request
- `api-free.php` and `api-v5.php` are parsed with a real PHP grammar (no PHP binary in the sandbox), and
  every page's CSS goes through PostCSS before it ships
