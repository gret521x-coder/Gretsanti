# Pindutan v20 — the leaderboard, and how to make it live

Two new things in v18:

1. **A name gate.** The password screen is now two steps: the password, then a nickname. Give no
   name and you are thrown back to the password screen — nothing is unlocked, nothing is stored.
2. **A hall of fame.** Wins and best scores from **human battles only** — 1v1 and group battles.
   Solo runs and VS CPU never count. It lives in the lobby as a card, with ALL / 1V1 / GROUP tabs.

The ladder is stored on your own host, `aidekupal.unaux.com`, by one PHP file. Everything else about
the game is unchanged: the same engine, the same one-lobby network, the same touch controls.

---

## What you have to upload, once

Two files, same folder as the pages you already host there (`gc.html`, `api-v2.php`, `save.php`):

| file | where it goes | what it does |
|---|---|---|
| `labs/pindutan-v21.html` | `pindutan.html` (or any name you like) | the game |
| `api-leaderboard.php` | `api-leaderboard.php`, same folder | the ladder |

**And that is the last time you upload the game.** From v20.8 on it updates itself. When it opens it
reads a small file from this repo — `labs/pindutan-build.json` on the `main` branch — and if that
names a newer build it quietly opens that copy instead: same address, same saved nickname, same
crown, same ladder. Offline, or a half download, and it just plays the copy it already has.

A copy kept on your own phone's storage does the same thing: opened, it checks the repo, and if
there is something newer it writes that page over itself. With no signal it just plays the copy it
has, so the file still works offline.

To publish a new build (this is for whoever edits the repo):

1. put the new game on `main` as `labs/pindutan-v21.html`, with its `BUILD_TAG` bumped;
2. bump `build` in `labs/pindutan-build.json` to the same tag.

The line under the wordmark and the little `data-build` on the page both come from `BUILD_TAG`, so
they cannot drift apart. A hosted copy older than the tag on `main` will swap on its next open.

Keep the PHP's name exactly as it is, or edit `LEADERBOARD_URL` near the top of the game's script to
match. Everything else is optional.

1. Take **`api-leaderboard.php`** from this repo's root.
2. Upload it to the same folder on `aidekupal.unaux.com` that already serves your other pages
   (`api-v2.php`, `save.php`, `gc.html` sit there), so it answers at:

   ```
   https://aidekupal.unaux.com/api-leaderboard.php
   ```

3. Open that URL in a browser. You should see JSON:

   ```json
   {"ok":true,"mode":"all","updated":0,"players":0,"rows":[]}
   ```

That is the whole install. It creates its own `data/` folder on first use:

```
data/pindutan-leaderboard.json     the table
data/pindutan-lb-rate.json         per-IP rate limiting
```

The `data/` folder is guarded with an `.htaccess` that denies direct access, so the JSON cannot be
read from outside — only through the endpoint.

> If the host refuses to create folders, make a `data` folder next to the PHP yourself and `chmod`
> it 755 (or 775). Nothing else is needed — no MySQL, no composer, no extensions.

### Keeping the ladder file private

Short answer: **do not rely on a hidden file.** On Windows you can right-click `data` →
Properties → Hidden (or run `attrib +h data`), and it will vanish from Explorer — but Apache does not
care about file attributes. A hidden folder is still served at
`https://yoursite/data/pindutan-leaderboard.json` if the server lets it through. Hiding is tidiness,
not protection. The free host has no Properties dialog at all.

Three levels, worst to best:

1. **The guards that already ship with the PHP.** On first use it writes `data/.htaccess` (deny rules in
   both Apache 2.4 and 2.2 dialects) and an empty `data/index.html` so no directory listing appears.
   This works on most shared hosts as-is, and on XAMPP as long as `AllowOverride` is not `None` in
   `httpd.conf`.

2. **Check it, don't assume it.** Open `https://yoursite/data/pindutan-leaderboard.json` in a browser.
   You want **403 Forbidden**. If you get JSON, the guard is being ignored — go to level 3.

3. **Move the folder out of the web root.** One line at the top of `api-leaderboard.php`:

   ```php
   define('DATA_DIR', __DIR__ . '/../pindutan-data');
   ```

   On XAMPP that turns `htdocs/game/data` (reachable) into `htdocs/pindutan-data` (not reachable, so the
   browser gets 404 before Apache even looks). On a shared host, `__DIR__ . '/../../pindutan-data'`
   usually lands outside `public_html`. The PHP creates the folder itself; nothing else changes.

While a request is being served PHP reads the file directly from disk, so all three levels work
identically for the game — the difference is only who *else* can read it.

### Changing the address

The game points at that URL in one place. Search `labs/pindutan-v21.html` for:

```js
const LEADERBOARD_URL = "https://aidekupal.unaux.com/api-leaderboard.php";
```

Change it if you host it elsewhere.

### The shared secret

The file and the page sign every result with the same string:

```php
const LB_SECRET = 'kdl-pindutan-lb-2026-7f3a';
```

```js
const LB_KEY = atob("a2RsLXBpbmR1dGFuLWxiLTIwMjYtN2YzYQ==");   // the same string, base64'd
```

If you change one, change the other, or every result will be refused with `bad signature`.

**Be honest about what this is:** the secret ships inside the HTML, so it stops casual cheating and
stray curl spam, not a determined person. It is a scoreboard for friends, not a prize ladder.

---

## How a result travels

1. A 1v1 or group battle ends — you win, or you top out.
2. The page reports **your own side only**: `{name, mode: duel|group, win: 0|1, score, lines, ts, sig}`.
   Everyone reports their own result, so a 1v1 gives one win and one loss, not two wins.
3. The PHP checks the signature, the timestamp (±15 min), the name (2–14 characters), the score cap,
   and the per-IP rate limit. It then updates that name's row: wins, losses, best score, current streak.
4. The reply carries your new rank, and the game over screen adds a line like
   `Leaderboard  #4 of 23`.

The **same name on two phones is the same person** — the ladder is keyed by nickname (case-insensitive,
spaces collapsed). That is the point: type the same name and your wins add up.

### Rules the PHP enforces

| rule | value |
|---|---|
| name | 2–14 characters, letters/numbers/space/`.`/`_`/`-`, markup stripped |
| modes accepted | `duel`, `group` — anything else is refused |
| score | 0 – 5,000,000 |
| timestamp | must be within 15 minutes of the server clock |
| duplicates | a second report with the same or older timestamp is ignored |
| rate limit | 30 reports and 120 reads per minute per IP |
| table size | 900 players; when full the weakest are dropped |
| ranking | most wins first, then the higher best score, then the name |

## When the host is unreachable

The game never blocks on the network:

- the board keeps working, the battle still ends normally,
- your own results are kept in `localStorage` (`pindutan_lb_mine`),
- the card shows the last copy it saw and says so ("Can't reach the host — showing the last copy"),
- your own row still appears, marked *(you, offline)*,
- every result that did not go up is put in a queue on the phone (`pindutan_lb_pending`,
  the last 40) and sent the next time the host answers — opening the lobby or tapping
  ↻ both try it, with a fresh timestamp each time,
- the card says why in plain words ("1 result is waiting to go up — no internet right now").

So the ladder degrades to a personal scoreboard instead of breaking, and nothing is lost.

## Results the host never heard about

The phone is the only place a battle is written down, so it is the only place that can tell
something is missing up there. On every lobby visit (and on ↻) it reads what the host has for
each name this phone has played under, and sends only the difference: phone says 3 wins, host
says 1, two go up. Names are kept apart, so a friend playing on your phone does not collect
your wins and you do not collect theirs.

Nothing is invented: if the host does not answer, or the board is too long to see in full,
it sends nothing and tries again later. Each result also carries an id, so a retry after a
lost reply never counts the same battle twice.

## The ladder without a host at all

Every phone already keeps its own wins. The lobby relay (the same public MQTT brokers the room
uses) now also carries them, so the board can exist with **no server and no upload**:

- each phone stores its own rows on the phone (`pindutan_lb_mine`, split by nickname),
- every 15 seconds, and right after a battle, the phone sends what it knows — its own rows first,
  then what it heard from others, so a result keeps travelling to phones it never met,
- the receiving phone merges by name and mode, taking the larger number (wins and best only ever
  grow), clamping anything silly (99,999 wins becomes 9,999), and forgets a phone not seen in a day,
- the board draws host rows when the host answers and the phones' rows always, so the ladder works
  on a copy Chrome will not let talk to any server (a file opened from Downloads).

If any phone in that lobby can reach `api-leaderboard.php`, it also **carries the others up**: it
compares what it heard with what the host has and posts the difference (the message says "carried by
this one"). That is how a copy Chrome will not let talk to a server still reaches the server — its
wins travel to a phone that can.

What it cannot do: keep a result after every phone has closed the page for a day, or show a player
who never shared. The public brokers can read the topic, so treat this as a scoreboard for friends.

## Running the game straight from a file

Clicking `pindutan-v21.html` on the phone (Chrome shows `file:///...`) works for the ladder too,
with two wrinkles that are handled in the page:

- the report is sent as `text/plain`, which is a "simple" request. Browsers do not run a
  pre-flight check for those, and free hosts often never answer a pre-flight — this is the usual
  reason a local copy never gets its results up while the uploaded copy does;
- a file page can be refused permission to read the reply even when the write went through. If the
  plain attempt fails on the network the page sends the same report once more "blind" (no reply
  expected). Each report carries its id, so the host counts it once even if both arrived.

What a file page can still not do is *read* the board back — some browsers block that from
`file://`. The card then shows the phone's copy and says so. Open the uploaded page
(`aidekupal.unaux.com/pindutan.html`) to see everyone on the ladder.

## Changing the game later

Files live in the same folder on the host. When the game itself changes, upload
`pindutan.html` only. When the endpoint changes too (`api-leaderboard.php`), upload both —
a new page talking to an old PHP just falls back to the phone, it does not break.

## What it costs

Nothing beyond the page load. There is no polling: the ladder is read when you open the lobby, when
you switch tabs, and after a battle you took part in.

## Deleting the ladder

```bash
curl -X POST https://aidekupal.unaux.com/api-leaderboard.php?action=reset \
     -d 'key=kdl-pindutan-lb-2026-7f3a'
```

Or just delete `data/pindutan-leaderboard.json` over FTP.

---

## If something looks wrong

| symptom | cause |
|---|---|
| Every result says "kept on this phone" | the PHP is not at that URL, or the host blocked the write — open the URL by hand and check the JSON comes back |
| A local copy shows results but the board never moves | the page was opened from a file and the browser dropped the write: play from the uploaded page once and the queue goes up by itself |
| `bad signature` in the network tab | the secret in the PHP and the HTML differ |
| `stale report` | the phone's clock is more than 15 minutes out |
| Ladder loads but never changes | you are playing solo or VS CPU — only human battles report |
| Empty after playing a friend on the same phone | you are both reporting the same nickname; use two names |
| Two rows for one person | the nickname changed at some point; the old name keeps its wins, play on under one name and it climbs on its own |
| Two people share one phone | each name keeps its own wins (`pindutan_lb_mine` → `byName`), so both climb separately; playing the same name merges you on purpose |
