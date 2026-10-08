// Supplemental reference layout, compiled separately to preserve existing CSS.
const fs = require('node:fs');
const path = require('node:path');
const { compile } = require('tailwindcss');
(async () => {
  const root = path.resolve(__dirname, '..');
  const source = fs.readFileSync(path.join(root, 'resources/workspace/reference.css'), 'utf8');
  const css = (await compile(source)).build([]);
  if (/(^|\n)(?:\*|:root|:host|html\s*\{|body\s*\{)/m.test(css)) throw new Error('Unscoped reference design.');
  const output = path.join(root, 'public/assets/css/workspace.css');
  if (process.argv.includes('--check')) {
    if (!fs.existsSync(output) || fs.readFileSync(output, 'utf8') !== css) throw new Error('Rebuild workspace.css: node tools/build-workspace.cjs');
    console.log('Reference stylesheet matches source.');
  } else {
    fs.mkdirSync(path.dirname(output), { recursive: true });
    fs.writeFileSync(output, css);
    console.log('Built reference workspace stylesheet.');
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
