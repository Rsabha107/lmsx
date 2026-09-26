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
          <!-- Excel window, faded behind the menu and dialog -->
          <rect x="6" y="6" width="388" height="220" rx="7" class="win" />
          <path d="M6 13a7 7 0 0 1 7-7h374a7 7 0 0 1 7 7v13H6z" fill="#217346" />
          <circle cx="18" cy="16" r="3" fill="#ffffff" opacity=".55" />
          <circle cx="28" cy="16" r="3" fill="#ffffff" opacity=".55" />
          <circle cx="38" cy="16" r="3" fill="#ffffff" opacity=".55" />
          <text x="200" y="20" class="win-title">{{ fileName }}</text>
          <g opacity=".35">
            <rect x="6" y="26" width="388" height="13" fill="#f1f3f4" />
            <rect x="22" y="48" width="372" height="18" fill="#8A1538" />
            <rect
              v-for="r in 8"
              :key="`bg${r}`"
              x="22"
              :y="66 + (r - 1) * 18"
              width="372"
              height="18"
              :fill="r % 2 ? '#ffffff' : '#f7f8f9'"
            />
          </g>

          <!-- Step 1: File → Save as PDF -->
          <g class="layer" :class="{ 'layer--on': current === 0 }">
            <rect x="6" y="26" width="112" height="200" fill="#217346" />
            <text x="20" y="44" class="menu-item menu-item--hl">File</text>
            <line x1="14" y1="50" x2="110" y2="50" stroke="#ffffff" stroke-opacity=".3" />
            <g v-for="(item, i) in fileMenu" :key="item">
              <rect v-if="i === pdfMenuIndex" x="10" :y="55 + i * 20" width="104" height="17" rx="3" fill="#ffffff" />
              <text
                x="20"
                :y="66.5 + i * 20"
                class="menu-item"
                :class="i === pdfMenuIndex ? 'file-item--sel' : 'menu-item--hl'"
              >{{ item }}</text>
            </g>
            <rect x="7" :y="52 + pdfMenuIndex * 20" width="110" height="23" rx="5" fill="none" stroke="#f59e0b" stroke-width="2.5" />
          </g>

          <!-- Steps 2–4: the Save as PDF dialog -->
          <g class="layer" :class="{ 'layer--on': current >= 1 }">
            <rect x="40" y="32" width="320" height="190" rx="8" fill="#ffffff" stroke="#c9cdd2" class="menu-shadow" />
            <text x="200" y="48" class="dialog-title">Save as PDF</text>

            <text x="54" y="64" class="pane-label">Sheets in Excel</text>
            <rect x="54" y="68" width="112" height="66" rx="3" fill="#ffffff" stroke="#c9cdd2" />
            <text v-for="(tab, i) in otherSheets" :key="`ws${i}`" x="61" :y="80 + i * 15" class="dialog-input">{{ tab }}</text>

            <rect x="174" y="84" width="52" height="15" rx="3" fill="#f1f3f4" stroke="#c9cdd2" />
            <text x="200" y="94.5" class="btn-text">Add »</text>
            <rect x="174" y="104" width="52" height="15" rx="3" fill="#f1f3f4" stroke="#c9cdd2" />
            <text x="200" y="114.5" class="btn-text">« Remove</text>

            <text x="234" y="64" class="pane-label">Sheets in PDF</text>
            <rect x="234" y="68" width="112" height="66" rx="3" fill="#ffffff" stroke="#c9cdd2" />
            <rect x="236" y="71" width="108" height="13" rx="2" fill="#dbe8f6" />
            <text x="241" y="80" class="dialog-input">{{ sheetTab }}</text>

            <line x1="54" y1="144" x2="346" y2="144" stroke="#e3e5e8" />
            <text x="54" y="158" class="pane-label">Conversion options</text>
            <rect x="56" y="164" width="9" height="9" rx="2" :fill="current >= 2 ? '#217346' : '#ffffff'" stroke="#9aa0a6" />
            <path v-if="current >= 2" d="M58 168.5 l2 2 l3.5 -4" fill="none" stroke="#ffffff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
            <text x="71" y="171.5" class="dialog-input">Fit worksheet to a single page</text>
            <rect x="56" y="180" width="9" height="9" rx="2" fill="#ffffff" stroke="#9aa0a6" />
            <text x="71" y="187.5" class="dialog-input">Fit to paper width</text>

            <rect x="222" y="198" width="50" height="16" rx="4" fill="#f1f3f4" stroke="#c9cdd2" />
            <text x="247" y="209" class="btn-text">Cancel</text>
            <rect x="278" y="198" width="68" height="16" rx="4" fill="#217346" />
            <text x="312" y="209" class="dialog-btn">Convert to PDF</text>
          </g>

          <!-- Step 2: move the sheet across -->
          <g class="layer" :class="{ 'layer--on': current === 1 }">
            <rect x="171" y="81" width="58" height="21" rx="5" fill="none" stroke="#f59e0b" stroke-width="2.5" />
            <rect x="233" y="69" width="114" height="17" rx="4" fill="none" stroke="#f59e0b" stroke-width="2" />
          </g>

          <!-- Step 3: fit to one page -->
          <g class="layer" :class="{ 'layer--on': current === 2 }">
            <rect x="52" y="160" width="152" height="17" rx="4" fill="none" stroke="#f59e0b" stroke-width="2.5" />
          </g>

          <!-- Step 4: convert -->
          <g class="layer" :class="{ 'layer--on': current === 3 }">
            <rect x="275" y="195" width="74" height="22" rx="5" fill="none" stroke="#f59e0b" stroke-width="2.5" />
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

const fileMenu = ['Info', 'New', 'Open', 'Save As', 'Save as PDF', 'Print', 'Export'];
const pdfMenuIndex = fileMenu.indexOf('Save as PDF');
const otherSheets = ['Home.Events', 'Rpt.…', 'Lkp.…', '+75 more'];

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
    title: 'Save as PDF from Excel',
    text: `Open the workbook holding ${props.sheetLabel} and go to File → Save as PDF.`,
  },
  {
    title: 'Move the sheet into the PDF',
    text: `Find "${props.sheetTab}" under Sheets in Excel and move it across to the Sheets in PDF pane. Leave every other sheet out.`,
  },
  {
    title: 'Fit the worksheet to one page',
    text: 'Under Conversion options, select "Fit worksheet to a single page" so no columns spill onto other pages.',
  },
  {
    title: 'Convert to PDF',
    text: 'Click Convert to PDF, then drop the file below — AI switches on by itself and takes 1–3 minutes to read it.',
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
.dialog-title { fill: #202124; font-size: 9px; font-weight: 700; text-anchor: middle; font-family: inherit; }
.dialog-input { fill: #202124; font-size: 7.5px; font-family: inherit; }
.dialog-btn { fill: #ffffff; font-size: 7.5px; font-weight: 600; text-anchor: middle; font-family: inherit; }
.btn-text { fill: #202124; font-size: 7px; text-anchor: middle; font-family: inherit; }
.pane-label { fill: #5f6368; font-size: 7px; font-weight: 600; font-family: inherit; }
.file-item--sel { fill: #217346; font-weight: 700; }

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
