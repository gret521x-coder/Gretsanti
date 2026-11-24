# Fortress — art folder

Every picture in `labs/fortress.html` lives here as a plain PNG, and the game itself
carries copies of them as data URIs so it stays a single file you can drop anywhere.

```
towers/    archery-1..3.png  barracks-1..3.png  mage-1..3.png  catapult-1..3.png
           _sheet.jpg  (the reference sheet they are cut from)
heroes/    warrior.png  archer.png    spear.png      (the little pixel hero on the road)
           gerald.png alleria.png malik.png bolin.png magnus.png ignus.png denas.png
           _sheet.jpg   (hero cards, cut by slice-heroes.js)
monsters/  skeleton.png orc.png
decor/     castle.png   house.png     tree1.png  tree2.png  tree3.png
ui/        gold.png     icon_warrior.png  icon_archer.png  icon_spear.png
```

## Changing a picture

1. Drop the new PNG in the right folder (same file name — the name *is* the key).
   Towers carry one picture per level: `archery-1.png`, `archery-2.png`, `archery-3.png`.
2. Run the build:

```
node labs/assets/fortress/build-sprites.js
```

It rewrites the `var SPR = {…}` block in `labs/fortress.html`. Nothing else to install:
it only needs Node.

## Re-cutting the hero cards

`towers/_sheet.jpg` and `heroes/_sheet.jpg` are reference sheets: the first gives the
twelve tower pictures, the second the seven hero portraits. Cut them again with:

```
npm i sharp
node labs/assets/fortress/slice-heroes.js
node labs/assets/fortress/build-sprites.js
```

## Re-cutting the tower sheet

The twelve tower pictures are produced from `towers/_sheet.jpg` (4 columns × 5 rows,
the first three rows are levels 1–3 in the order archery, mage, catapult, barracks):

```
npm i sharp                       # only needed for this step
node labs/assets/fortress/slice-towers.js
node labs/assets/fortress/build-sprites.js
```

The slicer knocks out the grass and dirt behind each tower, keeps the tower and its
nearby clutter, trims it and scales it to 150 px tall. A preview of all twelve lands
in `/tmp/fortress-towers-preview.png`.

## Notes

- Sprites are stored at the size the game draws them, so a 92 px monster stays crisp
  on a phone without extra scaling work.
- Keep them small — every byte ends up inside the HTML file, four thirds larger once it
  is base64.
- Licence and origin of each picture: see `CREDITS.md`.
