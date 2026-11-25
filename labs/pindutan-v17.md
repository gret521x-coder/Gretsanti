# Pindutan v17 — the complete spec

*Written 25 Nov 2026, against `labs/pindutan-v17.html` at commit `542953a`.*

## 1. The file itself

| | |
|---|---|
| Path | `labs/pindutan-v17.html` |
| Size | 123,269 bytes · **3,591 lines** · 117 functions |
| Build step | none — one HTML file, inline CSS and JS |
| External dependency | exactly one: `mqtt@5.10.1` from unpkg, jsdelivr as fallback (~150 KB) |
| Images | none on disk; every icon and avatar is drawn on a `<canvas>` at runtime |
| Entry | the game itself; `index.html` (Mission Control) and `labs/index.html` both link here |
| Preview | `/labs/pindutan-v17.html` |

Two screens and a gate: **lock screen → lobby sheet → game**. The lobby is a sheet that slides over the
game, not a separate page, so the board keeps running underneath while you sit in it.

## 2. The lock

SHA-256 of the pass phrase (base64'd in source so it isn't greppable), compared against what you type,
case-insensitive. On plain-http origins where `crypto.subtle` is missing (XAMPP over a LAN IP) it falls
back to a 64-bit FNV-style digest computed the same way on both sides, so the lock still opens.
Unlock is remembered for the tab in `sessionStorage` (`pindutan_unlocked`).

## 3. The look (v17's design pass)

- **Wordmark:** "PINDUTAN" drawn from coloured blocks — a hand-written 5×7 pixel font, 47 cells wide,
  colours cycling amber → orange → cyan → pink → green → violet, blocks with rounded corners, a 5 %
  inset, a gloss gradient and a drop shadow. Two canvases: 28 px tall in the header, 44 px in the lobby.
  Both carry `role="img"` and `aria-label="Pindutan"`.
- **Background:** dot grid over the near-black ink, plus a soft radial glow at the top.
- **Players as cards:** 2-column grid. Each card has a tetromino avatar picked deterministically from the
  player id (`I J L O S T Z`), the name, what they're doing ("playing solo · you", "vs CPU · live",
  "in the lobby"), a CHALLENGE pill, and a YOU tag on your own card. Empty lobby shows a hint instead.
- **Play cards:** 2×2 — SOLO (O piece), VS CPU (T), 1V1 (S), GROUP (I) — each with its own 44 px canvas
  icon; the active one lights amber.
- **Invite:** one wide amber pill, "⚔ INVITE EVERYONE TO A GROUP BATTLE".
- **Headers:** cyan relay pill (`RELAY 3/3`), lobby pill, version badge, pause, lock.

## 4. The board and the rules

- **Grid:** 10 × 20 (COLS/ROWS), cell size computed from the viewport; DPR-aware canvases.
- **Pieces:** the classic seven, 7-bag randomiser, one hold slot, three-piece preview, ghost piece
  (toggleable).
- **Lock delay:** 480 ms resting on the stack, resettable 15 times per piece (move-and-settle feel).
- **Gravity:** `max(60, 950 × 0.855^(level−1))` ms per row; level = `floor(lines/10) + 1`.
- **Scoring:** line clears 100 / 300 / 500 / 800 × level; T-spins pay their own table (100–1600 at full
  T-spin, mini pays less); back-to-back × 1.5 on quads and spins; combo +50 × level per chain link;
  perfect clear +1500 × level. Soft drop +1 per row, hard drop +2 per row.
- **Attack table:** `[0, 0, 1, 2, 4]` garbage rows for 1/2/3/4 lines; full T-spin `[0,2,4,6,0]`, mini
  `[0,1,2,4,0]`, perfect clear +6.
- **Garbage:** rows arrive at the bottom with a hole, 2-row burst pops from the bottom.
- **PINDUTAN burst:** a charge meter fills with every clear (`+9` per line, `+5` T-spin, `+18` perfect,
  capped at 100). At full charge, `E` (or the button) wipes your bottom two rows, fires +300 × level
  score, shakes the board and, in a room, cancels five incoming rows and sends five back.

## 5. The four ways to play

| mode | how it starts | who you face |
|---|---|---|
| **solo** | play card | nobody; the board just runs |
| **VS CPU** | play card + level | a bot with its own board, stack, clears and attacks |
| **1v1** | CHALLENGE pill on a player | one human |
| **group** | the amber invite pill | up to 7 humans (`MAX_PLAYERS 7`) |

**CPU levels** (`localStorage: pindutan_cpu`, default `intermediate`):

| level | drop ms | min drop | move ms | mistake rate | lookahead | attack bonus | clear bonus |
|---|---|---|---|---|---|---|---|
| beginner | 155 | 115 | 48 | 30 % | no | 0 | 45 |
| intermediate | 88 | 46 | 26 | 9 % | no | 0 | 60 |
| Kupal | 46 | 22 | 13 | 0.4 % | yes | +1 row | 70 |

Placement scoring weights: holes −24, aggregate height −0.62, bumpiness −0.52, tall columns −2.7.
The bot runs its own real engine (bag, hold, T-spins, garbage), so it tops out on its own.

**Auto-pause** applies to solo and VS CPU only (`autoPauseApplies()`), never to a live room. The three
toggles — Ghost, Auto-Pause, SFX — live in the settings row, ON by default; auto-pause is ON by default.

## 6. The lobby (one room, no codes)

- One fixed room: `LOBBY_CODE = "LOBBY"`. Nothing to type, ever. A stale saved code from older builds is
  ignored on load.
- **Four paths at once:** two browser tabs over `BroadcastChannel("pindutan_v17_lobby")`, plus three free
  public MQTT relays over WSS — EMQX, HiveMQ, Mosquitto — on topic `pindutan/v17/LOBBY`. Publishing is
  "first relay that takes it wins", subscribing happens on all three, so a broken broker is invisible.
- **Presence:** everyone announces itself every 4 s; a peer silent for 13 s is swept out.
- **Packets:** `HI` hello, `SYNC` state, `BYE` leave, `INVITE` (duel or group), `JOIN`, `START`, `ROSTER`
  (late arrivals in a group battle), `GARBAGE` attack, `B` board frame.
- Everyone picks their own mode while sitting in the lobby — the lobby is not a lobby you leave.
- **Group invite window** is 4.5 s, so everyone gets a chance to tap Join; the host starts the battle when
  it closes, or waits for the earliest joiner's window.

## 7. Opponent boards (came in through PR #12)

- In a 1v1 or group battle every player pushes its board as a 200-character string (`. `.` for empty,
  the piece letter for filled) every **220 ms**; outside a battle, once a second, so a spectator can watch
  a solo run. Frames are skipped when nothing changed.
- `encodeBoard` / `decodeBoard` accept the old array form too, so a mixed build still renders something.
- The opponents panel paints each rival's board into the opponents grid, top-out included, and is only
  wanted while spectating or in a battle (`opponentsWanted()`).
- Watching: the `▶ Watch` toggle hides your own board without leaving the lobby, and the layout switches
  to the spectator arrangement.

## 8. Controls

Keyboard: ← → move, ↑/X rotate, Z rotate back, ↓ soft drop, Space hard drop, C hold, E burst, P/Esc pause
(and the lobby).

Touch: a 4-column grid of 46 px buttons under the board — left, right, rotate, soft, hard, hold, burst,
pause. `touch-action: manipulation`, no zoom, `viewport-fit=cover`.

## 9. What is remembered between visits

`localStorage`: `pindutan_best` (high score), `pindutan_nick` (your name), `pindutan_cpu` (level).
`sessionStorage`: `pindutan_unlocked`. Everything else is per-session.

## 10. Tests

48 assertions in the jsdom harness (no headless Chromium available): engine fixtures (lock delay, T-spin
corners, burst popping two rows, garbage), the single-lobby behaviour, challenges, group start, presence
sweep at 13 s, watch mode, the relay-only path with `BroadcastChannel` deleted, plus the look — both
wordmarks drawn, four play cards each with a canvas icon, piece avatars, aria labels.

## 11. Known limits, on purpose

- The **mqtt.js CDN is still there**. Removing it (inlining the client) was approved but postponed until
  it has been played with real people.
- v17 is on **its own relay topic**, separate from v16 — the board frames are a v17-only packet. v16
  clients and v17 clients do not see each other.
- Deferred design work: HUD decluttering (one big score), board corner brackets and scanlines, bigger
  drop/rotate touch buttons, a game-over scoreboard card, garbage shake, `safe-area-inset-bottom`.
- No server, no accounts, no storage of anything on anyone's machine. The relays are public and
  unauthenticated, so anyone who knows the topic can watch — fine for friends, not for secrets.
