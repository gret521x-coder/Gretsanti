# Gretsanti

Launcher, group chat, Aurora and Pindutan — plain HTML files, no build step, no framework.

**The live copies run on the PHP host.** Pages is not used: the files are uploaded to the host and opened
from there. The repository is public, which is what lets a hosted page read a build file from it.

| What | Address |
|---|---|
| Launcher | `https://aidekupal.unaux.com/index.html` |
| Aurora | `https://aidekupal.unaux.com/Aurora.html` |
| Group chat | `https://aidekupal.unaux.com/gc-v5.html` |
| Pindutan | `https://aidekupal.unaux.com/pindutan-run.html` |
| FreeModel relay | `https://aidekupal.unaux.com/api-free.php` |

## Files

| File | Size | What |
|---|---|---|
| `index.html` | 31 KB | Launcher (an upload of `index-v5.html`) — Pindutan opens a three-slot picker, slot 3 plays the newest build |
| `index-v5.html` | 54 KB | Launcher in use — motion, and cards that open the file name the host actually has |
| `gc-v5.html` | 139 KB | Group chat — KDL skin, lightning storm, sky of stars/satellites/rockets |
| `api-v5.php` | 43 KB | Backend for the chat: log, presence, uploads, single admin slot |
| `aurora-v4.html` | 118 KB | Aurora — FreeModel only, KDL skin |
| `api-free.php` | 10 KB | FreeModel relay — key typed once in a browser form, streamed answers |
| `worker-free.js` | 5 KB | Same relay as a free Cloudflare Worker, for a host that blocks cross-site calls |
| `aurora-v3.html` · `aurora-v2.html` · `Aurora.html` | 96 · 83 · 53 KB | Older Aurora builds, kept as fallback |
| `pindutan.html` | 25 KB | **The game hub — KDL** — the page the first runner opens: **the password gate**, then three chairs (Pindutan, Chess, one still waiting), what changed and a short about-this-page. Holds no game code, keeps itself current from `menuBuild`; its chairs load `feed.arena` and `feed.chess` |
| `labs/chess.html` | 91 KB | **Chess, game two** — complete rules (castling, en passant, promotion, mate, stalemate, fifty-move, threefold, dead positions), VS CPU at three levels, two players on one page, **an online lobby over three relays with tap-to-challenge, a coin toss for colours, chess clocks, rematch, PGN/FEN and a scoresheet you can tap back through**, six page themes, eight board palettes with a tint slider, three piece styles |
| `Screenshot.html` · `save.php` | 3 · 1 KB | Collectibles vault — password-gated image board |
| `index-v1…v4.html` · `gc`/`gc-v2…v4` · `api-v2…v4.php` | — | Archive, one design generation each |
| `agent-widget.js` · `api-ai.php` · `agent-demo.html` | 22 · 13 · 4 KB | Floating agent bubble any page can load |
| `diag.html` | 8 KB | Link diagnostic — says whether the PHP host answers, and why not |
| `pindutan-run.html` | 7 KB | **The first runner, frozen at the Nov 26 file** — no game code and no menu; reads the feed and opens `feed.html`, the hub. Left alone on purpose: every change since lives in the hub and the game |
| `labs/pindutan-v21.html` | 259 KB | Pindutan v23 — the game: the arena, its three chairs behind ☰ Games, and the build that keeps itself current. The gate opens straight on the nickname now |
| `labs/pindutan-v20.html` | 192 KB | Previous Pindutan build, kept as the tested copy |
| `labs/pindutan-build.json` | 1 KB | The build pointer — which Pindutan is newest, where the deck lives (`menu` / `menuBuild`), and the ladder's address |
| `labs/worker-leaderboard.js` | 14 KB | The ladder as a Cloudflare Worker, for when a PHP host is not wanted |
| `wrangler.jsonc` | 1 KB | Cloudflare build config — the worker, its entry file, and its KV box |
| `labs/index.html` | 12 KB | The experiment list |
| `labs/` | 7 KB + | Experiments: Castle Defense, Call Spider (one file), the transcript extension, the auto clicker APK |

## Latest changes

**Build numbers — v21 stops at nine.** A minor number is one digit, so a build after `v21.9` is `v22`, and the
ones after that are `v22.1`, `v22.2` … up to `v22.9`, then `v23`. The file the board lives in keeps its old
name, `labs/pindutan-v21.html`, so the feed and every saved copy keep working; only the tag on the page moves —
the badge reads `v22` and the line under the wordmark reads `v22.1`.

**Launcher — Pindutan opens a short list before the game.** PLD-02 no longer jumps into a fixed build. It
opens a picker with three rows — **1** and **2** sit there marked `TBD` (greyed, waiting for a game), and **3
Pindutan · our latest build** reads `labs/pindutan-build.json` on `main`, fetches the build that file names,
refuses a half download, and hands the whole page over to it once it is whole. So the launcher is a runner
too, and `pindutan-run.html` is left exactly as it was. No signal, and it says so, with **Try again** and the
hosted copy. Both launchers carry it, `index.html` and `index-v5.html`, and a saved grid moves with it —
`pindutan.html`, `pindutan-v18/20/21.html`, `labs/pindutan-v21.html`, the hosted copies and even
`pindutan-run.html` all open the list now. To fill an empty row, give it a `url` in `PINDUTAN_SLOTS`. The deck
at `pindutan.html` keeps its own three chairs for the runner and for anyone who opens it directly; nothing
here changes it. Also fixed in both launchers: the wording and upgrade tables were declared below the
"still unlocked" check, so a reload hit a dead zone inside `getApps()`, the `catch` swallowed it and the
player's saved grid was thrown away — they are declared above that check now.


**Pindutan runner — one small file, always the newest game.** `pindutan-run.html` holds no game at all: it
reads `labs/pindutan-build.json` on `main` and then loads the build that file names, so a copy kept on a phone
never goes stale. If the repo cannot be reached it says so, offers a retry and a link to the hosted copy.

**Runner → hub → game.** The first link is frozen; everything after it keeps itself current.

1. `pindutan-run.html` — the **first runner**: no game, no menu. Reads `labs/pindutan-build.json` and opens
   `feed.html`. It refuses anything under 20 KB that is not a whole page carrying the `SELF-UPDATE` marker, and
   if it cannot get one it says why, offers a retry and a link to the hosted copy. **This file is frozen**:
   it is byte-for-byte the version from Nov 26 and is not to be touched again — which is exactly why the hub
   below is 21 KB and still carries that marker.
2. `pindutan.html` — the **game hub (m5)**, wordmark **KDL**, 25 KB, no game code. It asks for **the
   password** first — that is where the lock lives now — and then shows three chairs: **Pindutan** opens the
   build the feed calls `arena`, **Chess** opens `chess`, and the third is wired and says it is not written
   yet. Under the chairs: **what changed** and a short **about this page**. The hub keeps *itself* current
   from `menuBuild`/`menu`, and it checks the feed on every open and on every tap of its strip.
3. `labs/pindutan-v21.html` — the **arena** (v23): the game. It carries the same three chairs behind a
   **☰ Games** pill in its header, and it updates itself from `arena` in the feed. Its gate opens **straight on
   the nickname**: the password step is gone, because it is asked one page earlier, in the hub.

`labs/chess.html` is game two: a complete chess app of its own, 91 KB, with its own build tag (`c3`) and its
own feed field, so it updates itself the same way the arena does.

**Chess — the whole game, not a demo.** The rules are the real ones: castling on both sides with every
restriction (rights, empty squares, no castling out of, through or into check), en passant, all four promotion
pieces, check, checkmate, stalemate, the fifty-move rule, threefold repetition and dead positions (K vs K, K+B,
K+N, bishops on one colour). The move generator is proved by perft — 20 / 400 / 8902 from the start position,
48 / 2039 from Kiwipete, 6 / 264 from the promotion position, 44 / 1486 from the tricky middle game — and the
scoresheet is written in real SAN, with disambiguation and `+` / `#`. Play the CPU or two players on one page.

**The three CPU levels are three different players.** Beginner looks one move ahead, does not follow captures
through and throws a move away one time in five; Intermediate looks two moves ahead, takes anything left
hanging, and still slips once in twelve; Advanced looks three moves ahead, follows every capture to the end and
never slips. The search is a White-positive alpha-beta with a quiescence pass, and the evaluation knows about
material, piece-square tables, a bishop pair and doubled pawns. The look is
yours: six page themes, eight board palettes with a tint slider that recolours both squares, and three piece
styles (classic, outline, neon). Everything you pick is remembered.

**Chess online — one lobby, no room codes.** The same three public brokers Pindutan uses (EMQX, HiveMQ,
Mosquitto) carry one lobby channel (`kdl/chess/v1/lobby`); a BroadcastChannel and a storage bus cover the tabs
on one device for a browser that blocks WebSockets or the relay library. Names appear in the lobby as players
arrive and drop off after twelve silent seconds. Tap **⚔** beside a name and a temporary room
(`kdl/chess/v1/m/<id>`) is opened for the two of you, with an **Accept / Decline** bar at the other end.

**The colours are tossed, not handed out.** Both players put a coin in; if the two coins agree the inviter has
White, if they disagree the other player does. Each side works the answer out from the same two numbers, so both
of you agree, and neither can steer it. A toss bar slides in and names the colours, and the board turns round
for whoever gets Black. A rematch tosses again, so the colours change hands.

**Clocks, if you want them.** 3+2, 5+0, 10+0, 15+10, or none at all. The increment is added as you move, the
clocks travel with every move so both sides agree on the time, and a flag that falls ends the game for both of
you — sent as a `FLAG` packet, not left to the loser to admit. The room stays open when a game ends so a
**rematch** is one tap away, and **PGN / FEN** are one more (both copyable). Tap any move on the scoresheet to
look at that position; the live game never moves while you look.

Moves travel as squares and are re-checked with the local rules before they are played, so a stale or bogus
packet cannot put an illegal piece on your board; resign, draw offers and rematches travel the same way, and a
player who goes quiet is named on the match bar rather than left hanging.

The feed names three addresses, one per link: `html` (the hub the runner opens), `arena` (the game the hub
opens, and the file the game updates itself from) and `menu`/`menuBuild` (the hub's own address and tag).
Pointing `html` at the game instead takes the runner straight to Pindutan and skips the hub — one field, no
upload.

**And the hub keeps itself current too.** On opening — and on a tap of its strip — the hub compares its own
tag with `menuBuild` in the feed (currently `m7`). If a newer hub is out it fetches it, checks it (`HUB-MENU` marker, `</html>`,
size, and that it really calls itself that build), then hands the page over; `sessionStorage` stops a hub that
was just swapped in from swapping again. The strip says which copy you are on in one line — `hub m4 · newest ·
arena v22.9` — turns cyan while it looks, amber when it cannot, and admits `offline` without losing the menu.

**Pindutan v22 — the page keeps itself current.** Open a copy and it reads one small file from this repo,
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
answer, and never read a refused probe as a missing file. The Pindutan card opens a three-row picker, and
row 3 runs the newest build from the repo. Relative cards point at the PHP host, so the
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
- **Pindutan v22** — 236: engine, glance rules, rooms over four paths, the lobby (created by KDL under the
  wordmark, the room line beside the word Lobby, the name given once at the gate and then carried only by
  your card — no box to change it, nothing repeating it beside Watch — a card that is a name and
  Playing/Available and nothing
  else — the ladder is the only place that keeps score), the table (your board on the left, your rival's
  beside it on the right at 72% of its width, a group battle or watching keeping the full-width strip
  underneath), the lobby chat (words
  ride the relay, never stored, markup shown as words, 80 characters, one message a second, floods turned
  away, five lines tall), the maker's God mode (a button beside Watch that opens inside the same tab a list of
  everyone else — Kick, Ban, and Unban; a kick empties that phone's room until it reloads, a ban is written on
  the maker's phone and only Unban undoes it, and a banned phone is simply not heard), the three-second countdown (a frozen board and a frozen CPU until GO, and for
  whoever is facing the maker in a 1v1 or a group battle one line above the numbers — “You are challenging
  the creator, Goodluck!”, which the maker never reads), the storm
  (lightning for the whole room — three jagged vector bolts, each eight points of zig-zag, drawn as SVG so
  they stay sharp on any screen — a four-second fanfare the moment the maker's key is taken, a maker-only
  ⚡ beside the relays that broadcasts, a second bolt inside 600 ms held back), eight themes that stick — the last two
  are skies: a launch pad whose three satellites and two ships fly over the page as thin line art with mission tags,
  on an arc and a climb rather than a straight line, dimming when nobody is playing, and a
  Christmas night whose snow falls over the whole page and is stopped by the table itself — the player card
  behind a row tap (wins, best, the win rate worked out from what was played, the run of wins you are on,
  last seen — no losses), the ladder (ten names, no more, a rank for each of them earned in 1v1 alone — bronze,
  silver, gold, diamond, three tiers of three stars and a fourth that steps you up, so a group win lifts nothing —
  your own row reading "(you)", and
  the note that speaks of Cloudflare when the board lives there), an update log that opens by itself the first time
  you reach the lobby on a new build — three days of notes, and "Do not show again" in the lower right corner, with a
  📜 Updates pill beside Themes for anyone who wants it back — and the page acting as its own runner: a build strip
  under the wordmark that names the copy it is running, looks for a newer one on every open and whenever the tab comes
  back, and offers `pindutan-run.html` when GitHub cannot be reached — the CPU rival and its three levels, the name gate,
  the hall of fame (the note names whatever host the feed points at), the GLIDE panel, hold-to-slide, the
  pills, and the self-update (nothing newer leaves it alone, a newer build comes over whole, a broken
  download refused, a swap lets go of the relays first, the feed tag must match the game's own)
- **Launcher picker** — the Pindutan card opens the list, rows 1–2 are TBD and unclickable, row 3 reads the
  feed, a half download is refused, an offline start offers retry and the hosted copy, and a saved grid from
  any old Pindutan target lands on the list
- **Pindutan v17** — 48 · **Chess** — 65 · **hub and runner** — 25 · **labs pages** — 20 · **leaderboard PHP** — 23 · **leaderboard worker** — 19
- **Aurora v4** — 49 + 54: only FreeModel survives, proxy sends no key, CORS wall named, dead relay gets its
  own message, Test connection proves a chat request
- `api-free.php` and `api-v5.php` are parsed with a real PHP grammar (no PHP binary in the sandbox), and
  every page's CSS goes through PostCSS before it ships
