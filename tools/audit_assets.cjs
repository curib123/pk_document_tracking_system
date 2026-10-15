#!/usr/bin/env node
'use strict';
// Read-only CSS usage audit. Dynamic class names, selectors produced by PHP,
// third-party markup and runtime assets can defeat static detection.
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
function walk(dir) {
  if (!fs.existsSync(dir)) return [];
  return fs.readdirSync(dir, {withFileTypes: true}).flatMap(e => {
    const p = path.join(dir, e.name);
    return e.isDirectory() ? walk(p) : [p];
  });
}
const sourcePaths = [
  ...walk(path.join(root, 'application')),
  ...walk(path.join(root, 'public/assets/js')),
  ...walk(path.join(root, 'public'))
].filter((p, i, a) => a.indexOf(p) === i && /\.(php|js|cjs|html)$/.test(p));
const sources = sourcePaths.map(p => fs.readFileSync(p, 'utf8')).join('\n');
const cssFiles = walk(path.join(root, 'public/assets/css')).filter(p=>p.endsWith('.css'));
const candidates = [];
for (const file of cssFiles) {
  const source = fs.readFileSync(file,'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
  const selectors = source.match(/\.([a-zA-Z_][\w-]*)/g) || [];
  const names = [...new Set(selectors.map(x=>x.slice(1)))];
  const unused = names.filter(name => {
    // Treat common dynamic BEM/status variants and JS-generated suffixes as indeterminate.
    if (/^(fa-|status-|is-|has-|show$|active$|collapsed$|modal-|btn-|d-|col-|row$)/.test(name)) return false;
    return !sources.includes(name);
  });
  if (unused.length) candidates.push({file: path.relative(root,file), possiblyUnusedClasses:unused.sort()});
}
process.stdout.write(JSON.stringify({
  note:'Review candidates only. Never delete styles automatically based on this report.',
  scannedSourceFiles: sourcePaths.length, scannedCssFiles: cssFiles.length,
  candidates
},null,2)+'\n');
