# Fortress — TD overhaul notes

**Build `f3` · `labs/fortress.html` (one page, one script) · art root `TD/`**

Everything below is either *done in the repo* or *handed to you with the exact step to finish it*.
The rule for this build was: the game must never break because an image is missing.

---

## 1. What was shipped

### The `TD/` tree

```
TD/
├── images/
│   ├── towers/     .gitkeep  (9 PNGs to drop in — see §4)
│   ├── troops/     .gitkeep  (3)
│   ├── monsters/   .gitkeep  (18)
│   ├── heroes/     .gitkeep  (2)
│   └── ui/         .gitkeep  (4)
├── resources/
│   └── assets.json           ← the manifest: every key → its exact path
├── assets/         .gitkeep  (future: atlases, tilesets, fonts)
└── audio/          .gitkeep  (future: sfx)
```

Every value in `assets.json` is relative to `TD/` (`images/towers/archer.png`, not `TD/images/...`).

### The preloader (in the game)

* `#boot` covers the page: crest, **progress bar**, `caching sprites n/36`, and three of the game's
  own sprites bouncing underneath — the first screen already looks like the game.
* Load order: `../TD/resources/assets.json` → `main` on GitHub → the manifest **written into the
  page** if both are unreachable. It never blocks on the network.
* Each key gets an `Image()`. On `load` the PNG replaces the drawn sprite. On `error` the drawn
  sprite stays. A **2.6 s cap** opens the game regardless.
* `paintHeroBar()` runs on boot, so the HUD is alive before the first frame.

### Procedural fallback art (this is why nothing can crash)

* **Every** monster and hero already has a hand-made character grid (`SPR`) — the same art the game
  shipped with — so a missing PNG is invisible to the player.
* Towers are drawn procedurally (`drawTowerSprite`) at 1:1; a PNG in `TD/images/towers/` overrides it
  per type when one lands.
* `KDL_FORTRESS.spriteMode(key)` reports `png` or `drawn`, so you can see what actually loaded.

### Five new archetypes (fully playable, in the wave table)

| key | name | hp | spd | gold | lives lost | traits |
|---|---|---|---|---|---|---|
| `sapper` | Goblin Sapper | 16 | 2.9 | 12 | 1 | `swarm` — fills the choke early (wave 3+) |
| `vanguard` | Shielded Vanguard | 240 | 0.7 | 55 | 2 | `shield` — physical damage ×0.35, **magic full** (wave 9+) |
| `ram` | Siege Ram | 560 | 0.36 | 120 | **6** | `armor:0.5`, `siege` — steel halved, **explosives/magic full** (wave 11+) |
| `shade` | Dark Necromancer | 130 | 0.7 | 70 | 1 | `heal` + `raise` + `ward` (all damage ×0.88) (wave 13+) |
| `harpy` | Sky Harpy | 75 | 2.3 | 30 | 2 | `fly` + `air` — only anti-air towers reach her (wave 8+) |

Damage model added in `applyHit(e, dmg, {magic, boom, flank})`:
`shield` → ×0.35 unless magic/flanking · `armor` → ×(1−armor) unless magic/boom · `ward` → ×0.88.
`Frost`, `Poison`, `Tesla`, `Laser`, `Sniper` are **magic**; `Cannon` and `Mortar` are **boom**.

### The Royal Knight (playable)

* **Click-to-move:** tap the knight (dashed ring appears) → tap anywhere on the board → he walks
  there. Anything that reaches him inside 38 px **stops and fights him** instead of walking past.
  Flyers are not blocked and cannot be hit by his sword — that is what the towers are for.
* **Royal Aura (passive):** every tower within **150 px** of him shows a gold ring and fires
  **18 % faster** (`t.aura`). He also gains a guard bonus when he holds ground.
* **Shield Bash (active):** the HUD button. Radius **112 px**, **46** magic damage, **96-frame stun**,
  particle shockwave + screen shake, **720-frame (12 s)** cooldown, drawn as a filling bar on the
  button and as a countdown label. He revives 7 s after going down.
* HUD: portrait drawn from his own grid, health bar, state line, skill button.
* Hook for tests: `KDL_FORTRESS.hero()`, `heroSelect()`, `heroMoveTo()`, `heroBash()`, `heroReset()`,
  `brush(x,y)`, `auraAt(x,y)`.

### Game juice

* Drop shadows under every unit, projectile and the knight.
* Idle breathing: monsters bob on `sin(t)`; flyers get a wider, slower arc.
* Muzzle flashes on every tower that fires, with a short white bloom along its facing angle.
* Projectile **trails** (last 7 positions), faded from tail to head.
* **Particles** on every death (`burst`), on armour hits, and on every stun.
* **Floating numbers**: damage in ivory, magic in blue, fire in orange, gold in yellow — they rise,
  scale and fade; outlined so they read over any tile.
* Range rings on hover and while placing (dashed + 10 % fill), tower aura ring, tile highlight.
* **Wave preview** under the HUD: `NEXT — WAVE 7: 4× Orc Grunt · 3× Orc Shaman …`.
* Boot screen, responsive CSS HUD (`clamp()` title, wrapping bars, 720 px canvas keeps its aspect).

---

## 2. How the game finds the files (the CORS / broken-path answer)

```js
var ASSET_BASES = ['../TD/', 'https://raw.githubusercontent.com/gret521x-coder/Gretsanti/main/TD/'];
```

* Served from `labs/fortress.html` → `../TD/` = `/TD/` ✔
* Written into the arena by `document.write` (the URL stays `labs/pindutan-v21.html`) → `../TD/` ✔
* Served from any hosted copy one folder up → the `main` URL ✔
* `file://`, offline, or a host that refuses the manifest → the manifest baked into the page ✔

Images are same-origin or `raw.githubusercontent.com` (which sends `Access-Control-Allow-Origin: *`),
so `new Image()` can paint them into the canvas without tainting it.

---

## 3. Tests

```bash
node /tmp/phplint/test_fortress.js      # 97 passed, 0 failed
```

New coverage: the `TD/` tree exists · the manifest is valid JSON · it maps all 9 towers, all 18
monsters, the knight + portrait · every path is `images/<group>/<key>.png` · the preloader knows 36
keys · missing PNGs report `drawn` · every sprite grid is a rectangle with defined colours · the five
archetypes and their damage maths (`probe`) · their wave hooks · the knight's spawn, selection,
walking, blocking, aura, bash + cooldown + revive · the boot screen, portrait, wave preview and a
full paint pass.

---

## 4. What still needs a human (image generation)

Nothing here is required for the game to run — the drawn art is the current look. Dropping the files
in is the upgrade.

### Shared prompt primer (prepend to every prompt)

> 2D game sprite, top-down 2.5D perspective, single character, centred, full body, crisp pixel-art
> edges, cohesive palette of forest green / iron grey / burnished gold / blood red, strong dark
> outline, transparent background, no text, no drop shadow, no ground, 512×512 PNG, the subject
> fills ~85 % of the frame.

### Monsters → `TD/images/monsters/`

| file | prompt tail |
|---|---|
| `orc_grunt.png` | squat green orc grunt, leather belt, chipped cleaver, tusks |
| `orc_berserker.png` | lean blue-skinned orc berserker, twin axes, warpaint, snarling |
| `orc_brute.png` | hulking red orc brute, horned iron pauldrons, huge club |
| `orc_shaman.png` | hunched purple orc shaman, bone fetishes, glowing green staff |
| `orc_necromancer.png` | black-robed orc necromancer, skull mask, violet soul-fire |
| `goblin_runner.png` | tiny lime goblin runner, sack, sprint pose, wide eyes |
| `stone_ogre.png` | stone-grey ogre, rock growths on shoulders, tiny head |
| `skeleton.png` | bare skeleton warrior, red eye sockets, cracked shield |
| **`goblin_sapper.png`** | goblin sapper, strap of black powder kegs, lit fuse, goggles |
| **`shielded_vanguard.png`** | armoured vanguard behind a tower shield, gold boss, blue tabard, spear |
| **`siege_ram.png`** | wooden battering ram cart, iron-capped head, six wheels, iron bands, orcs pushing |
| **`dark_necromancer.png`** | hooded dark necromancer, two violet eyes, bone staff, ragged robes |
| **`sky_harpy.png`** | harpy, copper wings half-spread, talons, shrieking, wind-torn feathers |
| `orc_warlord.png` | orc warlord boss, crown of iron, two-handed cleaver, cape |
| `cave_bat.png` | cave bat, wings up, red eyes, tiny fangs |
| `gargoyle.png` | stone gargoyle, spread wings, glowing amber eyes |
| `wyvern.png` | green wyvern, long neck, gold belly, leathery wings |
| `sky_warlord.png` | armoured sky warlord on a wyvern, banner on the back |

### Towers → `TD/images/towers/` (512×512, same primer, plus "seen from a 3/4 top-down angle, base centred")

`archer.png` — wooden watchtower, green banner, archer at the parapet ·
`cannon.png` — squat stone bunker, iron barrel aimed right, rivets ·
`frost.png` — blue crystal spire, frost mist, icicles ·
`sniper.png` — tall dark tower, long rifle barrel, brass scope ·
`poison.png` — mossy cauldron tower, green vats, dripping nozzles ·
`tesla.png` — brass coil tower, blue arcs, ceramic insulators ·
`laser.png` — red ruby focus, iron mount, heat fins ·
`mortar.png` — sunken steel tube at 45°, sandbags, shells ·
`bank.png` — gold vault tower, coin stack, strongbox door.

### Hero → `TD/images/heroes/`

* `royal_knight.png` (768×768): *"ornate silver-and-gold plate armour, gleaming broadsword in the
  right hand, heraldic heater shield with a crimson cross on the left arm, flowing crimson royal
  cape, heroic stance, tiny blue visor glow"* — **the design shipped in canvas is this, pixel by pixel:
  plume, steel helm, blue eyes, gold breastplate with a white cross, red cape, gold shield, steel sword.**
* `royal_knight_portrait.png` (512×512): head-and-shoulders crop of the same knight, for the HUD.

### UI → `TD/images/ui/`

`crest.png` (fortress gate + shield), `shield_bash.png` (gold shockwave sigil), `range_ring.png`
(dashed gold ring, 512×512, transparent centre), `coin.png` (16×16 gold coin, for the HUD).

### Troops → `TD/images/troops/`

`militia.png`, `sentry.png`, `knight_guard.png` — reserved for friendly soldiers on the road (next
build); the keys are already in the manifest so nothing has to be renamed later.

---

### Installing a picture (build f4)

Any of the 36 slots takes a PNG now — not just monsters. Towers, the knight on the board and the
HUD portrait all ask for their picture first and fall back to the character grid if it is missing,
so art can land one file at a time and nothing ever breaks.

```bash
node TD/tools/install-sprite.js --list                       # every key and its path
node TD/tools/install-sprite.js mine.png monsters:grunt      # checks, then installs
node TD/tools/install-sprite.js knight.png heroes:royal_knight
```

It reads the PNG header itself and refuses, with the reason:

- a key that is not in the manifest (nothing would ever load it)
- a file that is not a PNG
- **no alpha channel** — it would paint its background over the map
- **the wrong shape** — the game stretches the picture into the box its grid fills, so a 1:1 image
  in a 10:11 slot comes out fat. `TD/resources/sprite-boxes.json` holds every box; the tolerance is
  2.5 %. `--force` overrides it.
- over 200 KB (36 of those would blow the 2.6 s preloader)

Nothing else needs editing: the manifest already names the path. Commit the file and the hosted copy
picks it up from `main` — `KDL_FORTRESS.pngReady('monsters:grunt')` says whether it arrived.

---

## 5. If you want to go further (not done here)

1. **Multi-frame PNG animation.** `drawMonster` takes one image; to animate, add
   `{"frames": ["...1.png", "...2.png"]}` to a manifest entry and step through them with `e.frame`.
2. **Hero skill #2 (Rally).** The knight's radius already computes nearby towers; a second button that
   widens `AURA_R` for 5 s and adds `+2 dmg` to those towers is a ~15-line change in `stepHero`.
3. **Friendly troops.** The three `troops` keys can spawn from a Barracks tower; blocking logic already
   exists (`hold` in `stepEnemies`) and would be reused.
4. **Balance pass on the ram.** 560 HP / 6 lives at wave 11 is deliberately brutal — if it feels unfair,
   lower `dmg` to 4 or move the hook to wave 13 in `waveList()`.
5. **Audio.** `TD/audio/` is scaffolded; the boot screen is the natural place to unlock an `AudioContext`.
