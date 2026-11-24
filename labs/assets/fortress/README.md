# Fortress — art folder

Every picture in `labs/fortress.html` lives here as a plain PNG, and the game itself
carries copies of them as data URIs so it stays a single file you can drop anywhere.

```
towers/    archery.png  barracks.png  mage.png  catapult.png
heroes/    warrior.png  archer.png    spear.png
monsters/  skeleton.png orc.png
decor/     castle.png   house.png     tree1.png  tree2.png  tree3.png
ui/        gold.png     icon_warrior.png  icon_archer.png  icon_spear.png
```

## Changing a picture

1. Drop the new PNG in the right folder (same file name — the name *is* the key).
2. Run the build:

```
node labs/assets/fortress/build-sprites.js
```

It rewrites the `var SPR = {…}` block in `labs/fortress.html`. Nothing else to install:
it only needs Node.

## Notes

- Sprites are stored at the size the game draws them, so a 92 px monster stays crisp
  on a phone without extra scaling work.
- Keep them small — every byte ends up inside the HTML file, four thirds larger once it
  is base64.
- Licence and origin of each picture: see `CREDITS.md`.
