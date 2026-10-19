// Scratch: drop CSS rules from Plans.vue's <style> whose selectors only use classes that
// no longer appear in its template or script. Prints what it removes.
// Usage: node scripts/plans-split/prune-css.mjs [--apply]
import { readFileSync, writeFileSync } from 'node:fs';

const path = 'resources/js/Pages/Plans.vue';
const text = readFileSync(path, 'utf8');
const open = text.indexOf('<style scoped>') + '<style scoped>'.length;
const close = text.lastIndexOf('</style>');
const markup = text.slice(0, open - '<style scoped>'.length);
const style = text.slice(open, close);

// A class counts as used if its name appears anywhere outside the stylesheet (covers :class and strings),
// if it is a modifier built at runtime (`foo--${x}`), or a <transition name> class.
const used = (cls) => {
  if (new RegExp(`(?<![\\w-])${cls.replace(/[-]/g, '\\-')}(?![\\w-])`).test(markup)) return true;
  if (cls.includes('--') && markup.includes(`${cls.slice(0, cls.lastIndexOf('--') + 2)}\${`)) return true;
  const transition = cls.match(/^(.*)-(enter|leave)(-active|-from|-to)?$/);
  return !!transition && markup.includes(`name="${transition[1]}"`);
};
const classesOf = (selector) => [...selector.matchAll(/\.([a-zA-Z][\w-]*)/g)].map((m) => m[1]);
const dead = (selector) => {
  if (selector.includes(':deep')) return false;
  const parts = selector.split(',').map((s) => s.trim()).filter(Boolean);
  return parts.length > 0 && parts.every((part) => {
    const classes = classesOf(part);
    return classes.length > 0 && classes.some((c) => !used(c));
  });
};

const removed = [];
function prune(css) {
  let out = '';
  let i = 0;
  while (i < css.length) {
    const brace = css.indexOf('{', i);
    if (brace === -1) { out += css.slice(i); break; }
    let depth = 1, j = brace + 1;
    while (j < css.length && depth) { if (css[j] === '{') depth++; else if (css[j] === '}') depth--; j++; }
    const head = css.slice(i, brace);
    const selector = head.replace(/\/\*[\s\S]*?\*\//g, '').trim();
    const body = css.slice(brace + 1, j - 1);
    if (selector.startsWith('@media') || selector.startsWith('@supports')) {
      const inner = prune(body);
      out += inner.trim() ? `${head}{${inner}}` : '';
    } else if (!selector.startsWith('@') && dead(selector)) {
      removed.push(selector);
      // keep any comment that sat before the rule only if it isn't the rule's own heading
      out += head.slice(0, head.length - head.trimStart().length);
    } else {
      out += css.slice(i, j);
    }
    i = j;
  }
  return out;
}

const next = prune(style).replace(/\n{3,}/g, '\n\n');
console.log(removed.join('\n'));
console.log(`${removed.length} rules`);
if (process.argv.includes('--apply')) writeFileSync(path, text.slice(0, open) + next + text.slice(close));
