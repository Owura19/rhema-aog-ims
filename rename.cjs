// rename.js — GraceWorld -> Rhema project-wide rename
//
//   PREVIEW (no changes written):   node rename.js
//   APPLY the changes:              node rename.js apply
//
// Put this file in the project root (C:\Users\user\Herd\rhema-aog-ims)
// and run it from there.

const fs = require('fs');
const path = require('path');

// ---- Edit the right-hand side if you want different wording ----
// Order matters: longest / most-specific forms MUST come first.
const REPLACEMENTS = [
  ['GraceWorld International', 'Rhema Assembly of God'],
  ['GraceWorld IMS',          'Rhema AoG IMS'],
  ['graceworld-ims',          'rhema-aog-ims'],
  ['graceworld_ims',          'rhema_aog_ims'],
  ['GraceWorld',              'Rhema'],
  ['graceworld',              'rhema'],
  // To also rename the sermon feature, uncomment:
  // ['RandyImpact AI', 'Rhema Live'],
  // ['RandyImpact',    'RhemaLive'],
];
// ----------------------------------------------------------------

const ROOT = __dirname;
const APPLY = process.argv[2] === 'apply';

const SKIP_DIRS = new Set(['node_modules', 'vendor', '.git']);
const SKIP_NAMES = new Set(['composer.lock', 'package-lock.json', 'rename.js']);

// Only touch text files. Extensionless includes handled separately.
const TEXT_EXTS = new Set([
  '.php', '.js', '.jsx', '.ts', '.tsx', '.css', '.scss',
  '.json', '.md', '.txt', '.html', '.vue', '.xml',
  '.yml', '.yaml', '.stub', '.env', '.example', '.config',
]);
const INCLUDE_NAMES = new Set(['.env', '.env.example', 'artisan']);

function walk(dir, files = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    const rel = path.relative(ROOT, full).replace(/\\/g, '/');
    if (entry.isDirectory()) {
      if (SKIP_DIRS.has(entry.name)) continue;
      if (rel === 'storage' || rel.startsWith('storage/')) continue;
      if (rel.startsWith('bootstrap/cache')) continue;
      if (rel.startsWith('public/build') || rel.startsWith('public/hot')) continue;
      walk(full, files);
    } else {
      files.push(full);
    }
  }
  return files;
}

let totalFiles = 0;
let totalHits = 0;

for (const file of walk(ROOT)) {
  const base = path.basename(file);
  if (SKIP_NAMES.has(base)) continue;
  const ext = path.extname(base);
  if (!TEXT_EXTS.has(ext) && !INCLUDE_NAMES.has(base)) continue;

  let content;
  try { content = fs.readFileSync(file, 'utf8'); } catch { continue; }
  if (content.includes('\u0000')) continue; // looks binary, skip

  let updated = content;
  let hits = 0;
  for (const [from, to] of REPLACEMENTS) {
    const parts = updated.split(from);
    const count = parts.length - 1;
    if (count > 0) {
      updated = parts.join(to);
      hits += count;
    }
  }

  if (hits > 0) {
    totalFiles++;
    totalHits += hits;
    console.log(`${String(hits).padStart(4)}  ${path.relative(ROOT, file)}`);
    if (APPLY) fs.writeFileSync(file, updated, 'utf8');
  }
}

console.log('');
console.log(`${totalHits} replacements across ${totalFiles} files`);
console.log(APPLY
  ? 'APPLIED — changes written to disk.'
  : 'PREVIEW only. Run "node rename.js apply" to write the changes.');