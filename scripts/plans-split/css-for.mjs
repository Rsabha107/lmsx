// Scratch: print the CSS rules in Plans.vue's <style> whose selectors use any of the given classes.
// Usage: node scripts/plans-split/css-for.mjs class1 class2 ...
import { readFileSync } from 'node:fs';

const text = readFileSync('resources/js/Pages/Plans.vue', 'utf8');
const style = text.slice(text.indexOf('<style scoped>') + '<style scoped>'.length, text.lastIndexOf('</style>'));
const classes = process.argv.slice(2);
const uses = (selector) => classes.some((c) => new RegExp(`\\.${c}(?![\\w-])`).test(selector));

// Walk top-level blocks; descend one level into @media/@supports.
function rules(css) {
  const out = [];
  let i = 0;
  while (i < css.length) {
    const open = css.indexOf('{', i);
    if (open === -1) break;
    const selector = css.slice(i, open).trim();
    let depth = 1, j = open + 1;
    while (j < css.length && depth) { if (css[j] === '{') depth++; else if (css[j] === '}') depth--; j++; }
    const body = css.slice(open + 1, j - 1);
    out.push({ selector, body });
    i = j;
  }
  return out;
}

const picked = [];
for (const { selector, body } of rules(style)) {
  const clean = selector.replace(/\/\*[\s\S]*?\*\//g, '').trim();
  if (clean.startsWith('@media') || clean.startsWith('@supports')) {
    const inner = rules(body).filter((r) => uses(r.selector)).map((r) => `  ${r.selector.replace(/\/\*[\s\S]*?\*\//g, '').trim()} {${r.body}}`);
    if (inner.length) picked.push(`${clean} {\n${inner.join('\n')}\n}`);
  } else if (uses(clean)) {
    picked.push(`${clean} {${body}}`);
  }
}
console.log(picked.join('\n'));
