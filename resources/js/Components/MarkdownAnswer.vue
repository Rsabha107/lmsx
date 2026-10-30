<template>
  <div class="md">
    <template v-for="(block, i) in blocks" :key="i">
      <component :is="`h${block.level}`" v-if="block.type === 'heading'" class="md-h">
        <md-inline :text="block.text" />
      </component>

      <div v-else-if="block.type === 'table'" class="md-table-wrap">
        <table class="md-table">
          <thead>
            <tr>
              <th v-for="(cell, c) in block.head" :key="c" :class="alignClass(block, c)">
                <md-inline :text="cell" />
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, r) in block.rows" :key="r">
              <td v-for="(cell, c) in row" :key="c" :class="[alignClass(block, c), { 'md-nowrap': block.nowrap[c] }]">
                <md-status v-if="block.status === c && statusTone(cell)" :text="cell" />
                <md-inline v-else :text="cell" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else-if="block.type === 'card'" class="md-card">
        <div class="md-card-head">
          <span v-if="block.index" class="md-card-index">{{ block.index }}</span>
          <span class="md-card-title"><md-inline :text="block.title" /></span>
          <md-status v-if="block.status" :text="block.status" />
        </div>
        <dl v-if="block.fields.length" class="md-kv">
          <template v-for="(f, n) in block.fields" :key="n">
            <dt>{{ f.label }}</dt>
            <dd><md-inline :text="f.value" /></dd>
          </template>
        </dl>
      </div>

      <dl v-else-if="block.type === 'kv'" class="md-kv md-kv--plain">
        <template v-for="(f, n) in block.fields" :key="n">
          <dt>{{ f.label }}</dt>
          <dd>
            <md-status v-if="isStatusLabel(f.label) && statusTone(f.value)" :text="f.value" />
            <md-inline v-else :text="f.value" />
          </dd>
        </template>
      </dl>

      <ul v-else-if="block.type === 'ul'" class="md-list">
        <li v-for="(item, n) in block.items" :key="n"><md-inline :text="item" /></li>
      </ul>

      <ol v-else-if="block.type === 'ol'" class="md-list" :start="block.start">
        <li v-for="(item, n) in block.items" :key="n"><md-inline :text="item" /></li>
      </ol>

      <div v-else-if="block.type === 'attention'" class="md-attention">
        <span class="md-attention-label">Attention</span>
        <span><md-inline :text="block.text" /></span>
      </div>

      <p v-else class="md-p"><md-inline :text="block.text" /></p>
    </template>
  </div>
</template>

<script setup>
import { computed, h } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  text: { type: String, default: '' },
});

/**
 * Renders the Markdown subset the assistant emits. Output goes through Vue nodes, never v-html:
 * model output is untrusted input, so HTML in an answer must stay inert text.
 */
const MdInline = (p) => {
  const nodes = [];
  // Links, code spans, bold, italic, and job / movement references (JOB-20261028-0003, TRP-00624).
  const pattern = /(\[[^\]\n]{1,120}\]\([^)\s]{1,300}\)|`[^`]+`|\*\*[^*]+\*\*|\*[^*\n]+\*|\b(?:JOB-\d{6,8}-\d+|TRP-\d+)\b)/g;
  let last = 0;

  for (const match of p.text.matchAll(pattern)) {
    if (match.index > last) nodes.push(p.text.slice(last, match.index));
    const token = match[0];

    if (token.startsWith('[')) {
      const [, label, href] = token.match(/^\[(.*)\]\((.*)\)$/);
      nodes.push(INTERNAL_LINK.test(href)
        ? h(Link, { href, class: 'md-link' }, () => label)
        : label);
    } else if (token.startsWith('`')) {
      nodes.push(h('code', { class: 'md-code' }, token.slice(1, -1)));
    } else if (token.startsWith('**')) {
      nodes.push(h('strong', token.slice(2, -2)));
    } else if (token.startsWith('*')) {
      nodes.push(h('em', token.slice(1, -1)));
    } else {
      nodes.push(h('span', { class: 'md-ref' }, token));
    }
    last = match.index + token.length;
  }

  if (last < p.text.length) nodes.push(p.text.slice(last));

  return nodes;
};
MdInline.props = ['text'];

// Only these app pages can be linked from an answer; any other target (another site, javascript:) shows as text.
const INTERNAL_LINK = /^\/(job\/\d+|plans|crew-assignment|day-board|jobs|kit-truck|resource-schedule|event-teams)(?=$|[/?#])[\w\-/?#=&%.]*$/;

// Status words from the tools, shown as a coloured badge.
const TONES = [
  [/^(in[ -]progress|live|dispatched|started)/, 'live'],
  [/^(completed?|done|on time|arrived|landed|departed)/, 'ok'],
  [/^(delayed|late|overdue|behind)/, 'warn'],
  [/^(no job( yet)?|no crew|unassigned|missing|not covered|failed)/, 'alert'],
  [/^(cancel+ed|skipped|not required)/, 'muted'],
  [/^(pending|scheduled|planned|not started|waiting)/, 'neutral'],
];
const clean = (text) => String(text ?? '').replace(/\*\*/g, '').trim();
function statusTone(text) {
  const t = clean(text).toLowerCase();
  return TONES.find(([re]) => re.test(t))?.[1] ?? null;
}
const isStatusLabel = (label) => /^status$/i.test(clean(label));

const MdStatus = (p) => {
  const text = clean(p.text);
  const [, main, extra] = text.match(/^(.*?)(\s*\(.*\))?$/) ?? [null, text, ''];
  return h('span', { class: ['md-status', `md-status--${statusTone(text)}`] }, [
    main,
    extra ? h('span', { class: 'md-status-extra' }, extra.trim()) : null,
  ]);
};
MdStatus.props = ['text'];

const isRow = (line) => line.trim().startsWith('|');
const cells = (line) => line.trim().replace(/^\||\|$/g, '').split('|').map(c => c.trim());
const isDivider = (line) => /^\|?[\s:|-]+\|[\s:|-]*$/.test(line.trim()) && line.includes('-');
const BULLET = /^\s*[-*+]\s+/;
const NUMBERED = /^\s*(\d+)[.)]\s+/;
// "**Label:** value" or "Label: value" (short label) bullet lines.
const KV = /^\*\*([^*]{1,40}?):?\*\*:?\s*(.+)$|^([A-Za-z][\w /]{0,30}):\s+(.+)$/;
const kv = (item) => {
  const m = item.match(KV);
  return m ? { label: (m[1] ?? m[3]).trim(), value: (m[2] ?? m[4] ?? '').trim() } : null;
};
const allKv = (items) => items.length > 0 && items.every((it) => kv(it));

function parse(text) {
  const lines = text.replace(/\r/g, '').split('\n');
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
      const status = head.findIndex((c) => isStatusLabel(c));
      // Times, codes and ids read badly when wrapped.
      const nowrap = head.map((c, col) => /^(time|window|job|code|movement code|start|end|eta|sta|std)$/i.test(clean(c))
        || rows.every((r) => /^[\d:–\- ]{4,13}$/.test(clean(r[col] ?? ''))));
      out.push({ type: 'table', head, rows, align, status: status >= 0 ? status : null, nowrap });
      continue;
    }

    if (BULLET.test(line)) {
      const items = [];
      while (i < lines.length && BULLET.test(lines[i])) {
        items.push(lines[i].replace(BULLET, ''));
        i++;
      }
      out.push(allKv(items) ? { type: 'kv', fields: items.map(kv) } : { type: 'ul', items });
      continue;
    }

    if (NUMBERED.test(line)) {
      const items = [];
      const start = Number(line.match(NUMBERED)[1]);
      while (i < lines.length && NUMBERED.test(lines[i])) {
        items.push(lines[i].replace(NUMBERED, ''));
        i++;
      }
      out.push({ type: 'ol', items, start });
      continue;
    }

    if (/^\**attention:?\**:?\s/i.test(line.trim())) {
      out.push({ type: 'attention', text: line.trim().replace(/^\**attention:?\**:?\s*/i, '') });
      i++;
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

  return toCards(out);
}

/**
 * A title (numbered item, heading or bold-only line) followed by "Label: value" lines becomes a card.
 * The model often numbers each item on its own ("1." every time), so the index is counted here.
 */
function toCards(blocks) {
  const out = [];
  let index = 0;

  for (let b = 0; b < blocks.length; b++) {
    const block = blocks[b];
    const next = blocks[b + 1];
    const titled = (block.type === 'ol' && block.items.length === 1)
      || block.type === 'heading'
      || (block.type === 'p' && /^\*\*[^*]+\*\*$/.test(block.text.trim()));

    if (titled && next?.type === 'kv') {
      const fields = next.fields.filter((f) => !isStatusLabel(f.label));
      const status = next.fields.find((f) => isStatusLabel(f.label) && statusTone(f.value))?.value ?? null;
      const title = block.type === 'ol' ? block.items[0] : block.text;
      index = block.type === 'ol' ? index + 1 : index;
      out.push({ type: 'card', index: block.type === 'ol' ? index : null, title, fields, status });
      b++;
      continue;
    }

    out.push(block);
  }

  return out;
}

const blocks = computed(() => parse(props.text));

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

.md-table-wrap { overflow-x: auto; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); }
.md-table { border-collapse: collapse; width: 100%; font-size: 12.5px; }
.md-table th {
  text-align: left; padding: 7px 12px;
  background: var(--panel); color: var(--ink3);
  font-size: 10.5px; font-weight: 700;
  letter-spacing: .05em; text-transform: uppercase;
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}
.md-table td { padding: 7px 12px; border-bottom: 1px solid var(--border); color: var(--ink); vertical-align: middle; }
.md-table tbody tr:last-child td { border-bottom: 0; }
.md-table tbody tr:hover td { background: color-mix(in srgb, var(--panel) 60%, transparent); }
.md-nowrap { white-space: nowrap; font-variant-numeric: tabular-nums; }

.md-right { text-align: right; }
.md-center { text-align: center; }
.md-num { font-variant-numeric: tabular-nums; font-weight: 600; }

.md-code {
  font-family: var(--font-mono, ui-monospace, monospace);
  font-size: 11.5px;
  background: var(--panel);
  padding: 1px 5px; border-radius: 4px;
}
.md-link { color: var(--accent-fg, var(--accent)); font-weight: 600; text-decoration: underline; text-underline-offset: 2px; }
.md-link:hover { text-decoration-thickness: 2px; }
.md-ref {
  font-family: var(--font-mono, ui-monospace, monospace);
  font-size: 11.5px; color: var(--ink2); white-space: nowrap;
  background: var(--panel); border: 1px solid var(--border);
  padding: 0 5px; border-radius: 4px;
}

.md-status {
  display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;
  font-size: 11px; font-weight: 700; line-height: 1;
  padding: 4px 8px; border-radius: 20px;
}
.md-status::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.md-status-extra { font-weight: 500; opacity: 0.8; }
.md-status--live { background: var(--live-soft); color: var(--live); }
.md-status--ok { background: var(--ok-soft); color: var(--ok); }
.md-status--warn { background: var(--warn-soft); color: var(--warn); }
.md-status--alert { background: var(--danger-soft); color: var(--danger); }
.md-status--muted { background: var(--panel); color: var(--ink4); }
.md-status--neutral { background: var(--panel); color: var(--ink2); }

.md-card { border: 1px solid var(--border); border-radius: 10px; background: var(--surface); overflow: hidden; }
.md-card-head { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-bottom: 1px solid var(--border); background: var(--panel); }
.md-card-index {
  flex-shrink: 0; width: 22px; height: 22px; border-radius: 50%;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700; background: var(--accent); color: #fff;
}
.md-card-title { flex: 1; min-width: 0; font-weight: 700; color: var(--ink); }
.md-kv { margin: 0; padding: 10px 12px; display: grid; grid-template-columns: max-content 1fr; gap: 6px 16px; font-size: 12.5px; }
.md-kv dt { color: var(--ink3); font-weight: 600; }
.md-kv dd { margin: 0; color: var(--ink); }
.md-kv--plain { padding: 0; }

.md-attention {
  display: flex; gap: 10px; align-items: baseline;
  padding: 8px 12px; border-radius: 8px;
  background: var(--warn-soft); border: 1px solid color-mix(in srgb, var(--warn) 30%, transparent); color: var(--warn); line-height: 1.5;
}
.md-attention-label { font-size: 10.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; flex-shrink: 0; }
</style>
