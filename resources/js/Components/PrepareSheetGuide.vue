<template>
  <div class="guide">
    <button type="button" class="guide-toggle" :aria-expanded="open" @click="open = !open">
      <svg-icon name="info" :size="14" />
      <span class="guide-toggle-label">How do I prepare my file?</span>
      <svg-icon name="chevronDown" :size="14" class="guide-chevron" :class="{ 'guide-chevron--open': open }" />
    </button>

    <div v-if="open" class="guide-body" @mouseenter="pause" @mouseleave="resume">
      <div class="guide-modes" role="tablist" aria-label="File type">
        <button
          v-for="option in modes"
          :key="option.value"
          type="button"
          role="tab"
          class="guide-mode"
          :class="{ 'guide-mode--active': mode === option.value }"
          :aria-selected="mode === option.value"
          @click="mode = option.value"
        >
          <svg-icon :name="option.icon" :size="13" />
          {{ option.label }}
        </button>
      </div>

      <p v-if="mode === 'sheet'" class="guide-intro">
        The LOG PMA Scheduler holds <strong>70+ sheets</strong>, but only <strong>one</strong> is read — whichever
        was selected when the workbook was last saved. Copy the sheet you need into its own file first.
      </p>
      <p v-else class="guide-intro">
        A PDF has no columns to match, so <strong>AI reads the values themselves</strong>. It works, but a
        spreadsheet is always more reliable — use a PDF only when that is all you have, and keep it to
        <strong>one table</strong>.
      </p>

      <div v-if="mode === 'sheet'" class="guide-stage">
        <svg viewBox="0 0 400 232" class="guide-svg" role="img" :aria-label="steps[current].title">
          <!-- Window -->
          <rect x="6" y="6" width="388" height="220" rx="7" class="win" />
          <path d="M6 13a7 7 0 0 1 7-7h374a7 7 0 0 1 7 7v13H6z" fill="#217346" />
          <circle cx="18" cy="16" r="3" fill="#ffffff" opacity=".55" />
          <circle cx="28" cy="16" r="3" fill="#ffffff" opacity=".55" />
          <circle cx="38" cy="16" r="3" fill="#ffffff" opacity=".55" />
          <text x="200" y="20" class="win-title">{{ current >= 2 ? 'Book1 — new file' : fileName }}</text>

          <!-- Column letters -->
          <rect x="6" y="26" width="388" height="13" fill="#f1f3f4" />
          <text v-for="(col, i) in colLetters" :key="col" :x="colX(i) + colWidths[i] / 2" y="35" class="col-letter">
            {{ col }}
          </text>

          <!-- Row numbers -->
          <rect x="6" y="39" width="16" height="150" fill="#f1f3f4" />
          <text v-for="n in 8" :key="`n${n}`" x="14" :y="49 + (n - 1) * 18" class="row-num">{{ n + 3 }}</text>

          <!-- Rows above the header: the sheet's own title/spacer rows -->
          <g opacity=".5">
            <rect x="22" y="39" width="372" height="34" fill="#ffffff" />
            <rect x="28" y="47" width="120" height="6" rx="2" fill="#d6d9dc" />
          </g>

          <!-- Header row (row 6 in the real sheet) -->
          <rect x="22" y="75" width="372" height="18" fill="#8A1538" />
          <text
            v-for="(head, i) in preview.headers"
            :key="head"
            :x="colX(i) + 4"
            y="87"
            class="head-text"
          >{{ head }}</text>

          <!-- Data rows -->
          <g v-for="(row, r) in preview.rows" :key="`row${r}`">
            <rect x="22" :y="93 + r * 18" width="372" height="18" :fill="r % 2 ? '#f7f8f9' : '#ffffff'" />
            <text
              v-for="(cell, c) in row"
              :key="`c${c}`"
              :x="colX(c) + 4"
              :y="105 + r * 18"
              class="cell-text"
            >{{ cell }}</text>
          </g>

          <!-- Grid lines -->
          <g class="grid-lines">
            <line v-for="(col, i) in colLetters" :key="`l${col}`" :x1="colX(i)" y1="26" :x2="colX(i)" y2="189" />
            <line v-for="n in 9" :key="`h${n}`" x1="22" :y1="39 + (n - 1) * 18" x2="394" :y2="39 + (n - 1) * 18" />
          </g>

          <!-- Step 3: values-only wash over the data -->
          <g class="layer" :class="{ 'layer--on': current === 2 }">
            <rect x="22" y="75" width="372" height="72" fill="#2f7d32" opacity=".12" />
            <rect x="22" y="75" width="372" height="72" fill="none" stroke="#2f7d32" stroke-width="1.5" stroke-dasharray="4 3" />
            <rect x="248" y="96" width="132" height="26" rx="6" fill="#2f7d32" />
            <text x="314" y="113" class="badge-text">Paste as Values</text>
          </g>

          <!-- Sheet tabs -->
          <g class="tabs">
            <template v-if="current < 2">
              <rect x="22" y="192" width="62" height="18" rx="3" fill="#e8eaed" />
              <rect x="22" y="207" width="62" height="3" fill="#9aa0a6" />
              <text x="53" y="204" class="tab-text">Home.Events</text>

              <rect x="88" y="192" width="118" height="18" rx="3" fill="#ffffff" stroke="#217346" />
              <rect x="88" y="207" width="118" height="3" fill="#217346" />
              <text x="147" y="204" class="tab-text tab-text--active">{{ sheetTab }}</text>

              <rect x="210" y="192" width="62" height="18" rx="3" fill="#e8eaed" />
              <rect x="210" y="207" width="62" height="3" fill="#9aa0a6" />
              <text x="241" y="204" class="tab-text">Rpt.…</text>

              <text x="282" y="205" class="tab-more">+75 more</text>
            </template>
            <template v-else>
              <rect x="22" y="192" width="118" height="18" rx="3" fill="#ffffff" stroke="#217346" />
              <rect x="22" y="207" width="118" height="3" fill="#217346" />
              <text x="81" y="204" class="tab-text tab-text--active">{{ sheetTab }}</text>
            </template>
          </g>

          <!-- Step 1: highlight the tab -->
          <g class="layer" :class="{ 'layer--on': current === 0 }">
            <rect x="85" y="189" width="124" height="24" rx="5" fill="none" stroke="#f59e0b" stroke-width="2.5" />
            <circle cx="147" cy="222" r="5" fill="#f59e0b" opacity=".35" />
          </g>

          <!-- Step 2: right-click menu -->
          <g class="layer" :class="{ 'layer--on': current === 1 }">
            <rect x="100" y="104" width="140" height="74" rx="6" fill="#ffffff" stroke="#c9cdd2" class="menu-shadow" />
            <text x="112" y="122" class="menu-item">Insert…</text>
            <text x="112" y="139" class="menu-item">Delete</text>
            <rect x="104" y="144" width="132" height="16" rx="3" fill="#217346" />
            <text x="112" y="156" class="menu-item menu-item--hl">Move or Copy…</text>
            <text x="112" y="173" class="menu-item">Rename</text>
          </g>

          <!-- Step 4: save -->
          <g class="layer" :class="{ 'layer--on': current === 3 }">
            <rect x="96" y="90" width="208" height="70" rx="8" fill="#ffffff" stroke="#c9cdd2" class="menu-shadow" />
            <text x="200" y="112" class="dialog-title">Save As</text>
            <rect x="112" y="120" width="176" height="18" rx="4" fill="#f1f3f4" stroke="#c9cdd2" />
            <text x="118" y="133" class="dialog-input">{{ saveName }}</text>
            <rect x="240" y="142" width="48" height="16" rx="4" fill="#217346" />
            <text x="264" y="154" class="dialog-btn">Save</text>
          </g>

          <!-- Step 5: upload -->
          <g class="layer" :class="{ 'layer--on': current === 4 }">
            <rect x="96" y="86" width="208" height="80" rx="8" fill="#ffffff" stroke="#217346" stroke-dasharray="5 4" />
            <path d="M200 142 L200 106 M188 118 L200 106 L212 118" fill="none" stroke="#217346" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
            <text x="200" y="158" class="dialog-title">Drop it below</text>
          </g>
        </svg>
      </div>

      <div v-else class="guide-stage">
        <svg viewBox="0 0 400 232" class="guide-svg" role="img" :aria-label="steps[current].title">
          <!-- Viewer window -->
          <rect x="6" y="6" width="388" height="220" rx="7" class="win" />
          <path d="M6 13a7 7 0 0 1 7-7h374a7 7 0 0 1 7 7v13H6z" fill="#b3261e" />
          <circle cx="18" cy="16" r="3" fill="#ffffff" opacity=".55" />
          <circle cx="28" cy="16" r="3" fill="#ffffff" opacity=".55" />
          <circle cx="38" cy="16" r="3" fill="#ffffff" opacity=".55" />
          <text x="200" y="20" class="win-title">{{ pdfName }}</text>
          <rect x="6" y="26" width="388" height="13" fill="#f1f3f4" />
          <text x="230" y="35" class="col-letter">Page 1 of {{ pdfThumbs }}</text>

          <!-- Page thumbnails -->
          <rect x="6" y="39" width="60" height="187" fill="#e8eaed" />
          <g v-for="t in pdfThumbs" :key="`t${t}`">
            <rect x="16" :y="thumbY(t - 1)" width="40" height="28" fill="#ffffff" stroke="#c9cdd2" stroke-width=".6" />
            <rect x="20" :y="thumbY(t - 1) + 5" width="32" height="3" :fill="t > 3 ? '#1a73e8' : '#8A1538'" />
            <rect v-for="l in 3" :key="l" x="20" :y="thumbY(t - 1) + 9 + l * 4" width="32" height="1.5" fill="#d6d9dc" />
          </g>

          <!-- Current page -->
          <rect x="66" y="39" width="328" height="187" fill="#dadce0" />
          <rect x="96" y="46" width="268" height="174" fill="#ffffff" class="menu-shadow" />
          <rect x="110" y="56" width="110" height="5" rx="2" fill="#d6d9dc" />
          <rect x="110" y="70" width="240" height="14" fill="#8A1538" />
          <text
            v-for="(head, i) in preview.headers"
            :key="`ph${head}`"
            :x="pdfColX(i) + 3"
            y="79.5"
            class="head-text"
          >{{ head }}</text>
          <g v-for="(row, r) in preview.rows" :key="`pr${r}`">
            <line x1="110" :y1="98 + r * 14" x2="350" :y2="98 + r * 14" stroke="#e3e5e8" stroke-width=".6" />
            <text
              v-for="(cell, c) in row"
              :key="`pc${c}`"
              :x="pdfColX(c) + 3"
              :y="94 + r * 14"
              class="cell-text"
            >{{ cell }}</text>
          </g>
          <rect v-for="l in 6" :key="`pl${l}`" x="110" :y="130 + l * 13" width="240" height="4" rx="2" fill="#f1f3f4" />

          <!-- Step 1: print to PDF -->
          <g class="layer" :class="{ 'layer--on': current === 0 }">
            <rect x="120" y="66" width="180" height="112" rx="8" fill="#ffffff" stroke="#c9cdd2" class="menu-shadow" />
            <text x="210" y="84" class="dialog-title">Print</text>
            <g v-for="(opt, i) in printOptions" :key="opt[0]">
              <text x="134" :y="104 + i * 20" class="menu-item">{{ opt[0] }}</text>
              <rect x="200" :y="94 + i * 20" width="86" height="14" rx="3" fill="#f1f3f4" stroke="#c9cdd2" />
              <text x="205" :y="104 + i * 20" class="dialog-input">{{ opt[1] }}</text>
            </g>
            <rect x="238" y="156" width="48" height="16" rx="4" fill="#1a73e8" />
            <text x="262" y="167" class="dialog-btn">Save</text>
          </g>

          <!-- Step 2: columns that spilled onto later pages -->
          <g class="layer" :class="{ 'layer--on': current === 1 }">
            <rect x="66" y="39" width="328" height="187" fill="#dadce0" />
            <g v-for="(page, p) in spillPages" :key="page.label">
              <rect :x="82 + p * 166" y="50" width="130" height="144" fill="#ffffff" class="menu-shadow" />
              <rect :x="90 + p * 166" y="62" width="114" height="12" :fill="page.color" />
              <text :x="93 + p * 166" y="70.5" class="head-text">{{ page.label }}</text>
              <g v-for="r in 7" :key="r">
                <rect :x="90 + p * 166" :y="72 + r * 14" width="114" height="4" rx="2" fill="#e3e5e8" />
                <text v-if="p === 0" :x="80" :y="76 + r * 14" class="row-num">{{ r }}</text>
              </g>
            </g>
            <path d="M216 122 L242 122 M234 115 L242 122 L234 129" fill="none" stroke="#2f7d32" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
            <rect x="130" y="200" width="200" height="18" rx="5" fill="#2f7d32" />
            <text x="230" y="212" class="badge-text badge-text--sm">Same rows, more columns — keep in order</text>
          </g>

          <!-- Step 3: pages from another table -->
          <g class="layer" :class="{ 'layer--on': current === 2 }">
            <rect x="13" :y="thumbY(0) - 3" width="46" height="104" rx="4" fill="none" stroke="#2f7d32" stroke-width="1.8" />
            <g v-for="t in [3, 4]" :key="`x${t}`">
              <rect x="16" :y="thumbY(t)" width="40" height="28" fill="#b91c1c" opacity=".18" />
              <path :d="`M28 ${thumbY(t) + 6} L44 ${thumbY(t) + 22} M44 ${thumbY(t) + 6} L28 ${thumbY(t) + 22}`" stroke="#b91c1c" stroke-width="2.5" stroke-linecap="round" />
            </g>
            <rect x="80" y="150" width="150" height="40" rx="6" fill="#ffffff" stroke="#b91c1c" stroke-width="1.5" class="menu-shadow" />
            <text x="155" y="167" class="callout-text">A different table</text>
            <text x="155" y="180" class="callout-text callout-text--muted">Delete these pages first</text>
          </g>

          <!-- Step 4: selectable text, not a scan -->
          <g class="layer" :class="{ 'layer--on': current === 3 }">
            <rect x="110" y="86" width="240" height="12" fill="#1a73e8" opacity=".28" />
            <path d="M318 100 v14 M314 100 h8 M314 114 h8" stroke="#202124" stroke-width="1.3" stroke-linecap="round" />
            <rect x="216" y="150" width="134" height="24" rx="6" fill="#2f7d32" />
            <text x="283" y="166" class="badge-text badge-text--sm">Text selects — not a scan</text>
          </g>

          <!-- Step 5: upload -->
          <g class="layer" :class="{ 'layer--on': current === 4 }">
            <rect x="126" y="86" width="208" height="80" rx="8" fill="#ffffff" stroke="#b3261e" stroke-dasharray="5 4" />
            <path d="M230 142 L230 106 M218 118 L230 106 L242 118" fill="none" stroke="#b3261e" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
            <text x="230" y="158" class="dialog-title">Drop it below — AI turns on</text>
          </g>
        </svg>
      </div>

      <ol class="guide-steps">
        <li
          v-for="(step, i) in steps"
          :key="step.title"
          class="guide-step"
          :class="{ 'guide-step--current': i === current }"
        >
          <button type="button" class="guide-step-btn" @click="goTo(i)">
            <span class="guide-step-num">{{ i + 1 }}</span>
            <span>
              <strong class="guide-step-title">{{ step.title }}</strong>
              <span class="guide-step-text">{{ step.text }}</span>
            </span>
          </button>
        </li>
      </ol>

      <div class="guide-controls">
        <button type="button" class="guide-ctrl" @click="playing ? pause() : resume()">
          {{ playing ? 'Pause' : 'Play' }}
        </button>
        <button type="button" class="guide-ctrl" @click="goTo(0)">Restart</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import SvgIcon from './SvgIcon.vue';

const props = defineProps({
  sheetLabel: { type: String, default: 'the sheet you need' },
  // The scheduler's own tab name for that data, e.g. Tbl.VGC26Arrivals&Depart.
  sheetTab: { type: String, default: 'Tbl.…' },
  fileName: { type: String, default: 'LOG PMA Scheduler 2026 ….xlsx' },
  saveName: { type: String, default: 'extracted-sheet.xlsx' },
  // Headers and sample rows drawn in the mock, taken from the real sheet so
  // staff recognise their own columns.
  preview: {
    type: Object,
    default: () => ({ headers: [], rows: [] }),
  },
});

const STEP_MS = 4000;

const colLetters = ['A', 'B', 'C', 'D', 'E'];
const colWidths = [38, 74, 96, 86, 78];

function colX(index) {
  return 22 + colWidths.slice(0, index).reduce((sum, w) => sum + w, 0);
}

const pdfColWidths = [30, 40, 62, 56, 52];
const pdfThumbs = 5;
const pdfName = computed(() => props.saveName.replace(/\.\w+$/, '.pdf'));
const printOptions = [['Destination', 'Save as PDF'], ['Pages', 'All'], ['Layout', 'Landscape']];
const spillPages = [
  { label: 'Page 1 — first columns', color: '#8A1538' },
  { label: 'Page 4 — the rest', color: '#8A1538' },
];

function pdfColX(index) {
  return 110 + pdfColWidths.slice(0, index).reduce((sum, w) => sum + w, 0);
}

function thumbY(index) {
  return 46 + index * 35;
}

const modes = [
  { value: 'sheet', label: 'Spreadsheet', icon: 'columns' },
  { value: 'pdf', label: 'PDF', icon: 'audit' },
];

const mode = ref('sheet');

const sheetSteps = [
  {
    title: 'Find the right sheet',
    text: `Scroll the tabs to the one holding ${props.sheetLabel} — named like "${props.sheetTab}".`,
  },
  {
    title: 'Copy it to a new file',
    text: 'Right-click the tab → Move or Copy… → tick "Create a copy" and choose "(new book)".',
  },
  {
    title: 'Turn formulas into values',
    text: 'In the new file select all, copy, then Paste Special → Values. The scheduler\'s VLOOKUPs point at other sheets and break once copied out.',
  },
  {
    title: 'Save as .xlsx',
    text: 'Save with that sheet still selected — it is the only one this converter reads.',
  },
  {
    title: 'Upload it here',
    text: 'Drop the new file below. You will see the column mapping and every converted row before anything is saved.',
  },
];

const pdfSteps = [
  {
    title: 'Print the table to PDF',
    text: `Open the report holding ${props.sheetLabel} and use Print → Save as PDF, landscape. Export it straight from the source — never scan or photograph a printout.`,
  },
  {
    title: 'Keep every column',
    text: 'A wide table spills its extra columns onto later pages. That is fine — keep those pages, in their original order, and the AI joins them back onto the right rows.',
  },
  {
    title: 'Remove pages you don\'t need',
    text: 'Delete cover pages, dashboards and any other table. A second table in the same PDF is flagged for you rather than merged, and just slows the read down.',
  },
  {
    title: 'Check it is real text',
    text: 'Try selecting a word in the PDF. If you can\'t, it is a scanned image and the values will be unreliable. Keep it under 10 MB.',
  },
  {
    title: 'Upload it here',
    text: 'Drop the PDF below — AI switches on by itself. Reading takes 1–3 minutes; then check every row against the PDF before you download.',
  },
];

const steps = computed(() => (mode.value === 'pdf' ? pdfSteps : sheetSteps));

const open = ref(false);
const current = ref(0);
const playing = ref(true);
let timer = null;

function stop() {
  clearInterval(timer);
  timer = null;
}

function start() {
  stop();
  timer = setInterval(() => {
    current.value = (current.value + 1) % steps.value.length;
  }, STEP_MS);
}

function pause() {
  playing.value = false;
  stop();
}

function resume() {
  playing.value = true;
  start();
}

function goTo(index) {
  current.value = index;
  if (playing.value) start();
}

watch(open, (isOpen) => {
  current.value = 0;
  isOpen && playing.value ? start() : stop();
});

watch(mode, () => goTo(0));

onBeforeUnmount(stop);
</script>

<style scoped>
.guide { border: 1px solid var(--border); border-radius: 9px; overflow: hidden; }

.guide-toggle {
  display: flex; align-items: center; gap: 8px; width: 100%;
  padding: 10px 12px; cursor: pointer;
  background: var(--panel); border: 0; color: var(--ink);
  font-size: 12.5px; font-weight: 600; text-align: left;
}
.guide-toggle:hover { color: var(--accent); }
.guide-toggle-label { flex: 1 1 auto; }
.guide-chevron { transition: transform .15s; }
.guide-chevron--open { transform: rotate(180deg); }

.guide-body { padding: 14px; display: flex; flex-direction: column; gap: 12px; }
.guide-intro { margin: 0; font-size: 12.5px; line-height: 1.55; color: var(--ink3); }

.guide-modes {
  display: inline-flex; align-self: flex-start; gap: 2px; padding: 2px;
  border: 1px solid var(--border); border-radius: 8px; background: var(--panel);
}
.guide-mode {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 5px 12px; border: 0; border-radius: 6px; cursor: pointer;
  background: transparent; color: var(--ink3); font-size: 12px; font-weight: 600;
}
.guide-mode:hover { color: var(--ink); }
.guide-mode--active { background: var(--surface); color: var(--ink); box-shadow: 0 1px 2px rgba(0, 0, 0, .08); }

.guide-stage { display: flex; justify-content: center; }
/* The mock keeps Excel's own colours in both themes - it depicts another
   application, so theme tokens would make it unrecognisable. */
.guide-svg {
  width: 100%; max-width: 460px; height: auto;
  border-radius: 8px;
  box-shadow: 0 2px 10px rgba(0, 0, 0, .12);
}

.win { fill: #ffffff; stroke: #c9cdd2; }
.win-title { fill: #ffffff; font-size: 8px; font-weight: 600; text-anchor: middle; font-family: inherit; }

.col-letter { fill: #5f6368; font-size: 6.5px; text-anchor: middle; font-family: inherit; }
.row-num { fill: #5f6368; font-size: 6px; text-anchor: middle; font-family: inherit; }

.head-text { fill: #ffffff; font-size: 6.2px; font-weight: 700; font-family: inherit; }
.cell-text { fill: #202124; font-size: 6.4px; font-family: inherit; }

.grid-lines line { stroke: #e3e5e8; stroke-width: .6; }

.tab-text { fill: #5f6368; font-size: 6.4px; text-anchor: middle; font-family: inherit; }
.tab-text--active { fill: #217346; font-weight: 700; }
.tab-more { fill: #9aa0a6; font-size: 6px; font-family: inherit; }

.layer { opacity: 0; transition: opacity .35s ease; pointer-events: none; }
.layer--on { opacity: 1; }

.menu-shadow { filter: drop-shadow(0 3px 8px rgba(0, 0, 0, .22)); }
.menu-item { fill: #202124; font-size: 7.5px; font-family: inherit; }
.menu-item--hl { fill: #ffffff; font-weight: 600; }

.badge-text { fill: #ffffff; font-size: 9px; font-weight: 700; text-anchor: middle; font-family: inherit; }
.badge-text--sm { font-size: 7.5px; }
.callout-text { fill: #b91c1c; font-size: 8px; font-weight: 700; text-anchor: middle; font-family: inherit; }
.callout-text--muted { fill: #5f6368; font-weight: 500; font-size: 7px; }
.dialog-title { fill: #202124; font-size: 9px; font-weight: 700; text-anchor: middle; font-family: inherit; }
.dialog-input { fill: #202124; font-size: 7.5px; font-family: inherit; }
.dialog-btn { fill: #ffffff; font-size: 7.5px; font-weight: 600; text-anchor: middle; font-family: inherit; }

.guide-steps { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2px; }

.guide-step-btn {
  display: flex; align-items: flex-start; gap: 9px; width: 100%;
  padding: 6px 8px; cursor: pointer; text-align: left;
  background: transparent; border: 0; border-radius: 7px; color: inherit;
}
.guide-step-btn:hover { background: var(--panel); }
.guide-step--current .guide-step-btn { background: var(--panel); }

.guide-step-num {
  flex: 0 0 auto; width: 17px; height: 17px;
  display: grid; place-items: center; border-radius: 50%;
  background: var(--border); color: var(--ink3);
  font-size: 10px; font-weight: 700;
}
.guide-step--current .guide-step-num { background: var(--accent); color: #fff; }

.guide-step-title { display: block; font-size: 12px; color: var(--ink); }
.guide-step-text { display: block; font-size: 11.5px; line-height: 1.5; color: var(--ink3); }

.guide-controls { display: flex; gap: 10px; }
.guide-ctrl {
  background: none; border: 0; padding: 0; cursor: pointer;
  font-size: 11.5px; color: var(--accent);
}
.guide-ctrl:hover { text-decoration: underline; }
</style>
