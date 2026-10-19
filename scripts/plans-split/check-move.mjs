// Scratch: check a planned extraction. Given 1-based line ranges to move out of
// Plans.vue, report (a) names declared in the moved code that the rest still uses,
// and (b) names the moved code needs from what stays (script declarations and props).
// Usage: node scripts/plans-split/check-move.mjs 100-200 300-310 ...
import { readFileSync } from 'node:fs';

const lines = readFileSync('resources/js/Pages/Plans.vue', 'utf8').split(/\r?\n/);
const ranges = process.argv.slice(2).map((r) => r.split('-').map(Number));
const moved = (n) => ranges.some(([a, b]) => n >= a && n <= b);

const scriptStart = lines.findIndex((l) => l.startsWith('<script setup')) + 1;
const scriptEnd = lines.findIndex((l) => l.startsWith('</script>'));

const strip = (s) => s.replace(/\/\/.*$/, '').replace(/(["'])(?:\\.|(?!\1).)*\1/g, '""');
const words = (text) => new Set(strip(text).match(/[A-Za-z_$][\w$]*/g) ?? []);
const declared = (from, to, keep) => {
  const names = new Set();
  for (let n = from; n <= to; n++) {
    if (keep(n) === false) continue;
    const m = lines[n - 1].match(/^(?:const|let|function|async function)\s+([A-Za-z_$][\w$]*)/);
    if (m) names.add(m[1]);
  }
  return names;
};

const movedDecls = declared(scriptStart + 1, scriptEnd, (n) => moved(n));
const stayDecls = declared(scriptStart + 1, scriptEnd, (n) => !moved(n));

const movedText = [], stayText = [];
lines.forEach((l, i) => (moved(i + 1) ? movedText : stayText).push(l));

const propsMatch = lines.slice(scriptStart).join('\n').match(/defineProps\(\{([\s\S]*?)\n\}\)/);
const propNames = new Set([...(propsMatch?.[1].matchAll(/^\s{2}(\w+):/gm) ?? [])].map((m) => m[1]));

const stayWords = words(stayText.join('\n'));
const movedWords = words(movedText.join('\n'));
console.log('Still used by the rest:', [...movedDecls].filter((n) => stayWords.has(n)).join(', ') || '-');
console.log('Needed from what stays:', [...stayDecls].filter((n) => movedWords.has(n)).join(', ') || '-');
console.log('Props used by moved code:', [...propNames].filter((n) => movedWords.has(n)).join(', ') || '-');
