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

## Checks

- `node --check` on the extracted `<script>` block of every HTML file
- All `getElementById` / `el()` targets verified to exist in the markup
- Presence logic unit-tested in Node: away ≠ left, a single leave notice, host prefers online users
