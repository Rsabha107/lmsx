// Scratch analysis for the Plans.vue split: which script identifiers a template
// range uses, and whether anything else in the file uses them too.
// Usage: node scripts/plans-split/analyse.mjs <startLine> <endLine>
import { readFileSync } from 'node:fs';

const file = 'resources/js/Pages/Plans.vue';
const lines = readFileSync(file, 'utf8').split(/\r?\n/);
const [start, end] = process.argv.slice(2).map(Number);

const scriptStart = lines.findIndex((l) => l.startsWith('<script setup')) + 1;
const scriptEnd = lines.findIndex((l) => l.startsWith('</script>'));
const script = lines.slice(scriptStart, scriptEnd);

// Top-level declarations in <script setup>: name -> [firstLine, lastLine] (1-based).
const decls = new Map();
for (let i = 0; i < script.length; i++) {
  const m = script[i].match(/^(?:const|let|function|async function)\s+([A-Za-z_$][\w$]*)/);
  if (!m) continue;
  let j = i;
  if (!/;\s*$/.test(script[i]) || /[({[]\s*$/.test(script[i])) {
    let depth = 0;
    for (j = i; j < script.length; j++) {
      for (const ch of script[j].replace(/(["'`])(?:\\.|(?!\1).)*\1/g, '')) {
        if ('({['.includes(ch)) depth++;
        if (')}]'.includes(ch)) depth--;
      }
      if (depth <= 0 && j > i && /^[)}\]]/.test(script[j].trim())) break;
      if (depth <= 0 && j === i && /;\s*$/.test(script[j])) break;
    }
  }
  decls.set(m[1], [scriptStart + i + 1, scriptStart + j + 1]);
}

const words = (text) => new Set(text.match(/[A-Za-z_$][\w$]*/g) ?? []);
const block = lines.slice(start - 1, end).join('\n');
const outsideTemplate = [...lines.slice(0, start - 1), ...lines.slice(end, scriptStart - 1)].join('\n');

const used = [...words(block)].filter((w) => decls.has(w));
const report = used.map((name) => {
  const [a, b] = decls.get(name);
  const scriptOutside = script
    .filter((_, i) => scriptStart + i + 1 < a || scriptStart + i + 1 > b)
    .join('\n');
  return {
    name,
    lines: `${a}-${b}`,
    inOtherTemplate: words(outsideTemplate).has(name),
    inOtherScript: words(scriptOutside).has(name),
  };
});
console.log(JSON.stringify(report, null, 0).replace(/\},\{/g, '},\n{'));
