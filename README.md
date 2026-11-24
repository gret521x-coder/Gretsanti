# Gretsanti

Launcher + group chat for XAMPP. Plain HTML files, no build step.

## Files

| File | Size | Description |
|---|---|---|
| `index.html` | 30 KB | Original **KDL // MISSION CONTROL** |
| `index-v1.html` | 30 KB | Same as `index.html`, named v1 so the set reads v1 → v4 |
| `index-v2.html` | 43 KB | SpaceX / orbital redesign |
| `index-v3.html` | 45 KB | Facebook dark mode |
| `index-v4.html` | 25 KB | v1 without the API / SYNC / OpenRouter / add-payload UI |
| `gc.html` | 43 KB | Original **Black Cat Group Chat** upload, unchanged |
| `gc-v2.html` | 61 KB | Chat v2 — presence fix, lightning joins, new design |
| `api-v2.php` | 22 KB | Backend used by `gc-v2.html` |
| `gc-v3.html` | 82 KB | Chat v3 — replies, edit/delete, link previews, ticks, unread, paste & drop |
| `api-v3.php` | 32 KB | Backend used by `gc-v3.html` (v2 plus edit, delete, link preview) |
| `gc-v4.html` | 101 KB | Chat v4 — pinned banner, voice notes, end-to-end encrypted text |
| `api-v4.php` | 35 KB | Backend used by `gc-v4.html` (v3 plus voice notes and the banner) |
| `gc-v5.html` | 109 KB | Chat v5 — one secret admin owns the banner, message delete and chat clear |
| `api-v5.php` | 39 KB | Backend used by `gc-v5.html` (v4 plus the single admin slot) |
| `agent-widget.js` | 21 KB | Floating agent bubble any page can load with one `<script>` tag |
| `api-ai.php` | 11 KB | Key-holding proxy that streams the model's answer to the widget |
| `agent-demo.html` | 4 KB | Demo page for the widget (scripted replies, no PHP needed) |
| `Aurora.html` | 53 KB | Original Aurora AI chatbot |
| `aurora-v2.html` | 82 KB | Aurora v2 — stability fixes plus Phase 2 features |

---

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

## What changed in Aurora (`aurora-v2.html`)

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

## Version history

| Version | Notes |
|---|---|
| `index.html` | original launcher |
| `index-v1.html` | v1 alias of the original |
| `index-v2.html` | SpaceX / orbital redesign |
| `index-v3.html` | Facebook dark mode |
| `index-v4.html` | v1 with the API, SYNC, OpenRouter and add-payload UI removed |
| `gc.html` | original group chat |
| `gc-v2.html` | presence fix, lightning joins, new design |
| `gc-v3.html` | replies, edit/delete, link previews, delivery ticks + outbox, unread jump, paste & drop |
| `gc-v4.html` | pinned banner, voice notes, end-to-end encrypted text |
| `gc-v5.html` | one secret admin owns the banner, delete and clear-chat |
| `Aurora.html` | original AI chatbot |
| `aurora-v2.html` | consolidated file, stop/regenerate/edit, search, exports, prompt library, voice, highlighting |

## Checks

- `node --check` on the extracted `<script>` block of every HTML file
- All `getElementById` / `el()` targets verified to exist in the markup
- Presence logic unit-tested in Node: away ≠ left, a single leave notice, host prefers online users
- **Agent widget exercised in jsdom: 28 checks** — mount/dedupe, open/close/Escape, streaming into one bubble,
  request body and history, markdown rendering, inert HTML in replies, network and 401 errors with retry,
  Stop mid-stream, demo mode, sessionStorage restore, left-position option
- **Chat v5 exercised with four tabs on a shared fake broker and fake PHP: 44 checks** — admin claim,
  a second `++vip` join rejected with the normal-join escape hatch, member sees the admin, banner live then
  pulled by a late joiner then shown as unreadable-but-visible to a crypto-less reader, member has no delete,
  admin deletes someone else's message both ways, admin clears for both, handover on release, reload re-claim,
  join lightning on your own join, peer join strike + pill, calm glow under reduced motion
- **Chat v5 re-run against the v4 suite: 37 checks · against the v3 suite: 41 checks**
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
