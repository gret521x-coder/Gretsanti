# Gretsanti

Personal launcher + group chat, built as plain HTML and PHP for XAMPP. No build step, no frameworks — just drop the files in `htdocs` and open them in a browser.

```
http://localhost/index.html          → launcher (original)
http://localhost/index-v2.html       → launcher (SpaceX theme)
http://localhost/index-v3.html       → launcher (Facebook dark mode)
http://localhost/index-v4.html       → launcher (simplified)
http://localhost/gc-v2.html          → group chat (v2)
```

---

## Files

### Launcher

| File | Size | Description |
|---|---|---|
| `index.html` | 30 KB | Original **KDL // MISSION CONTROL** — dark orbital theme, access-code lock screen, payload grid, OpenRouter key modal, Bardagulan gate |
| `index-v2.html` | 43 KB | **SpaceX / orbital redesign** — animated starfield, glass panels, live payload search, toast notifications, accessible modals |
| `index-v3.html` | 45 KB | **Facebook dark mode** — `#18191a` palette, top bar with search pill, sidebar menu, cover/profile card, feed-style payload cards |
| `index-v4.html` | 25 KB | **Simplified v1** — same layout as `index.html` without the API / SYNC / OpenRouter / add-payload UI |

All four launchers share the same logic: access-code unlock, MET clock, payload cards, per-card delete, `localStorage` persistence, and the locked **Bardagulan** channel. Each file is standalone — pick whichever you like and rename it to `index.html`.

Access codes and the Bardagulan signature are configured in the `VALID_PASSWORDS` / `BARDAGULAN_SIG` constants inside each file.

### Group chat

| File | Size | Description |
|---|---|---|
| `gc.html` | 43 KB | Original **Black Cat Group Chat** upload — kept unchanged as a reference |
| `gc-v2.html` | 61 KB | **Chat v2** — presence fix, lightning joins, new design |
| `api-v2.php` | 23 KB | Backend for `gc-v2.html` — storage, uploads, presence, GIF proxy |

Also on `main`: `Aurora.html` and `htdocs (1).zip` (a full copy of the `htdocs` folder, ~3.6 MB).

---

## Group chat v2

### Architecture

One global room, carried over three public MQTT brokers at once, with a PHP polling fallback so messages still arrive if every broker is unreachable.

| Layer | Role |
|---|---|
| MQTT (EMQX, HiveMQ, Mosquitto) | live delivery, cross-relay re-publishing, de-duplicated by packet `rid` |
| `api-v2.php` polling (3.5 s) | guaranteed fallback + message history |
| `sessionStorage` uid | one identity per browser tab, so two tabs behave as two people |

### Presence model

The original build left the room whenever the tab lost focus. That is fixed:

| Situation | Behaviour |
|---|---|
| Heartbeat | every **7 s** → online |
| Tab / app switched away | marked **away** after 22 s (dimmed avatar, amber dot) — **never** a leave |
| Silent for **80 s** | leaves once, with a system notice |
| Tab actually closed | leave sent via `sendBeacon` + MQTT |
| Back/forward cache | ignored (`event.persisted`) — you are not kicked out |

**Server-side confirmation:** if `api-v2.php?action=online` returns a list containing your own uid, the client trusts it and removes peers the server says are gone instead of waiting for the timeout. If the endpoint is missing, that check is silently skipped and the client falls back to heartbeats.

Host election prefers a genuinely online user over an away one, then the earliest joiner, then a stable uid order. Only the host sees the 🗑️ clear button, and only the host's `clear` packets are accepted.

### ⚡ Lightning joins

When someone new enters, everyone else sees a two-bolt strike, a violet flash, a brief screen shake, and a glowing **"⚡ Name joined the chat"** pill. Strikes are throttled to one per 1.2 s and are skipped entirely under `prefers-reduced-motion`.

### Design

Deep ink background with violet/cyan glows, frosted glass header, gradient wordmark, online counter, per-user avatars with online/away rings, `Today` / `Yesterday` day separators, gradient bubbles for your own messages, rounded sheet drawers, pill reaction popover, animated message entry, pinch-zoom enabled.

---

## `api-v2.php`

| Endpoint | Method | Purpose |
|---|---|---|
| `?action=health` | GET | version, PHP version, folder-writable check |
| `?action=load&since=<ms>` | GET | message history — incremental when `since` is supplied |
| `?action=send` | POST | store a message (idempotent by message id) |
| `?action=react` | POST | toggle a reaction for one uid |
| `?action=clear` | POST | wipe the log |
| `?action=upload` | POST | image/file upload, 8 MB max |
| `?action=gif&q=<query>` | GET | GIF proxy (works around CORS) |
| `?action=beat` | POST | presence heartbeat |
| `?action=leave` | POST | remove a user from presence |
| `?action=online` | GET | list of uids currently connected |

### Storage

Created automatically on the first request, next to `api-v2.php`:

```
data/messages.json     chat log (last 400 messages, server timestamps)
data/presence.json     who is online right now
data/rate.json         rate-limit counters
uploads/               uploaded files
```

Both folders get a `.htaccess` that blocks direct access, and `uploads/` has script execution disabled.

### Security notes

- Uploads are checked by **extension and real MIME type**, renamed to a random hex name, and capped at 8 MB — uploading a `.php` file is rejected.
- Per-IP rate limits (25 requests / 10 s; stricter for uploads and clears).
- All read-modify-write cycles run under `flock`, with atomic JSON writes and a Windows-safe `rename()` fallback.
- Message text, names and ids are length-capped and stripped of control characters.
- URLs from other users are restricted to `http(s)` or relative paths, so `javascript:` links are rejected.
- History is capped at 400 messages; the server assigns monotonic timestamps.

### Known limitations

- The three MQTT brokers are **public**, and the room topic is a fixed string. Anyone who knows the topic can read and post. Treat the room as semi-public, or derive the topic from a shared secret.
- The GIF search uses an unofficial keyless endpoint (`gifsnap.com`) and may stop working; the client then offers pasting a direct `.gif` / `gifer.com` link instead.
- Message ordering uses the client timestamp; the server timestamp is used for incremental sync.
- Presence is cooperative — a client could fake its own heartbeat.

---

## Running it

1. Install **XAMPP** and start **Apache**.
2. Copy the HTML files and `api-v2.php` into `C:\xampp\htdocs\`.
3. Open `http://localhost/index.html` (launcher) or `http://localhost/gc-v2.html` (chat).
4. The chat creates its `data/` and `uploads/` folders on first load.
5. To try presence and the join effect, open the chat in **two tabs** and join in the second one.

Requirements: PHP 7.4+ with JSON and `fileinfo` (both ship with XAMPP). `curl` is optional — the GIF proxy falls back to stream wrappers.

---

## Version history

| Version | Notes |
|---|---|
| `index.html` | original launcher |
| `index-v2.html` | SpaceX / orbital redesign |
| `index-v3.html` | Facebook dark mode |
| `index-v4.html` | v1 with the API, SYNC, OpenRouter and add-payload UI removed |
| `gc.html` | original group chat |
| `gc-v2.html` + `api-v2.php` | presence fix, lightning joins, new design, hardened backend |

## Development checks

- `node --check` on the extracted `<script>` block of every HTML file
- `api-v2.php` parsed with the `php-parser` package — no syntax errors
- All `getElementById` / `el()` targets verified to exist in the markup
- Presence logic unit-tested in Node: away ≠ left, a single leave notice, host prefers online users, server-confirmed disconnect
