# labs

Experimental build area. Work in progress lives here so the shipped pages stay exactly as they are.

Nothing in this folder is part of the launcher until it graduates — it is reachable at
`/labs/index.html`, but no card on the launcher points here.

Pindutan v17 is the only current game build. v15, v16, Fortress, Castle Defense and the Fortress art are in `../previous/`.

## What is in here

| File | What it is |
|---|---|
| `index.html` | The labs landing page — a card per experiment, an empty state when there are none |
| `README.md` | This file |

## Adding an experiment

1. Drop the file in this folder, next to `index.html` (e.g. `labs/hover-test.html`).
2. Append one object to the `EXPERIMENTS` array at the top of the script in `labs/index.html`:

```js
{
    title: 'Hover test',
    file: 'hover-test.html',
    status: 'wip',                 // 'live' | 'wip' | 'idea'
    note: 'Probes what a pointer can do without a canvas.'
}
```

Leave `file` out while it is still on paper — the card renders without a link.

## Rules of the room

- Plain HTML/CSS/JS, no build step, same as the rest of the repo.
- Each experiment is self-contained: its own `<style>`, its own `<script>`, no shared file to break.
- `prefers-reduced-motion` is honoured in anything that moves.
- Nothing here imports from the launcher or the chat. Copy what you need instead.

## Graduating an experiment

Promote it out of `labs/` when it is decided, keep the original file untouched as a fallback in the
same way `index.html` sits behind `previous/index-v5.html`, and add it to the README table.

## Moving this folder into its own repo

The history comes with it:

```
git subtree split --prefix=labs -b labs-only
```

Then push `labs-only` to the new repository as its `main`.
