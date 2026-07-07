// gridfix.cjs — convert inline grid styles in Blade views to responsive classes
//
//   PREVIEW (no changes):   node gridfix.cjs
//   APPLY the changes:      node gridfix.cjs apply
//
// Run from the project root.

const fs = require('fs');
const path = require('path');

const ROOT = __dirname;
const VIEWS = path.join(ROOT, 'resources', 'views');
const APPLY = process.argv[2] === 'apply';

// Map each column definition to a responsive class.
// Keys are normalised (spaces removed) column specs.
const COLS_TO_CLASS = {
  'repeat(2,1fr)': 'grid-2',
  '1fr1fr': 'grid-2',
  'repeat(3,1fr)': 'grid-3',
  '1fr1fr1fr': 'grid-3',
  'repeat(4,1fr)': 'grid-4',
  '1fr1fr1fr1fr': 'grid-4',
  'repeat(5,1fr)': 'grid-5',
  '1fr1fr1fr1fr1fr': 'grid-5',
  '2fr1fr': 'grid-main',
  '1fr2fr': 'grid-main-rev',
};

// Matches: style="display:grid; grid-template-columns:<cols>; gap:<g>; [margin-bottom:<m>;]"
// Tolerant of spacing and optional trailing margin-bottom.
const GRID_RE = /style="display:\s*grid;\s*grid-template-columns:\s*([^;]+);\s*gap:\s*[^;]+;\s*(margin-bottom:\s*[^;"]+;?)?\s*"/g;

function classFor(colsRaw) {
  const key = colsRaw.replace(/\s+/g, '');
  return COLS_TO_CLASS[key] || null;
}

function walk(dir, files = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full, files);
    else if (entry.name.endsWith('.blade.php')) files.push(full);
  }
  return files;
}

let totalFiles = 0;
let totalHits = 0;
const unmatched = new Set();

for (const file of walk(VIEWS)) {
  let content;
  try { content = fs.readFileSync(file, 'utf8'); } catch { continue; }

  let hits = 0;
  const updated = content.replace(GRID_RE, (whole, cols, marginPart) => {
    const cls = classFor(cols);
    if (!cls) {
      unmatched.add(cols.trim());
      return whole; // leave untouched if we don't recognise the column spec
    }
    hits++;
    const margin = marginPart ? marginPart.trim().replace(/;$/, '') : '';
    return margin
      ? `class="${cls}" style="${margin};"`
      : `class="${cls}"`;
  });

  if (hits > 0) {
    totalFiles++;
    totalHits += hits;
    console.log(`${String(hits).padStart(3)}  ${path.relative(ROOT, file)}`);
    if (APPLY) fs.writeFileSync(file, updated, 'utf8');
  }
}

console.log('');
console.log(`${totalHits} grids converted across ${totalFiles} files`);
if (unmatched.size) {
  console.log('');
  console.log('Column specs NOT converted (no matching class) — review by hand:');
  for (const u of unmatched) console.log('   ' + u);
}
console.log('');
console.log(APPLY
  ? 'APPLIED — changes written to disk.'
  : 'PREVIEW only. Run "node gridfix.cjs apply" to write the changes.');
