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
| `api-v2.php` | 23 KB | Backend used by `gc-v2.html` |
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

## What changed in the chat

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

### Known limitations

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
| `Aurora.html` | original AI chatbot |
| `aurora-v2.html` | consolidated file, stop/regenerate/edit, search, exports, prompt library, voice, highlighting |

## Checks

- `node --check` on the extracted `<script>` block of every HTML file
- All `getElementById` / `el()` targets verified to exist in the markup
- Presence logic unit-tested in Node: away ≠ left, a single leave notice, host prefers online users
- **Aurora v2 exercised in a real DOM (jsdom): 35 checks** — page boots without errors, one style/one script,
  message rendering, escaping, persistence, quota-overflow recovery, streamed replies, Stop, regenerate,
  prompt library, exports, theme and custom instructions
