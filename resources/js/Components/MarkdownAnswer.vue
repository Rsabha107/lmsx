<template>
  <div class="md">
    <template v-for="(block, i) in blocks" :key="i">
      <component :is="`h${block.level}`" v-if="block.type === 'heading'" class="md-h">
        <md-inline :text="block.text" />
      </component>

      <table v-else-if="block.type === 'table'" class="md-table">
        <thead>
          <tr>
            <th v-for="(cell, c) in block.head" :key="c" :class="alignClass(block, c)">
              <md-inline :text="cell" />
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, r) in block.rows" :key="r">
            <td v-for="(cell, c) in row" :key="c" :class="alignClass(block, c)">
              <md-inline :text="cell" />
            </td>
          </tr>
        </tbody>
      </table>

      <ul v-else-if="block.type === 'ul'" class="md-list">
        <li v-for="(item, n) in block.items" :key="n"><md-inline :text="item" /></li>
      </ul>

      <ol v-else-if="block.type === 'ol'" class="md-list">
        <li v-for="(item, n) in block.items" :key="n"><md-inline :text="item" /></li>
      </ol>

      <p v-else class="md-p"><md-inline :text="block.text" /></p>
    </template>
  </div>
</template>

<script setup>
import { computed, h } from 'vue';

const props = defineProps({
  text: { type: String, default: '' },
});

/**
 * Renders the Markdown subset the assistant actually emits. Output goes through
 * Vue nodes, never v-html: model output is untrusted input, so HTML in an answer
 * must stay inert text rather than be parsed.
 */
const MdInline = (p) => {
  const nodes = [];
  const pattern = /(`[^`]+`|\*\*[^*]+\*\*|\*[^*\n]+\*)/g;
  let last = 0;

  for (const match of p.text.matchAll(pattern)) {
    if (match.index > last) nodes.push(p.text.slice(last, match.index));
    const token = match[0];

    if (token.startsWith('`')) {
      nodes.push(h('code', { class: 'md-code' }, token.slice(1, -1)));
    } else if (token.startsWith('**')) {
      nodes.push(h('strong', token.slice(2, -2)));
    } else {
      nodes.push(h('em', token.slice(1, -1)));
    }
    last = match.index + token.length;
  }

  if (last < p.text.length) nodes.push(p.text.slice(last));

  return nodes;
};
MdInline.props = ['text'];

const isRow = (line) => line.trim().startsWith('|');
const cells = (line) => line.trim().replace(/^\||\|$/g, '').split('|').map(c => c.trim());
const isDivider = (line) => /^\|?[\s:|-]+\|[\s:|-]*$/.test(line.trim()) && line.includes('-');

const blocks = computed(() => {
  const lines = props.text.replace(/\r/g, '').split('\n');
  const out = [];
  let i = 0;

  while (i < lines.length) {
    const line = lines[i];

    if (!line.trim()) { i++; continue; }

    const heading = line.match(/^(#{1,4})\s+(.*)$/);
    if (heading) {
      out.push({ type: 'heading', level: Math.min(heading[1].length + 2, 6), text: heading[2] });
      i++;
      continue;
    }

    if (isRow(line) && isDivider(lines[i + 1] ?? '')) {
      const head = cells(line);
      const align = cells(lines[i + 1]).map(spec => {
        const right = spec.endsWith(':');
        const left = spec.startsWith(':');
        return left && right ? 'center' : right ? 'right' : null;
      });
      i += 2;

      const rows = [];
      while (i < lines.length && isRow(lines[i])) {
        rows.push(cells(lines[i]));
        i++;
      }
      out.push({ type: 'table', head, rows, align });
      continue;
    }

    if (/^\s*[-*+]\s+/.test(line)) {
      const items = [];
      while (i < lines.length && /^\s*[-*+]\s+/.test(lines[i])) {
        items.push(lines[i].replace(/^\s*[-*+]\s+/, ''));
        i++;
      }
      out.push({ type: 'ul', items });
      continue;
    }

    if (/^\s*\d+[.)]\s+/.test(line)) {
      const items = [];
      while (i < lines.length && /^\s*\d+[.)]\s+/.test(lines[i])) {
        items.push(lines[i].replace(/^\s*\d+[.)]\s+/, ''));
        i++;
      }
      out.push({ type: 'ol', items });
      continue;
    }

    const para = [];
    while (i < lines.length && lines[i].trim() && !isRow(lines[i])
           && !/^\s*([-*+]|\d+[.)])\s+/.test(lines[i]) && !/^#{1,4}\s/.test(lines[i])) {
      para.push(lines[i].trim());
      i++;
    }
    out.push({ type: 'p', text: para.join(' ') });
  }

  return out;
});

// Counts read far better right-aligned than strung along the left.
function alignClass(block, column) {
  if (block.align?.[column]) return `md-${block.align[column]}`;

  const body = block.rows.map(r => r[column]).filter(v => v != null && v !== '');
  const numeric = body.length > 0 && body.every(v => /^[\d.,%+-]+$/.test(v.trim()));

  return numeric ? 'md-right md-num' : null;
}
</script>

<style scoped>
.md { display: flex; flex-direction: column; gap: 10px; }

.md-p { margin: 0; line-height: 1.6; }
.md-h { margin: 2px 0 0; font-size: 13px; font-weight: 700; color: var(--ink); }

.md-list { margin: 0; padding-left: 18px; display: flex; flex-direction: column; gap: 4px; line-height: 1.55; }

.md-table {
  border-collapse: collapse;
  width: auto; min-width: min(100%, 260px);
  font-size: 12.5px;
  border: 1px solid var(--border);
  border-radius: 8px;
  overflow: hidden;
}
.md-table th {
  text-align: left; padding: 6px 12px;
  background: var(--panel); color: var(--ink3);
  font-size: 10.5px; font-weight: 700;
  letter-spacing: .05em; text-transform: uppercase;
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}
.md-table td {
  padding: 6px 12px;
  border-bottom: 1px solid var(--border);
  color: var(--ink);
}
.md-table tbody tr:last-child td { border-bottom: 0; }
.md-table tbody tr:nth-child(even) td { background: color-mix(in srgb, var(--panel) 45%, transparent); }

.md-right { text-align: right; }
.md-center { text-align: center; }
.md-num { font-variant-numeric: tabular-nums; font-weight: 600; }

.md-code {
  font-family: var(--font-mono, ui-monospace, monospace);
  font-size: 11.5px;
  background: var(--panel);
  padding: 1px 5px; border-radius: 4px;
}
</style>
