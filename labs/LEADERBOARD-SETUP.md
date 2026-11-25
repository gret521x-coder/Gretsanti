# Pindutan v18 — the leaderboard, and how to make it live

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
| `labs/pindutan-v18.html` | `pindutan.html` (or any name you like) | the game |
| `api-leaderboard.php` | `api-leaderboard.php`, same folder | the ladder |

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

The game points at that URL in one place. Search `labs/pindutan-v18.html` for:

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
- your own row still appears, marked *(you, offline)*.

So the ladder degrades to a personal scoreboard instead of breaking.

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
| Every result says "kept on this phone" | the PHP is not at that URL, or CORS is blocked by the host — open the URL by hand and check the JSON comes back |
| `bad signature` in the network tab | the secret in the PHP and the HTML differ |
| `stale report` | the phone's clock is more than 15 minutes out |
| Ladder loads but never changes | you are playing solo or VS CPU — only human battles report |
| Empty after playing a friend on the same phone | you are both reporting the same nickname; use two names |
