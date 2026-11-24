#!/usr/bin/env node
/* ==========================================================================
   Rebuild the sprite block inside labs/fortress.html from the PNGs in this
   folder. The game has no asset requests at run time — every picture is a
   data URI in the file — so this script is how a new or changed PNG gets in.

     node labs/assets/fortress/build-sprites.js

   Keys come from the file name, so monsters/orc.png becomes 'orc'.
   ========================================================================== */
'use strict';
const fs = require('fs');
const path = require('path');

const HERE = __dirname;
const GAME = path.join(HERE, '..', '..', 'fortress.html');
const FOLDERS = ['towers', 'heroes', 'monsters', 'decor', 'ui'];

const SPR = {};
for (const folder of FOLDERS) {
  const dir = path.join(HERE, folder);
  if (!fs.existsSync(dir)) continue;
  for (const file of fs.readdirSync(dir).sort()) {
    if (!file.toLowerCase().endsWith('.png')) continue;
    const key = path.basename(file, '.png');
    const bytes = fs.readFileSync(path.join(dir, file));
    SPR[key] = 'data:image/png;base64,' + bytes.toString('base64');
    console.log(key.padEnd(14), String(Math.round(bytes.length / 1024)).padStart(3) + ' KB');
  }
}

if (!Object.keys(SPR).length) {
  console.error('no PNGs found — nothing to do');
  process.exit(1);
}

const html = fs.readFileSync(GAME, 'utf8');
const start = html.indexOf('var SPR = {');
const end = html.indexOf('};', start) + 2;
if (start < 0) {
  console.error('could not find the sprite block in ' + GAME);
  process.exit(1);
}
const out = html.slice(0, start) + 'var SPR = ' + JSON.stringify(SPR) + ';' + html.slice(end);
fs.writeFileSync(GAME, out);
console.log('\nwrote ' + Object.keys(SPR).length + ' sprites into ' + path.relative(process.cwd(), GAME));
