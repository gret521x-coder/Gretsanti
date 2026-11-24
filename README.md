# Gretsanti

Launcher + group chat for XAMPP. Plain HTML files, no build step.

**Right now the live copies sit on the PHP host**, not on GitHub — the repository is private, and a private
repository stops publishing Pages on a free account.

| Page | Address |
|---|---|
| Launcher | `https://aidekupal.unaux.com/index.html` (`index-v5.html`) |
| Aurora | `https://aidekupal.unaux.com/Aurora.html` |
| Group chat | `https://aidekupal.unaux.com/gc-v5.html` (or `gc.html`) |
| FreeModel relay | `https://aidekupal.unaux.com/api-free.php` |

The launcher resolves relative cards against the PHP host and asks which name actually exists before it
opens one, so `Aurora.html` and `aurora-v4.html` both work without renaming anything on the server.

## Files

| File | Size | Description |
|---|---|---|
| `index.html` | 30 KB | Original **KDL // MISSION CONTROL** |
| `index-v1.html` | 30 KB | Same as `index.html`, named v1 so the set reads v1 → v4 |
| `index-v2.html` | 43 KB | SpaceX / orbital redesign |
| `index-v3.html` | 45 KB | Facebook dark mode |
| `index-v4.html` | 25 KB | v1 without the API / SYNC / OpenRouter / add-payload UI |
| `index-v5.html` | 54 KB | The launcher in use — motion (starfield, satellites, rockets, telemetry), pointer-lit cards, and cards that resolve `Aurora.html`/`gc-v5.html` names by asking the host |
| `gc.html` | 43 KB | Original **Black Cat Group Chat** upload, unchanged |
| `gc-v2.html` | 61 KB | Chat v2 — presence fix, lightning joins, new design |
| `api-v2.php` | 22 KB | Backend used by `gc-v2.html` |
| `gc-v3.html` | 82 KB | Chat v3 — replies, edit/delete, link previews, ticks, unread, paste & drop |
| `api-v3.php` | 32 KB | Backend used by `gc-v3.html` (v2 plus edit, delete, link preview) |
| `gc-v4.html` | 101 KB | Chat v4 — pinned banner, voice notes, end-to-end encrypted text |
| `api-v4.php` | 35 KB | Backend used by `gc-v4.html` (v3 plus voice notes and the banner) |
| `gc-v5.html` | 140 KB | Chat v5 in the KDL skin — secret admin, encrypted text, plus a sky of stars, satellites and rockets and a cyan lightning storm |
| `api-v5.php` | 41 KB | Backend used by `gc-v5.html` (v4 plus the single admin slot and CORS) |
| `agent-widget.js` | 21 KB | Floating agent bubble any page can load with one `<script>` tag |
| `api-ai.php` | 11 KB | Key-holding proxy that streams the model's answer to the widget |
| `labs/index.html` | 9 KB | Experimental build area — a card per experiment, empty state, nothing linked from the launcher |
| `labs/autoclicker/` | Android project | Auto clicker for Clash of Clans — Java, built into an APK by GitHub Actions |
| `labs/call-spider.html` | 31 KB | Call Spider as one file — paste a transcript, get notes; live capture through a copyable snippet |
| `labs/transcript-spider/` | Extension | Call Spider — harvests the live transcript on a page, floats a panel, summarizes the call |
| `.github/workflows/build-apk.yml` | 1 KB | Builds that APK on every change, artifact download from the Actions tab |
| `agent-demo.html` | 4 KB | Demo page for the widget (scripted replies, no PHP needed) |
| `diag.html` | 5 KB | Link diagnostic — says whether the PHP host answers, and why not |
| `Screenshot.html` | 3 KB | **Collectibles** vault — password-gated image board |
| `save.php` | 1 KB | Backend for Collectibles: stores, lists and serves the images |
| `tns.html` | 33 KB | **TNS — Safety Dispatch**, self-contained |
| `pindutan.html` | 42 KB | **Pindutan v14** arcade game, scores kept in `localStorage` |
| `Aurora.html` | 53 KB | Original Aurora AI chatbot (key left blank, asks at runtime) |
| `aurora-v2.html` | 82 KB | Aurora v2 — kept as the fallback; defaults to the free-token host / proxy |
| `aurora-v3.html` | 90 KB | Aurora v3 — the provider follows the key (Google, OpenRouter, Groq, OpenAI, custom), models discovered from the key |
| `aurora-v4.html` | 118 KB | **Aurora v4** — FreeModel only, KDL skin, key kept on the server, CORS explained instead of guessed |
| `api-free.php` | 10 KB | The FreeModel relay — key typed once in a browser form, streamed answers, model list |
| `worker-free.js` | 6 KB | The same relay as a free Cloudflare Worker, for a host that blocks cross-site calls |

---

## labs

`labs/` is where work in progress goes so the shipped pages stay untouched. It is reachable at
`/labs/index.html` and nothing on the launcher points at it.

- `labs/call-spider.html` — Call Spider as a single file: paste a transcript, press Summarise, get
  Summary / Decisions / Action items. Offline by default, OpenRouter when a key is saved. Live capture
  is a snippet the page copies for you, pasted into the transcript tab's console — no extension. The
  snippet's panel docks itself under the transcript box, captures everything that arrives, and can pop
  out into its own always-available window. The key decides the provider: `AIza…` talks to Google AI
  Studio, `sk-or-…` to OpenRouter, and **list models** asks the key what it can actually run instead of
  hardcoding model names that get retired.
- `labs/transcript-spider/` — the same spider as a Chrome extension, if Developer mode is not a bother.
- `labs/autoclicker/` — the Android project built into an APK by GitHub Actions.


Adding an experiment is one object in the `EXPERIMENTS` array in `labs/index.html`
plus a file next to it; `labs/README.md` has the details and the `git subtree split --prefix=labs` line
for moving the folder into a repository of its own.

---

## Launcher payload grid

Every launcher (`index.html`, `index-v1..v4.html`) opens the pages that ship today:

| Card | Opens |
|---|---|
| Bardagulan (locked) | `https://aidekupal.unaux.com/gc.html` — the copy next to the PHP |
| Collectibles | `https://aidekupal.unaux.com/Screenshot.html` — it needs `save.php` |
| Aurora | `aurora-v4.html` — was `Aurora.html`, then v2, then v3 |
| TNS, Pindutan | still local, they are pure static |

The chat and the vault point at the PHP host because that host refuses cross-site
requests outright (a plain `<script>` tag is refused too — `diag.html` proves it), so a
copy on Pages can never reach it. Opened from unaux, the chat finds `api-v5.php` beside
itself and everything works. The file on unaux is called `gc.html` (the same name your old chat had, so old
bookmarks now open the new one) — keep it in step with `gc-v5.html` in this repo.

Grids are saved in `localStorage`, so an existing one would have kept the old
filenames forever. `getApps()` now rewrites `gc.html` → `gc-v5.html` and
`Aurora.html` → `aurora-v2.html` on load and stores the result, so nothing has to be
reset. A corrupt saved grid falls back to the defaults instead of throwing.

All six cards now resolve: `tns.html`, `pindutan.html` and `Screenshot.html` are part
of the repo, so the launcher works from Pages as well as from the PHP host.

Two things about the payload pages:

- **Collectibles** reads and writes through `save.php`. On `*.github.io` it calls the
  copy on the PHP host instead, so upload the included `save.php` (it carries the CORS
  headers). The unlock password sits in the page source, and `save.php` itself has no
  auth check — on a public repo that password is readable by anyone. Good enough for a
  private board, not for anything you would mind losing.
- **Pindutan** hashes its code with `crypto.subtle`, which browsers only expose on
  `https://` or `localhost`. Over a plain-http LAN address the game now falls back to a
  local hash instead of failing to open.

## What changed in the launcher

### `index-v2.html` — SpaceX / orbital

- Animated canvas starfield, nebula glows, masked orbital grid
- Glass panels, rounded cards, gradient wordmark, hover glow states
- Sticky telemetry bar and mission banner with a pulsing `STATUS: GO`
- Live payload search
- Toast notifications replace `alert()` popups
- Accessible modals — Escape key and backdrop click close them
- Mobile reflow, pinch-zoom restored, `prefers-reduced-motion` respected

### `index-v3.html` — Facebook dark mode

- FB dark palette (`#18191a` background, `#242526` cards, `#2d88ff` accent)
- Top bar with logo, rounded search pill, nav circles and avatar
- Lock screen styled like the Facebook login card
- Left sidebar menu with counts, plus a telemetry card
- Cover/profile card with stats pills and action buttons
- Feed-style payload cards with avatar tiles, meta line, `Open →` footer and a `Private` badge

### `index-v4.html` — simplified v1

Same layout as `index.html`, with these removed:

- `SYNC` and `API` header buttons — `LOCK` and the MET clock stay
- `OpenRouter Console` payload card — Aurora renumbered to `PLD-05`
- API key modal and its JavaScript
- Deploy Payload card, deploy modal and the functions behind them
- The `LINK: NOMINAL` pill is now a static status light

Remaining payloads: TNS, Pindutan, Collectibles, Bardagulan 🔒, Aurora.

### `index-v5.html` — motion edition

Identical markup, logic, passwords and `localStorage` keys to `index.html`; everything below is
decoration layered on top, so the file can replace it one-for-one.

- **Starfield canvas** (`#field`) drifting behind whichever screen is showing
- **2–3 satellites** on slow elliptical orbits — solar panels, a body and a blinking green beacon;
  the far half of the orbit draws smaller and dimmer so they read as depth
- **Rockets** — one lifts off every 2 to 4 seconds from the bottom edge, a flickering cyan flame and a
  fading trail behind it; the screen never carries more than two at once
- **Radar sweep** rotating behind the `KDL // X` wordmark
- **Live telemetry strip** — SIGNAL, LATENCY, UPTIME and UPLINK tick on their own; uptime counts from
  the first unlock and is remembered in `kdl_mission_start`
- **Interactive cards** — the glow follows the pointer, cards tilt a few degrees and lift on hover,
  press scales them back, and they arrive in a short stagger
- **Boot sequence** — on unlock a black overlay types the mission-control boot lines and clears itself;
  tap anywhere to skip. It plays once per session
- A light sweep travels down the telemetry bar

Switch all of it off with `?still=1` in the URL, or let the OS do it: `prefers-reduced-motion: reduce`
removes the canvas (stars, satellites, rockets), radar, sweep, tilt, entrance animation and boot overlay. The dashboard is never
waiting on an animation.

All launchers keep the same behaviour: access-code unlock, MET clock, payload cards, per-card delete and `localStorage` persistence. Codes live in the `VALID_PASSWORDS` / `BARDAGULAN_SIG` constants inside each file.

---

## What changed in the chat — `gc-v2.html`

### Leaving on tab switch — fixed

The original build called `leaveRoom()` on `visibilitychange`, `pagehide` and `beforeunload`, so every tab or app switch announced a leave.

| Situation | Before | Now |
|---|---|---|
| Switch tab / app | "left the chat" notice | marked **away** only (dimmed avatar, amber dot) |
| Come back | join spam | instantly back online |
| Tab open in background | left after 24 s | stays online on the heartbeat |
| Close the tab | leave | leave sent once |
| Back/forward cache | kicked out | ignored |

Heartbeat every 7 s → online · 22 s silence → away · 80 s silence → one "left" notice. Host election now prefers a genuinely online user over an away one, and only the host's clear packets are accepted.

### ⚡ Lightning when someone joins

New joins (not returns from away) trigger a two-bolt strike, a violet flash, a short screen shake and a glowing "⚡ Name joined the chat" pill. Throttled to one strike per 1.2 s, skipped entirely under `prefers-reduced-motion`.

### Design

Deep ink background with violet/cyan glows, frosted glass header, gradient wordmark, online counter, per-user avatars with online/away rings, `Today` / `Yesterday` day separators, gradient bubbles for your own messages, rounded sheet drawers, pill reaction popover, animated message entry, pinch-zoom enabled.

### Other fixes

- `clear` packets accepted from the elected host only
- Links and images from other users limited to `http(s)` or relative paths
- PHP status dot no longer turns green on a failed response
- Dead always-true presence condition removed

## What changed in the chat — `gc-v3.html`

Everything from v2 is still there; these are the additions.

**Reply / quote**

- Long-press a bubble (or right-click it) → **Reply**; a bar above the composer shows who you are answering
- Your message carries a quoted stub with the original author and text
- Tap the quote to jump back to the original — it scrolls into view and flashes violet
- Cancel the reply with ✕; the quote is limited to a single line so bubbles stay small

**Edit and delete your own messages**

- The same menu offers **Copy**, **React**, **Edit** and **Delete** — edit/delete only appear on your own messages
- Editing opens an inline textarea inside the bubble; Save updates in place and adds a small `edited` badge
- Deleting replaces the bubble with a dashed "This message was deleted" tombstone — text, media and reactions are removed everywhere
- Both are broadcast over MQTT and written to the server, so other tabs update without a reload; only the author's own id can change a message, and an edit from anyone else is ignored

**Links**

- URLs in a message become real links (`target="_blank"`, `rel="noopener noreferrer nofollow"`)
- The first link in a message gets an OpenGraph preview card — title, domain and thumbnail, with a plain 🔗 fallback
- Previews are fetched server-side and cached for a week; private, loopback and LAN hosts are refused

**Delivery ticks**

| Tick | Meaning |
|---|---|
| 🕓 | sending |
| ✓ | published to at least one relay |
| ✓✓ | saved on the server too |
| ⚠ | nothing took it — tap to retry |

- Failed sends go into a local outbox in `localStorage`, so they survive a reload
- The outbox retries itself every 12 s and immediately when a relay reconnects

**Unread + jump to latest**

- Scroll up and new messages are counted instead of yanking you down
- A violet **↓ Latest** pill shows the count; tapping it jumps to the newest message and clears the counter
- The tab title gets a `(3)` prefix while you are away from the bottom, and clears when you return

**Paste and drag-drop**

- Paste an image from the clipboard and it uploads and sends straight away
- Drag a file anywhere onto the page: a dashed drop overlay appears and the file is sent on release
- File picker, paste and drop all use the same upload path, 8 MB limit kept

**Fixes found while building this**

- `sysNotice()` was called from three places but never defined — joining the room threw a `ReferenceError` and skipped presence/host election afterwards
- `safeUrl()` rejected the uploader's own `uploads/<name>` paths, so images and files were silently dropped from bubbles

## What changed in the chat — `gc-v4.html`

Everything from v3 is still there. v4 adds three things and changes no others.

**Pinned banner**

- A strip under the header for room-wide announcements — "Wi-Fi password is on the fridge" kind of thing
- The elected host writes it: tap the dashed pin strip, type, Save
- Everyone sees it; tapping the bar expands it from two lines to full text
- It is encrypted like any other message, so the server only stores ciphertext
- The host can edit or clear it; clearing removes it for everyone

**Voice notes**

- 🎤 in the footer starts recording; the strip shows a pulsing red dot and a running timer
- ➤ sends, 🗑 discards, and it stops itself at 2 minutes
- The note uploads as `weba` / `oga` / `m4a` and lands as a bubble with play/pause, a progress bar and a duration
- Starting one note pauses any other note that is playing
- Mic permission is asked each session; a blocked mic shows a toast instead of failing silently

**End-to-end encryption**

- Message text and the banner are sealed in the browser with **AES-GCM 256**, key derived with PBKDF2-SHA-256 (120k rounds) from a room secret inside the file
- What leaves the tab — to the MQTT brokers and to `api-v4.php` — is `v1.<iv>.<ciphertext>`; the server never holds readable text
- Incoming messages are decrypted before they render, in arrival order, so the log stays in sequence
- A message that cannot be opened shows a locked bubble instead of breaking the room
- Quotes store only the original's id, name and author — the text is looked up locally, so quotes never leak the message they quote
- Edits are re-sealed before they go out; deletes clear both the text and the ciphertext
- Without WebCrypto (plain `http://` on a LAN IP, old browser) the page falls back to plaintext and says so in a toast

**What this does and does not protect against**

| Protected | Not protected |
|---|---|
| Public MQTT brokers reading your messages | Someone who has this HTML file — the key is inside it |
| The PHP store, `data/messages.json`, backups | Media (images, files, voice notes) — those are still plain files in `uploads/` |
| The banner text sitting in `data/room.json` | Link previews — the URL is sent to `api-v4.php` to build the card |

**Compatibility**

- v4 reads v2/v3 messages normally; v2/v3 clients see v4 messages as empty bubbles, so everyone in the room should move to v4 together
- Changing `ROOM_SECRET` in the file makes a new private room — and makes every existing message unreadable

## What changed in the chat — `gc-v5.html`

v4 with one role added: a **single secret admin**. The host role no longer gates anything.

**Becoming the admin**

- In the lobby, type your name and append `++vip` — `Aria++vip` joins as `Aria`, holding the admin slot
- The token is stripped everywhere it is displayed; nothing in the UI hints that an admin exists
- There is exactly **one** slot: a second `++vip` join is **rejected** with *"Another secret admin is already in the room — connection rejected"* and a *Join as a normal member →* button
- The slot is a 45 s lease: the admin beats every 12 s, and it frees itself on leave, tab close or a crash
- A reload of the admin's own tab re-claims automatically (same tab, same id)
- The claim is stored in `data/admin.json`; MQTT `adminProbe` / `adminHere` keeps the rule working when the PHP API is down

**What the admin alone can do**

| Action | Member | Admin |
|---|---|---|
| Post / edit / clear the pinned banner | — | ✓ |
| Delete any message (tombstones) | — | ✓ |
| Clear the whole chat log | — | ✓ |
| Edit own message | ✓ | ✓ |
| Delete own message | — | ✓ |

Deleting your own message is admin-only too, as asked. Flip `ADMIN_ONLY_DELETE = false` at the top of the script if you ever want members to delete their own again. The server enforces the same rules: `clear`, `announce` and `del` are checked against the stored admin id, so a member cannot bypass the UI.

**Phone fixes**

- **Image / GIF viewer**: it had no visible way out. It now has a ✕ button (44 px, safe-area aware), an **open original ↗** link, closes on tapping the image, the backdrop or `Esc`, and locks background scrolling while open. It was also a plain `<div>` click handler — iOS Safari does not reliably fire those, which is why it felt stuck on a phone.
- **Actions under every bubble**: ☺ react · ↩ reply · ⋯ more, always visible on touch and on hover with a mouse. Long-press still works. Deleted messages get none.
- **Reactions never stuck.** `toggleReact()` applied the reaction, then `sendPacket()` replayed the same packet through `handle()` → `remoteReact()`, which toggled the *same user* off again. Net result: nothing. Self-echo is now ignored.

**Freeing a stuck admin slot**

- The lobby now shows the slot state: **free** (green), **busy — Admin: Aria** (amber), or **you hold it** (violet)
- If your tab still holds the lease, a **Step down** button releases it before you join
- A crashed tab no longer locks the room: on a rejected claim the client probes the room over MQTT, and if no admin answers, it asks the server to clear the slot — `freeadmin` only clears a lease silent for 25 s, so a live admin can never be kicked
- A live admin beats every 12 s, so 25 s can only mean gone

**Banner visibility — what was wrong**

- A member who opened the chat **after** the banner was posted depended on a clock comparison (`server ts > local ts`); on a device whose clock ran ahead the banner was silently skipped. It now compares the ciphertext itself.
- A device without WebCrypto (plain `http://` on a LAN IP) could not decrypt the banner and rendered **nothing at all**. It now shows the bar with *"Encrypted announcement — this device cannot read it"*, so it is never invisible.
- Both paths are covered by the new two-client tests: live MQTT delivery, a late joiner pulling it from `load`, and a crypto-less reader.

**⚡ Join lightning — what was wrong**

The strike was gated behind `prefers-reduced-motion`, and that media query didn't just skip the animation — it set `#fx{display:none}` and the script returned before doing anything. On any device with *reduce motion* turned on (Windows "Animation effects" off, a phone in battery saver, Chrome's reduce-motion flag) joining produced **no effect at all**, silently.

- Reduced motion now shows a **soft violet glow with no movement** instead of nothing
- The person joining gets the strike too, not just the people already in the room
- Both paths are covered by tests: full strike for a peer join, calm glow when `prefers-reduced-motion` matches

### Known limitations (both chat versions)

- The three MQTT brokers are public and the room topic is a fixed string — treat the room as semi-public
- GIF search uses an unofficial keyless endpoint and may stop working
- Presence is cooperative — a client could fake its own heartbeat

---

### The KDL skin and the sky (chat, latest)

The chat wears the same KDL // MISSION CONTROL skin as the launcher now: pure black deck, hairline grid
and cyan horizon, square corners, mono-caps labels, one cyan accent, green ok / amber wait / red stop.
The cat wallpaper and the violet gradients are gone; your own bubbles are cyan on a dark panel and the
quick-action chips read like terminal keys.

**A sky runs behind the messages.** The launcher's motion pack, moved into the chat: stars drifting down,
two or three satellites on slow elliptical orbits (solar panels, a green beacon blinking on the bus), and a
rocket crossing the deck every three to six seconds with a flickering flame and a fading trail. It is a
separate canvas at `z-index:0` under the app, dimmed to 62% so text stays readable, paused when the tab is
hidden, and switched off entirely under `prefers-reduced-motion`. `window.KDL_SKY` exposes the counts for
the tests.

**The ⚡ storm stays** — it was already in the chat — but its light is colder: bolts and flash in
`#dff6ff` with a cyan glow, still shaking the deck on arrival. It now also strikes when **you** send a
message, not only when one arrives.

---

## What changed in Aurora v4 (`aurora-v4.html`)

One platform: **FreeModel**, `https://freemodel.in/v1`, OpenAI-compatible, keys starting `fm_`.
Every other provider, host and model name is gone from the page.

**The key problem, stated plainly.** A key inside an HTML file is readable by anyone who opens the
source, and hashing is one-way — the browser could not un-hash it to send. Obfuscation is a curtain, not a
lock. So v4 ships both halves:

| Mode | Where the key is | Who can read it |
|---|---|---|
| **Proxy** (recommended) | `FREEMODEL_KEY` inside `api-free.php` on your PHP host | only you, on the server |
| **Key** | this browser's `localStorage` | this device only |

In proxy mode the page sends **no** `Authorization` header at all — there is nothing to steal from the
browser. `api-free.php` streams the answer through (SSE, no buffering), answers `?models=1` so "list
models" still works, refuses to run until the key is filled in, and only replies with CORS headers for
the origins listed at the top of the file.

**It looks like the launcher now.** Aurora wears the same KDL // MISSION CONTROL skin as
`index-v5.html`: pure black deck, hairline grid and cyan horizon, square corners, hairline borders,
mono-caps labels, one cyan accent (`#38bdf8`), green for ok (`#00e676`) and red for stop (`#ff3d00`).
The light/dark toggle is gone — the launcher has one theme, so Aurora does too.

**Green when gc is opened directly, red when it comes from the launcher** meant the launcher was falling
back to the *old* `gc.html` — the probe used `HEAD`, one free host refused the `HEAD`, and a refusal was read
as "file missing". The probe is a `GET` with a one-byte `Range` now: `404`/`410` mean missing, any other
answer means present, and no answer at all means stop asking. The chosen name is remembered in
`localStorage` (`kdl_pick_*`) so later taps open straight away — but only when the host actually voted, so a
failed probe can never stick.

**The launcher asks the host instead of assuming.** Each payload lists the names it might have
(`aurora-v4.html` or `Aurora.html`, `gc.html` or `gc-v5.html`); a tap sends one `HEAD` request and opens
the first name that answers, so nothing has to be renamed on the server. If the browser refuses the
probe (page opened from a download folder), the first name is used without asking.

**The launcher no longer depends on where it sits.** `index-v5.html` resolves every relative card
(`tns.html`, `pindutan.html`, `aurora-v4.html`) against `https://aidekupal.unaux.com/`, so the cards open
whether the file is opened from the PHP host, from a download folder, or from anywhere else — and a saved
`aurora-v2`/`aurora-v3` bookmark is repointed at `aurora-v4` on sight. The hardcoded resolutions have to be
declared *before* the first `renderApps()` call, or the first paint silently falls back to relative links.

**If the repository is made private**, GitHub Pages stops serving it on a free account — the
`github.io` link dies the moment the visibility flips. The relay and the page can live together on
unaux instead: upload `aurora-v4.html` next to `api-free.php`, open
`https://aidekupal.unaux.com/aurora-v4.html`, and set the relay address to
`https://aidekupal.unaux.com/api-free.php`. Both files are then on one host, so there is no CORS
conversation at all — `api-free.php` allows its own origin by default now.

**Setup is one form now.** Upload the file, open its address in a browser once, paste the key, press
Save — no code editing at all. The key is written to `.fmkey.php` beside it, a file that starts with
`<?php exit; ?>` so the web server runs it instead of printing it. Prefer editing? Fine, `FREEMODEL_KEY`
in the file still works and wins over the form.

Other changes: the old five-platform catalogue is dropped on load, `auto` is no longer offered (FreeModel
has no such model), the default is `deepseek-v4.1-flash`, keep-alive lines (`: ping`) no longer confuse the
stream parser, and `429`/`503` retry once then move to another model in the same catalogue.

### The `Failed to fetch` wall

**FreeModel does not let a page on another website call it.** The browser stops the request before it
leaves — the message is `Failed to fetch`, and it looks identical to a dead network or a bad key, which is
exactly how it was misread the first time. Nothing in a page can get around this: it is CORS, a browser
rule, and a page cannot step outside the browser. A relay is the only way, and the page now says so:

| What went wrong | What the page now says |
|---|---|
| FreeModel's own address pasted into **Proxy URL** | the box turns red, says `Failed to fetch` is coming, and names what belongs there |
| Key mode on `github.io` | "your browser was stopped before it reached FreeModel — this is CORS, your key is not the problem" |
| Relay address dead or blocking | "your relay did not answer", the exact address it called, and the free-host warning |

There is also a **🩺 Test connection** button in Settings. It checks both doors: the model list (a `GET`)
and then one real chat request (a `POST`, one word). Green means both answered and the address is saved;
red says which door failed. That second probe exists because the first round of this ended with a green
list, an empty box and a page still in direct mode.

Two smaller things came out of the same screenshot: a direct key-mode request now sends **only**
`Content-Type` and `Authorization` — a stray `X-Title` header makes the browser ask FreeModel's
permission for a header it never granted, and the refusal arrives as `Failed to fetch` with the key
never sent — and a green test now saves the address it proved instead of leaving it unsaved.

**Two relays ship.** `api-free.php` for a PHP host, and `worker-free.js` for a free Cloudflare Worker
(paste it in the dashboard, add the key as a `FREEMODEL_KEY` secret, deploy — no card, no domain). The
Worker one exists because several free PHP hosts, unaux included, refuse calls that come from another
website, which shows up as the same `Failed to fetch` even though the file is perfect.

---

## What changed in Aurora v3 (`aurora-v3.html`)

The old file sent every request either to `freetoken.website` (direct) or to the PHP proxy on the PHP
host (Pages), and both became unreliable. v3 decides from the key instead:

| Key starts with | Goes to | Streaming |
|---|---|---|
| `AIza…` or `AQ.…` | Google AI Studio (`generativelanguage.googleapis.com`) | `streamGenerateContent?alt=sse` |
| `sk-or-…` | OpenRouter | OpenAI-style SSE |
| `gsk_…` | Groq | OpenAI-style SSE |
| `sk-…` | OpenAI | OpenAI-style SSE |
| *custom base URL typed in Settings* | that host, unchanged | OpenAI-style SSE |

- **Busy models heal themselves.** Google answers `503 high demand` and `429 quota` under load. Neither is
  a bad request, so v3 retries the same model once after a moment, then moves to another Flash model the
  key can run — announced in the thinking panel. It never restarts after text has started arriving, and a
  genuine error (`400 API key not valid`) is shown at once instead of being retried.
- **Both Google key shapes work** — the older `AIza…` and the newer `AQ.…`. The key is sent as the
  `key` query value and as an `x-goog-api-key` header, so neither shape can fail quietly.
- **`list models`** asks the key what it can actually run and drops the answers into the model chips, so
  a retired model name can't strand you. Google's `auto` is resolved by asking for a Flash model.
- **A stale base URL is retired on load.** Anyone who saved `freetoken.website` or the PHP proxy keeps
  working — the saved value is dropped so the key decides again.
- Gemini's dialect is translated both ways: `system` becomes `systemInstruction`, the transcript becomes
  `contents`, and thinking-style deltas still stream into the panel.
- Errors name the provider and quote what it said, with a checklist that no longer mentions a base URL
  that isn't being used.
- The PHP proxy still works if you paste its URL — it is just no longer the default.

---

## What changed in Aurora v2 (`aurora-v2.html`)

The original file had two `</html>` tags, four stacked `<style>` blocks and five `<script>` blocks — with `callAPI`, `renderRich`, `renderModels` and `renderModelChips` each defined twice. v2 is one clean document: one stylesheet, one script, no duplicate definitions.

**Stability**

- Missing `scrollDown()` helper added (it was called but never defined)
- **Stop button** with `AbortController` and a 120 s timeout; partial replies are kept
- Context survives even with "Save history locally" off — the conversation lives in memory, storage is separate
- `saveChats()` survives quota errors: it drops the oldest chats, strips attachment payloads, and warns instead of failing silently
- Streaming render is throttled with `requestAnimationFrame` instead of re-parsing Markdown on every token
- `marked`, `DOMPurify` and `highlight.js` are all optional — the page still works if a CDN fails
- Links are hardened: `javascript:` / `data:` hrefs stripped, everything else gets `rel="noopener noreferrer nofollow"`
- Interface escapes single and double quotes now, not just `& < >`

**Features**

- **Regenerate**, **edit & resend**, and **delete** on individual messages
- **Chat search**, rename, pin, delete with confirmation
- **Export** the current chat as Markdown or JSON, or all chats as JSON
- **Prompt library** with six starting prompts, plus saving your own
- **Per-chat system prompt** and global custom instructions in Settings
- **Token counter** with automatic context trimming (9k-token budget)
- **Syntax highlighting** with a built-in token theme
- **Run** button for generated HTML — opens it in a sandboxed iframe
- 🎤 **Voice input** (Web Speech) and 🔊 **read aloud** on replies
- Token counter, prompt library and chat tools all work offline

Presence of the API key is unchanged: still hardcoded as requested.


---

## The agent widget (`agent-widget.js` + `api-ai.php`)

A chat agent that sits bottom-right on any page and answers from your model provider.

**Two lines on the page**

```html
<script>window.AGENT_CONFIG = { endpoint: 'api-ai.php' };</script>
<script src="agent-widget.js" defer></script>
```

**What the widget does**

- 56 px bubble, panel with header, transcript, composer; full-width on phones, left or right side
- Streams the answer token by token (SSE), with a Stop button, a 120 s ceiling and a retry bubble on failure
- Markdown-lite: `**bold**`, `` `code` ``, fenced blocks, clickable links — everything else escaped
- Transcript kept in `sessionStorage` for the tab, `Agent.reset()` to wipe it
- API: `Agent.open()`, `Agent.close()`, `Agent.toggle()`, `Agent.ask('…')`, `Agent.reset()`
- `demo: true` answers from a scripted stream so the UI can be tested without a backend

**Why the proxy**

The key lives in `api-ai.php`, never in the page. The proxy also

- rebuilds the system prompt server-side, so the browser cannot rewrite it
- rejects client-side `system` roles, caps the transcript at 24 messages / 4 000 chars each
- allows only models in `$ALLOWED_MODELS`
- rate-limits to 30 requests per IP per 10 minutes, stored in `data/agent-rate.json`
- streams straight through with curl, falls back to a single wrapped response without it
- scrubs its own key out of anything the provider echoes back
- answers `api-ai.php?health=1` with whether a key is configured

`$BASE_URL` defaults to OpenRouter; point it at any OpenAI-compatible endpoint (the Aurora key host, for example) and change `$MODEL` to match.

### Known limitations (both chat versions)

## Hosting the pages on GitHub Pages

The HTML is static, so GitHub can serve it. The PHP cannot run there — keep
`api-v5.php` on your normal host and let the page call it.

**1. Turn Pages on.** Settings → Pages → Source: *Deploy from a branch* → pick the
branch that has the files → folder `/ (root)` → Save. The site appears at
`https://<user>.github.io/<repo>/`. Pages needs a **public** repo on a free plan, or
Pro for a private one.

**2. Nothing to configure.** The config block just before the main script detects
`*.github.io` and points itself at the PHP host:

```js
window.KDL_API_BASE = 'https://aidekupal.unaux.com/api-v5.php';   // on Pages
window.KDL_API_BASE = '';                                         // everywhere else
```

Opened from XAMPP or from unaux itself, it uses the `api-v5.php` next to the page.
To move hosts, set the value by hand before the script runs. Relative `uploads/x.png`
paths are rewritten onto that host, so images, voice notes and GIFs keep working.

**Uploads failing on the Pages copy?** Tap the **🗄️ PHP API** row under the name field —
it runs the check and prints the answer ("PHP OK · v3 / cors" or "…refused this page
(TypeError: Failed to fetch)"). The same fact is visible in any browser:
`https://aidekupal.unaux.com/api-v5.php?action=health` → `"version":3,"build":"cors"`
means the new file is live; `"version":2` means the old one is.

That is the CORS headers on the PHP host. Upload
`api-v5.php` there and reload; the chat now says so out loud (it names the host and tells
you to upload the file) instead of showing a bare "Upload failed".

**Aurora needs a key too, and it has none.** On `*.github.io` it now posts to
`api-ai.php` instead of a provider, so the key lives on the server and the page stays
public-safe. `api-ai.php` accepts Aurora's own system prompt (capped at 3000 chars) and
always answers as a stream. Anywhere else Aurora still talks to its provider directly,
with the key from Settings.

**3. Upload the new `api-v5.php`.** It sends CORS headers for `*.github.io`,
`*.unaux.com`, `localhost` and `127.0.0.1` and answers the preflight `OPTIONS`
request — without it the browser blocks every call from Pages. Any other host goes
in `$CORS_HOSTS` at the top of the file; hosts not listed are refused.

`.nojekyll` is present so GitHub serves the files exactly as they are.

**What still will not work from Pages:** nothing in PHP, obviously — but also any
page that needs a key must carry it publicly. No key is stored in this repo:
`index-v*.html` and `api-ai.php` ship a `PASTE-YOUR-KEY-HERE` placeholder, and
`Aurora.html` / `aurora-v2.html` have `HARD_KEY` emptied, so both ask for the key at
runtime. Anything you paste into a page on a public repo is published to everyone —
keep keys on the server side (`api-ai.php`) when you can.

## Version history

| Version | Notes |
|---|---|
| `index.html` | original launcher |
| `index-v1.html` | v1 alias of the original |
| `index-v2.html` | SpaceX / orbital redesign |
| `index-v3.html` | Facebook dark mode |
| `index-v4.html` | v1 with the API, SYNC, OpenRouter and add-payload UI removed |
| `index-v5.html` | same launcher plus starfield, orbiting satellites, rockets, live telemetry, pointer-lit cards, a boot sequence (off with `?still=1`), and cards that resolve the real file name on the host before opening |
| `gc.html` | original group chat |
| `gc-v2.html` | presence fix, lightning joins, new design |
| `gc-v3.html` | replies, edit/delete, link previews, delivery ticks + outbox, unread jump, paste & drop |
| `gc-v4.html` | pinned banner, voice notes, end-to-end encrypted text |
| `gc-v5.html` | one secret admin owns the banner, delete and clear-chat; KDL skin, a sky of stars/satellites/rockets and a cyan lightning storm |
| `Aurora.html` | original AI chatbot |
| `aurora-v2.html` | consolidated file, stop/regenerate/edit, search, exports, prompt library, voice, highlighting |
| `aurora-v3.html` | provider auto-detected from the key, model discovery, no dead hosts, stale base URLs retired |
| `aurora-v4.html` | FreeModel only, key kept on the server via `api-free.php`, catalogue fetched from the platform |
| `worker-free.js` | the same relay for a free Cloudflare Worker, when the PHP host blocks other sites |

## Checks

- `node --check` on the extracted `<script>` block of every HTML file
- All `getElementById` / `el()` targets verified to exist in the markup
- Presence logic unit-tested in Node: away ≠ left, a single leave notice, host prefers online users
- **Agent widget exercised in jsdom: 28 checks** — mount/dedupe, open/close/Escape, streaming into one bubble,
  request body and history, markdown rendering, inert HTML in replies, network and 401 errors with retry,
  Stop mid-stream, demo mode, sessionStorage restore, left-position option
- **Chat v5 exercised with six tabs on a shared fake broker and fake PHP: 71 checks** — admin claim,
  a second `++vip` join rejected with the normal-join escape hatch, member sees the admin, banner live then
  pulled by a late joiner then shown as unreadable-but-visible to a crypto-less reader, member has no delete,
  admin deletes someone else's message both ways, admin clears for both, handover on release, reload re-claim,
  join lightning on your own join, peer join strike + pill, calm glow under reduced motion,
  lobby slot row (free / busy / yours), ghost slot reclaimed, live admin never kicked, step down,
  image viewer open/close/backdrop/Esc, per-bubble action row, reaction applied and removed
- **Chat v5 re-run against the v4 suite: 37 checks · against the v3 suite: 41 checks**
- **Chat skin and sky: 31 checks** — palette and square corners applied, mono caps, the cat wallpaper gone,
  the storm retuned but still firing on send and on arrival, plus a canvas that seeds stars and satellites,
  runs its loop, survives a missing canvas and disappears under reduced motion
- **Launcher: 70 checks** — every card resolves to the PHP host, `Aurora.html` is found when
  `aurora-v4.html` is missing, a refused probe is never mistaken for a missing file, and a chosen name is
  remembered for the next tap
- **Aurora v4 relay: 54 checks** — the CORS wall is named instead of guessed, a dead relay gets a different
  message, Test connection proves a real chat request, and a green test saves the address it proved
- **`diag.html`: 6 checks** — correct verdict for a working host, a headers-only block,
  an outright refusal and an HTTP error page
- **Chat failure messages: 14 checks** — a refused cross-origin call names the PHP host,
  says to upload `api-v5.php`, and warns once in the lobby; a real server error is shown
  verbatim; nothing is said when the host answers
- **Payload pages: 20 checks** — Collectibles locked until the password, listing and
  uploads routed to the PHP host from Pages and to the sibling `save.php` on the host,
  TNS and Pindutan load clean, Pindutan unlock with and without SubtleCrypto
- **Launcher grids: 60 checks** — all six launchers default to `gc.html` on the PHP host and
  `aurora-v3.html`, the grid renders, Bardagulan stays locked and its code still opens
  the right page, an old saved grid is upgraded and written back, corrupt storage
  falls back to defaults
- **Launcher motion (`index-v5.html`): 29 checks** — boots with no errors, telemetry ticks,
  the boot overlay plays on unlock and clears itself, two or three satellites orbit, rockets launch on
  their own and never exceed two on screen, cards arrive staggered and take the pointer position, the
  lock screen still covers the viewport, and `prefers-reduced-motion` / `?still=1` leave a plain
  working launcher
- **Aurora v4 / FreeModel: 36 checks** — no other platform string survives in the page, an `fm_live_` key
  posts to `freemodel.in/v1/chat/completions` with a bearer token and `stream:true`, a `.php` URL alone is
  enough to connect and sends **no** authorization header, `reasoning_content` reaches the thinking panel,
  `: keep-alive` lines do not break the parser, the catalogue is fetched from `/models` (or `?models=1`
  through the proxy), `429` retries then falls back, `401` is shown on the first hit, the old five-platform
  catalogue is dropped, and `api-free.php` streams, answers the model list, holds the key and never echoes it
- **Aurora v3 providers: 29 checks** — an `AIza` key reaches Google with `systemInstruction` and
  `contents` and no bearer header, `sk-or` reaches OpenRouter as `openrouter/auto`, a typed base URL
  wins over everything, `auto` resolves to a Flash model while embedding models are never chosen, a
  refused key quotes Google verbatim, no key means demo mode with zero network calls, the settings hint
  follows the key as it is typed, a saved `freetoken.website` base URL is retired on load, and an
  `AQ.…` key reaches Google with the header plus the query without breaking the `AIza…` path
- **Aurora through the proxy: 15 checks** — proxy picked on `github.io`, direct provider on
  localhost, no key and no `Authorization` header sent through the proxy, no
  `/chat/completions` appended to a `.php` base, system prompt and transcript in the body,
  SSE frames rendered even with streaming switched off, plain-text answer rendered
- **GitHub Pages mode: 9 checks** — sibling `api-v5.php` by default, absolute `KDL_API_BASE` used for
  every endpoint when set, relative uploads rewritten onto the PHP host, absolute image URLs untouched
- **Chat v4 exercised in a real DOM (jsdom): 37 checks** — WebCrypto key derivation, ciphertext on the wire
  and in the request body with no plaintext, local echo still readable, decryption of incoming text, locked
  bubble for a bad payload, encrypted edits both ways, banner write/decrypt/host-only buttons/clear/escaping,
  MediaRecorder stub through upload to a playable bubble, cancel discarding a note
- **Chat v4 re-run against the v3 suite: 41 checks** — the older features still behave when WebCrypto is
  missing and everything falls back to plaintext
- **Chat v3 exercised in a real DOM (jsdom): 41 checks** — boot, join, ticks through 🕓 → ✓ → ✓✓,
  reply quote and tap-to-jump, inline edit, tombstone delete, ignored edit from a non-author, unread
  counter and jump pill, failed send with outbox survival and retry, paste and drop uploads, HTML and
  `javascript:` payloads staying inert
- **Aurora v2 exercised in a real DOM (jsdom): 35 checks** — page boots without errors, one style/one script,
  message rendering, escaping, persistence, quota-overflow recovery, streamed replies, Stop, regenerate,
  prompt library, exports, theme and custom instructions
