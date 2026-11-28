#!/usr/bin/env node
/**
 * ============================================================================
 *  Fortress — install a sprite
 *  ------------------------------------------------------------------------
 *  Puts one picture where the game expects it, after checking it will not
 *  look wrong there. No dependencies: it reads the PNG header itself.
 *
 *      node TD/tools/install-sprite.js <source.png> <group:key> [--force]
 *      node TD/tools/install-sprite.js knight.png heroes:royal_knight
 *      node TD/tools/install-sprite.js orc.png monsters:grunt --force
 *      node TD/tools/install-sprite.js x.png monsters:grunt --root /tmp/td-test
 *
 *  What it refuses, and why
 *    · a key that is not in TD/resources/assets.json      (nothing would load it)
 *    · a file that is not a PNG                            (the loader asks for .png)
 *    · no alpha channel                                    (it would paint a box
 *                                                           over the map)
 *    · the wrong shape                                     (the game stretches the
 *                                                           picture into the box its
 *                                                           character grid fills, so
 *                                                           a 1:1 picture in a 10:11
 *                                                           slot comes out fat)
 *    · over 200 KB                                         (36 of those would blow
 *                                                           the 2.6 s preloader)
 *
 *  What it does not do is judge the art. If the shape is right and the alpha is
 *  there, it is installed; --force overrides every check but the key.
 * ============================================================================
 */
'use strict';

const fs = require('fs');
const path = require('path');

const RATIO_TOLERANCE = 0.025;      /* 2.5% — enough for rounding, not enough to squash */
const SOFT_BYTES = 80 * 1024;
const HARD_BYTES = 200 * 1024;

function fail(msg) {
  console.error('✗ ' + msg);
  process.exit(1);
}

/* ---------------- the PNG header, read by hand ---------------- */
function readPng(buf) {
  const sig = [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a];
  if (buf.length < 33 || sig.some((b, i) => buf[i] !== b)) return null;
  /* IHDR is always the first chunk: length(4) type(4) w(4) h(4) depth(1) colour(1) … */
  if (buf.toString('ascii', 12, 16) !== 'IHDR') return null;
  return {
    width: buf.readUInt32BE(16),
    height: buf.readUInt32BE(20),
    bitDepth: buf[24],
    colorType: buf[25]            /* 4 = grey+alpha, 6 = RGBA, 3 = palette (alpha only via tRNS) */
  };
}

function hasAlpha(png, buf) {
  if (png.colorType === 4 || png.colorType === 6) return true;
  if (png.colorType === 3) return buf.includes(Buffer.from('tRNS', 'ascii'));
  return false;
}

/* ---------------- arguments ---------------- */
const argv = process.argv.slice(2);
const flags = {};
const rest = [];
for (let i = 0; i < argv.length; i++) {
  if (argv[i] === '--force') { flags.force = true; continue; }
  if (argv[i] === '--root') { flags.root = argv[++i]; continue; }
  rest.push(argv[i]);
}
const [source, key] = rest;

if (!source || !key) {
  fail('usage: node TD/tools/install-sprite.js <source.png> <group:key> [--force] [--root DIR]\n' +
    '       keys: node TD/tools/install-sprite.js --list');
}
if (source === '--list') {
  const man = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'resources', 'assets.json'), 'utf8'));
  ['towers', 'troops', 'monsters', 'heroes', 'ui'].forEach(g => {
    Object.keys(man[g] || {}).forEach(k => console.log(g + ':' + k + '  →  ' + man[g][k]));
  });
  process.exit(0);
}

const root = flags.root ? path.resolve(flags.root) : path.resolve(__dirname, '..');
const manifestPath = path.join(root, 'resources', 'assets.json');
const boxesPath = path.join(root, 'resources', 'sprite-boxes.json');

if (!fs.existsSync(manifestPath)) fail('no manifest at ' + manifestPath);
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const boxes = fs.existsSync(boxesPath) ? (JSON.parse(fs.readFileSync(boxesPath, 'utf8')).boxes || {}) : {};

const [group, name] = key.split(':');
if (!group || !name || !manifest[group] || !manifest[group][name]) {
  fail('no such slot: ' + key + '\n  run with --list to see the ones that exist');
}

/* ---------------- the picture ---------------- */
if (!fs.existsSync(source)) fail('no file at ' + source);
const buf = fs.readFileSync(source);
const png = readPng(buf);
if (!png) fail('that is not a PNG. The game asks for .png and nothing else.');

const bytes = buf.length;
const warn = [];
if (bytes > HARD_BYTES && !flags.force) {
  fail('that file is ' + Math.round(bytes / 1024) + ' KB; the ceiling is ' + (HARD_BYTES / 1024) +
    ' KB so the preloader still finishes in 2.6 s. Shrink it, or --force.');
}
if (bytes > SOFT_BYTES) warn.push(Math.round(bytes / 1024) + ' KB is heavy for a sprite this small');

if (!hasAlpha(png, buf) && !flags.force) {
  fail('no alpha channel — this would paint its background over the map.\n' +
    '  Export it again with a transparent background, or --force to take the box.');
}

const box = boxes[key];
if (box) {
  const want = box[0] / box[1];
  const got = png.width / png.height;
  const off = Math.abs(got - want) / want;
  if (off > RATIO_TOLERANCE && !flags.force) {
    fail('wrong shape: the ' + key + ' box is ' + box[0] + '×' + box[1] + ' (' + want.toFixed(2) + ':1), ' +
      'this picture is ' + png.width + '×' + png.height + ' (' + got.toFixed(2) + ':1).\n' +
      '  The game stretches it into the box, so it would come out ' +
      (got > want ? 'wide' : 'tall') + '. --force to take it anyway.');
  }
  if (off > RATIO_TOLERANCE) warn.push('shape is off by ' + Math.round(off * 100) + '% — it will be stretched');
}

/* ---------------- install ---------------- */
const rel = manifest[group][name];
const dest = path.join(root, rel.split('/').join(path.sep));
fs.mkdirSync(path.dirname(dest), { recursive: true });
fs.writeFileSync(dest, buf);

const kb = (bytes / 1024).toFixed(1);
console.log('✓ installed ' + key);
console.log('    file   ' + rel + '  (' + kb + ' KB)');
console.log('    size   ' + png.width + '×' + png.height + (box ? '  → drawn into ' + box[0] + '×' + box[1] : ''));
console.log('    alpha  ' + (hasAlpha(png, buf) ? 'yes' : 'no'));
warn.forEach(w => console.log('  ! ' + w));
console.log('\n  Next: commit it. The game reads TD/ beside itself first, then main on GitHub,');
console.log('  so a hosted copy only sees it once it is on main. Nothing else to change — the');
console.log('  manifest already names this path, and every slot falls back to its drawn sprite.');
