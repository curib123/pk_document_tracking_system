// Compile the semantic Tailwind component sheets; no CDN or runtime compiler.
const fs = require('node:fs');
const path = require('node:path');
const { compile } = require('tailwindcss');
const root = path.resolve(__dirname, '..');
const input = path.join(root, 'resources/styles');
function sources(directory) {
  return fs.readdirSync(directory, { withFileTypes: true }).flatMap(entry => {
    const filename = path.join(directory, entry.name);
    return entry.isDirectory() ? sources(filename) : entry.name.endsWith('.css') ? [filename] : [];
  }).sort();
}
(async () => {
  const source = sources(input).map(file => fs.readFileSync(file, 'utf8')).join('\n');
  const compiler = await compile(source);
  const css = compiler.build([]);
  // Unstyled controls must not be affected by resets or theme-root selectors.
  if (/(^|\n)(?:\*|:root|:host|html\b|body\s*\{)/m.test(css)) {
    throw new Error('Unscoped CSS would leak into disabled modules.');
  }
  const output = path.join(root, 'public/assets/css/app.css');
  if (process.argv.includes('--check')) {
    if (!fs.existsSync(output) || fs.readFileSync(output, 'utf8') !== css) {
      throw new Error('Compiled CSS is stale. Run npm run build:css and commit app.css.');
    }
    console.log('Compiled Tailwind CSS matches source.');
  } else {
    fs.mkdirSync(path.dirname(output), { recursive: true });
    fs.writeFileSync(output, css);
    console.log(`Built ${path.relative(root, output)} (${Buffer.byteLength(css)} bytes).`);
  }
})().catch(error => { console.error(error.message); process.exitCode = 1; });
