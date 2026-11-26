# How the leaderboard stores its data

Two copies of the truth, in two places:

| copy | where | who can see it | lifetime |
|---|---|---|---|
| **the ladder** | `data/pindutan-leaderboard.json` on `aidekupal.unaux.com` | everyone playing, through the endpoint | forever, until you delete it |
| **your own record** | `localStorage` on your phone | only that phone | until you clear the browser |

The game writes the first one through `api-leaderboard.php`; the second one is the fallback that keeps
working when the host is asleep.

---

## 1. The file on the host

Path: `data/pindutan-leaderboard.json`, next to `api-leaderboard.php`. It is created the first time a
result is saved — you never have to make it yourself.

This is a real capture, four players after six reports:

```json
{
  "updated": 1795617751,
  "players": {
    "ace": {
      "name": "Ace",
      "wins": 2,
      "losses": 1,
      "best": 4820,
      "streak": 0,
      "bestStreak": 2,
      "last": 1795617651,
      "duel":  { "wins": 1, "losses": 1, "best": 4820 },
      "group": { "wins": 1, "losses": 0, "best": 3980 }
    },
    "noval": {
      "name": "NovaL",
      "wins": 1,
      "losses": 0,
      "best": 5110,
      "streak": 1,
      "bestStreak": 1,
      "last": 1795617661,
      "duel":  { "wins": 1, "losses": 0, "best": 5110 },
      "group": { "wins": 0, "losses": 0, "best": 0 }
    }
  }
}
```

That whole file, with four players in it, was **759 bytes**.

**What each field means**

| field | meaning |
|---|---|
| `players` key (`ace`, `noval`) | the nickname, lowercased and stripped — the identity. `Ace` and `ace ` and `ACE` are the same person |
| `name` | the spelling as last typed, used for display |
| `wins` / `losses` | every finished human battle, all modes added together |
| `best` | the highest score you ever finished a battle with |
| `streak` | wins in a row right now, reset to 0 by a loss |
| `bestStreak` | the longest run ever, never reset |
| `last` | unix seconds of the last accepted report — also the duplicate guard |
| `duel` / `group` | the same three numbers split by mode, so the 1V1 and GROUP tabs can rank separately |
| `updated` (top level) | when anything last changed, shown as the "live" note |

A player with **zero wins is stored but not ranked** — you have to win a human battle to appear on the
board. Losses and best scores still accumulate quietly underneath.

### It is only ever whole-file writes

No append, no journal, no rows. Each report:

1. reads the whole file into memory,
2. changes one player's numbers,
3. writes the new JSON to a **temporary file** (`…json.a1b2c3d4.tmp`) and flushes it with an
   exclusive lock,
4. `rename()`s the temp file over the real one.

`rename()` is atomic on Linux, so a reader never sees half a file and a crash mid-write leaves the old
file intact. If two players finish at the same instant, one write wins the race and the other's change
is lost — the only real cost of a flat file, and it is a lost win, never a corrupted ladder.

### What is deliberately not in it

No passwords, no device ids, no IP addresses, no boards, no timestamps of individual battles beyond the
`last` one. IPs exist only in `data/pindutan-lb-rate.json`, which holds **one counter per IP for 60
seconds** and then forgets them — the file is pruned on every request.

### How big it gets

About **190 bytes per player**. The cap is 900 players (`MAX_PLAYERS`), so the file stops at roughly
**170 KB**. When the 901st player arrives, the weakest are dropped by wins, then by best score. The
whole file is read and written on each report, which is why that cap exists.

### If the file goes bad

`read_json()` treats a missing, empty, or unparsable file as `{"players": {}}` — the endpoint keeps
answering, the ladder just looks empty. Nothing is thrown away silently: the broken file is still on
disk for you to inspect, and the next successful report overwrites it.

If the host refuses to create `data/`, the endpoint answers `{"ok":false,"error":"storage
unavailable"}` with HTTP 500, and the game falls back to phone-only records without breaking.

---

## 2. The copy on the phone

Two keys in `localStorage`, both written by the page:

**`pindutan_lb_mine`** — your own results, so they survive a host outage:

```json
{ "duel": { "wins": 3, "losses": 1, "best": 5200 },
  "group": { "wins": 1, "losses": 2, "best": 3100 },
  "name": "Ace", "updated": 1795617900 }
```

Written **before** the network call, every time a battle ends, whether or not the host answers. This is
what draws your *(you, offline)* row.

**`pindutan_lb_cache`** — the last ladder the page actually saw, per tab:

```json
{ "all": [ { "name": "NovaL", "wins": 4, "best": 5110 }, … ],
  "duel": [ … ], "group": [ … ],
  "fetched": 1795617850 }
```

Written on every successful read. When a read fails, the card paints from this and the note says
*"Can't reach the host — showing the last copy, 21:44:10"*.

**No retry queue.** Results that fail to upload are kept in `pindutan_lb_mine` but are **not** re-sent
later — that would need the server to accept old timestamps, and the duplicate guard would be the first
thing to break. So a battle played in a tunnel counts on your phone and never reaches the ladder.

---

## 3. Reading it without the game

The endpoint is ordinary JSON over GET:

```bash
# the whole ladder
curl "https://aidekupal.unaux.com/api-leaderboard.php?action=top&mode=all&limit=25"

# just the 1v1 ladder, top 10
curl "https://aidekupal.unaux.com/api-leaderboard.php?action=top&mode=duel&limit=10"
```

Each row comes back with `rank`, `name`, `wins`, `losses`, `best`, `played`, `rate` (win %), `streak`,
`bestStreak`, `last`, and `crown` (true only for names in the PHP's `CREATOR_NAMES` list).

## 4. Editing or clearing it

- **Read it:** over FTP, open `data/pindutan-leaderboard.json` in any text editor.
- **Fix a row:** edit the numbers, keep the keys lowercase and the JSON valid. The PHP does not cache
  anything, so the next read shows your edit immediately.
- **Delete one player:** remove their whole object, including the trailing comma.
- **Wipe it:** `curl -X POST "https://aidekupal.unaux.com/api-leaderboard.php?action=reset" -d "key=<the secret>"`
  — or delete the file; it is recreated on the next report.
- **Back it up:** copy that one file. That is the entire ladder.

---

## 5. Limits worth remembering

| thing | limit | why |
|---|---|---|
| players stored | 900 | whole-file rewrite |
| name | 2–14 characters | fits the cards, stops essay-names |
| score | 0 – 5,000,000 | a real run tops out in the low hundreds of thousands |
| timestamp | ±15 minutes | blocks replaying old captured results |
| duplicates | one report per name per second | two reports with the same `ts` are ignored |
| rate limit | 30 reports, 120 reads per IP per minute | stops a script hammering the host |
| report contents | `duel` or `group` only | solo and VS CPU are refused by name |

Everything above is enforced **on the server**, not in the page — except the signature, which is only a
speed bump, since the shared secret travels inside the HTML.
