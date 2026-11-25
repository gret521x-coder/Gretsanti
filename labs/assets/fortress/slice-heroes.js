#!/usr/bin/env node
/* ==========================================================================
   Cut the hero cards out of the reference sheet.

     node labs/assets/fortress/slice-heroes.js

   heroes/_sheet.jpg is a 3 x 3 grid of hero cards (the last row holds one).
   Each card is cropped whole — frame, face and printed name — then shrunk to
   88 px wide and written as heroes/<key>.png. Run build-sprites.js afterwards.
   ========================================================================== */
'use strict';
const sharp = require('sharp');
const fs = require('fs');
const path = require('path');

const SHEET = path.join(__dirname, 'heroes', '_sheet.jpg');
const OUT = path.join(__dirname, 'heroes');
const WIDTH = 88;

const COLS = [[1, 147], [150, 294], [297, 443]];
const ROWS = [[0, 227], [228, 455], [456, 683]];
const KEYS = ['alleria', 'gerald', 'malik', 'bolin', 'magnus', 'ignus', 'denas'];

(async () => {
  let n = 0;
  for (let r = 0; r < 3; r++) {
    for (let c = 0; c < 3; c++) {
      if (r === 2 && c > 0) continue;            /* the last row only has one card */
      const [x0, x1] = COLS[c], [y0, y1] = ROWS[r];
      const png = await sharp(SHEET)
        .extract({ left: x0, top: y0, width: x1 - x0 + 1, height: y1 - y0 + 1 })
        .resize({ width: WIDTH, kernel: 'lanczos3' })
        .png({ compressionLevel: 9, palette: true, colours: 128 })
        .toBuffer();
      const key = KEYS[n++];
      fs.writeFileSync(path.join(OUT, key + '.png'), png);
      const meta = await sharp(png).metadata();
      console.log(key.padEnd(9), meta.width + 'x' + meta.height, Math.round(png.length / 1024) + ' KB');
    }
  }
})();
