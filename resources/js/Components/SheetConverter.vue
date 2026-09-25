<template>
  <div>
    <p class="tool-blurb">
      {{ blurb }}
      Nothing is saved — review the result, download the file, then import it as usual.
    </p>

    <!-- Step 1: pick a file -->
    <div class="card">
      <prepare-sheet-guide
        :sheet-label="isMatches ? 'the fixtures' : 'the arrivals and departures'"
        :sheet-tab="isMatches ? 'Tbl.…Fixtures+Results' : 'Tbl.…Arrivals&Depart'"
        :save-name="isMatches ? 'matches-sheet.xlsx' : 'teams-sheet.xlsx'"
        :preview="sheetPreview"
        class="guide-slot"
      />

      <div
        class="dropzone"
        :class="{ 'dropzone--active': dragging, 'dropzone--filled': !!file }"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="onDrop"
        @click="$refs.fileInput.click()"
      >
        <svg-icon name="upload" :size="24" />
        <div v-if="file" class="dropzone-file">{{ file.name }}</div>
        <div v-else class="dropzone-hint">Drop a spreadsheet or PDF here, or click to browse (.xlsx, .xls, .csv, .pdf)</div>
      </div>
      <input ref="fileInput" type="file" class="sr-only" accept=".xlsx,.xls,.csv,.txt,.pdf" @change="onFileSelected" />

      <div class="card-actions">
        <label v-if="canUseAi" class="ai-toggle">
          <input v-model="useAi" type="checkbox" />
          <span>
            Use AI to match unknown columns
            <span class="ai-toggle-hint">Only runs when the built-in column names don't fit the file. Always required for a PDF.</span>
          </span>
        </label>
        <span v-else class="ai-toggle-hint">AI column matching needs the <code>ai.use</code> permission.</span>

        <Button variant="primary" size="md" :processing="converting" :disabled="!file" @click="convert">
          Convert
        </Button>
      </div>

      <div v-if="error" class="alert alert--error">{{ error }}</div>
    </div>

    <Modal :show="progressOpen" :closeable="progressState !== 'running'" max-width="520px" @close="closeProgress">
      <template #title>{{ progressTitle }}</template>

      <div class="progress-file">
        <svg-icon :name="runIsPdf ? 'audit' : 'columns'" :size="16" />
        <span>{{ runFileName }}</span>
      </div>

      <div
        class="progress-track"
        role="progressbar"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-valuenow="Math.round(progress)"
        :aria-valuetext="currentStage?.label"
      >
        <div
          class="progress-fill"
          :class="{ 'progress-fill--done': progressState === 'done', 'progress-fill--error': progressState === 'error' }"
          :style="{ width: `${progress}%` }"
        />
      </div>
      <div class="progress-meta">
        <span>{{ Math.round(progress) }}%</span>
        <span>{{ formatElapsed(elapsed) }} elapsed<template v-if="progressState === 'running'"> · {{ runIsPdf ? 'a PDF usually takes 1–3 minutes' : 'usually a few seconds' }}</template></span>
      </div>

      <ol class="stage-list">
        <li
          v-for="(stage, i) in runStages"
          :key="stage.label"
          class="stage"
          :class="`stage--${stageStatus(i)}`"
        >
          <span class="stage-icon">
            <svg-icon v-if="stageStatus(i) === 'done'" name="check" :size="13" :stroke-width="2.4" />
            <svg-icon v-else-if="stageStatus(i) === 'error'" name="x" :size="13" :stroke-width="2.4" />
            <span v-else-if="stageStatus(i) === 'active'" class="stage-spinner" />
          </span>
          <span class="stage-text">
            <span class="stage-label">{{ stage.label }}</span>
            <span v-if="stageStatus(i) === 'active' || stageStatus(i) === 'error'" class="stage-detail">{{ stage.detail }}</span>
          </span>
        </li>
      </ol>

      <div v-if="progressState === 'done'" class="alert alert--ok">
        Read <strong>{{ result?.rows.length }}</strong> {{ rowNoun }}{{ result?.rows.length === 1 ? '' : 's' }}.
        <template v-if="runIsPdf">The AI read these values from the PDF - check them against the document before you download.</template>
        <template v-else>Review the column mapping and rows before you download.</template>
      </div>
      <div v-else-if="progressState === 'error'" class="alert alert--error">{{ error }}</div>

      <template #footer>
        <Button v-if="progressState === 'running'" variant="secondary" size="sm" @click="cancelConvert">Cancel</Button>
        <Button v-else-if="progressState === 'error'" variant="secondary" size="sm" @click="closeProgress">Close</Button>
        <Button v-else variant="primary" size="sm" autofocus @click="closeProgress">Review result</Button>
      </template>
    </Modal>

    <!-- Step 2: review -->
    <template v-if="result">
      <div ref="resultEl" class="card">
        <div class="card-head">
          <h2 class="card-title">{{ isPdfResult ? 'What the AI read from the PDF' : 'Column mapping' }}</h2>
          <div class="chips">
            <span v-if="!isPdfResult" class="chip">Header row {{ result.headerRow }}</span>
            <span class="chip" :class="result.usedAi ? 'chip--ai' : 'chip--plain'">
              {{ pdfOrMatchLabel }}
            </span>
          </div>
        </div>

        <div v-if="isPdfResult" class="alert alert--warn">
          A PDF has no columns to match, so the AI read the values themselves rather than
          just naming the columns. <strong>Check every row below against the PDF before you
          import it</strong> — especially dates, times and passenger counts.
        </div>

        <div class="table-scroll">
          <table class="data-table">
            <thead>
              <tr>
                <th>{{ isPdfResult ? 'Source' : 'Source column' }}</th>
                <th>{{ isPdfResult ? 'Rows filled' : 'Source header' }}</th>
                <th>Template field</th>
                <th>Matched by</th>
                <th>{{ isPdfResult ? 'Coverage' : 'Confidence' }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(map, i) in result.mapping" :key="i">
                <td class="mono">{{ map.column }}</td>
                <td>{{ map.header }}</td>
                <td class="mono">{{ map.field }}</td>
                <td>
                  <span class="tag" :class="map.source === 'ai' ? 'tag--ai' : 'tag--alias'">{{ map.source }}</span>
                </td>
                <td>
                  <span v-if="map.confidence" class="tag" :class="`tag--${map.confidence}`">{{ map.confidence }}</span>
                  <span v-else class="muted">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="result.unmappedFields.length" class="alert alert--warn">
          {{ isPdfResult ? 'Nothing was found in the PDF for' : 'No source column found for' }}:
          <strong>{{ result.unmappedFields.join(', ') }}</strong>. These will be blank.
        </div>

        <div v-if="result.aiNotes" class="ai-notes">
          <div class="ai-notes-head"><svg-icon name="ai" :size="15" /> What the AI flagged for you to check</div>
          <p class="ai-notes-body">{{ result.aiNotes }}</p>
        </div>
      </div>

      <div class="card">
        <div class="card-head">
          <h2 class="card-title">Converted rows <span class="muted">({{ result.rows.length }})</span></h2>
          <Button variant="primary" size="md" :processing="downloading" @click="download">
            <svg-icon name="download" :size="15" /> Download import file
          </Button>
        </div>

        <div class="table-scroll table-scroll--tall">
          <table class="data-table">
            <thead>
              <tr><th v-for="h in result.headers" :key="h">{{ h }}</th></tr>
            </thead>
            <tbody>
              <tr v-for="(row, i) in result.rows" :key="i">
                <td v-for="(cell, c) in row" :key="c" :class="{ muted: !cell }">{{ cell || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <p class="footnote">{{ importHint }}</p>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onUnmounted } from 'vue';
import Button from './Button.vue';
import Modal from './Modal.vue';
import SvgIcon from './SvgIcon.vue';
import PrepareSheetGuide from './PrepareSheetGuide.vue';

const props = defineProps({
  type: { type: String, required: true },
  title: { type: String, default: 'Sheet Converter' },
  blurb: { type: String, default: '' },
  importHint: { type: String, default: '' },
  canUseAi: { type: Boolean, default: false },
  headers: { type: Array, default: () => [] },
});

const file = ref(null);
const useAi = ref(props.canUseAi);
const dragging = ref(false);
const converting = ref(false);
const downloading = ref(false);
const error = ref('');
const result = ref(null);
const resultEl = ref(null);

const progressOpen = ref(false);
const progressState = ref('running'); // 'running' | 'done' | 'error'
const progress = ref(0);
const elapsed = ref(0);
const runIsPdf = ref(false);
const runFileName = ref('');
const runStages = ref([]);
let runStartedAt = 0;
let ticker = null;
let abortController = null;

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const isMatches = computed(() => props.type === 'matches');
const isPdfResult = computed(() => result.value?.sourceKind === 'pdf');

const pdfOrMatchLabel = computed(() => {
  if (isPdfResult.value) return 'Read from the PDF by AI';
  return result.value?.usedAi ? 'Matched by column names + AI' : 'Matched by column names';
});

// Real column names and sample values from the scheduler's own sheets, so the
// guide's mock looks like the file the user is about to open.
const sheetPreview = computed(() => isMatches.value
  ? {
      headers: ['Match No.', 'Match Date', 'Venue', 'Match Round', 'PMA1'],
      rows: [
        ['VGC26-001', '30/10/2026', 'GRAND HAMAD', 'MD-R1', 'QAT-V'],
        ['VGC26-002', '30/10/2026', 'GRAND HAMAD', 'MD-R1', 'UAE-V'],
        ['VGC26-003', '30/10/2026', 'AL WAKRAH', 'MD-R1', 'KSA-V'],
      ],
    }
  : {
      headers: ['GROUP', 'PMA', 'TEAM', 'ARRIVAL AIRPORT', 'FLIGHT No.'],
      rows: [
        ['B3', 'BHR-V', 'BAHRAIN-V', 'HIA', 'QR1103'],
        ['A2', 'IRQ-V', 'IRAQ-V', 'HIA', 'QR445'],
        ['A1', 'KSA-V', 'SAUDI ARABIA-V', 'HIA', 'QR1165'],
      ],
    });

function onFileSelected(event) {
  setFile(event.target.files?.[0]);
}

function onDrop(event) {
  dragging.value = false;
  setFile(event.dataTransfer?.files?.[0]);
}

// Matches the server's own 'max:10240' rule, so an oversized file is named
// here rather than coming back as a generic upload failure.
const MAX_BYTES = 10 * 1024 * 1024;

function setFile(picked) {
  if (!picked) return;

  result.value = null;

  if (picked.size > MAX_BYTES) {
    file.value = null;
    error.value = `That file is ${(picked.size / 1024 / 1024).toFixed(1)} MB. `
      + 'The limit is 10 MB — copy just the sheet you need into a new file (see the guide above).';
    return;
  }

  file.value = picked;
  error.value = '';

  // A PDF can only be read by the extraction agent, so the toggle isn't optional
  // there - flip it rather than letting the server reject the upload.
  if (picked.name.toLowerCase().endsWith('.pdf') && props.canUseAi) {
    useAi.value = true;
  }
}

const rowNoun = computed(() => (isMatches.value ? 'match' : 'team'));
const keyLabel = computed(() => (isMatches.value ? 'Match Number' : 'Trigram'));

// The server reports nothing until it is finished, so each stage's `until` is
// where it sits on an estimated timeline, not a measured step.
function buildStages(isPdf, withAi) {
  if (isPdf) {
    return [
      { label: 'Uploading the PDF', detail: 'Sending the file to the server.', until: 3 },
      { label: 'Handing the PDF to the AI', detail: 'The whole document goes to the model in one request.', until: 8 },
      { label: 'Reading every page', detail: 'The AI reads the table on each page and joins columns that were printed onto later pages back onto their rows.', until: 70 },
      { label: `Writing out one row per ${rowNoun.value}`, detail: 'Dates are written as YYYY-MM-DD and times as HH:MM; anything unclear is noted for you to check.', until: 93 },
      { label: 'Checking the result', detail: `Dropping rows without a ${keyLabel.value} and re-checking every date and time the AI returned.`, until: 100 },
    ];
  }

  return [
    { label: 'Uploading the file', detail: 'Sending the spreadsheet to the server.', until: 15 },
    { label: 'Finding the header row', detail: 'Skipping title and grouping rows to find the row that names the columns.', until: 35 },
    withAi
      ? { label: 'Matching columns', detail: 'Known column names are matched first; the AI is asked only about the ones left over.', until: 80 }
      : { label: 'Matching columns', detail: 'Matching each column against the built-in list of known column names.', until: 60 },
    { label: 'Converting rows', detail: 'Reading every row and converting dates, times and countries into the template format.', until: 100 },
  ];
}

const currentStageIndex = computed(() => {
  const index = runStages.value.findIndex((stage) => progress.value < stage.until);
  return index === -1 ? runStages.value.length - 1 : index;
});

const currentStage = computed(() => runStages.value[currentStageIndex.value]);

const progressTitle = computed(() => ({
  running: runIsPdf.value ? 'Reading the PDF…' : 'Converting the sheet…',
  done: 'Conversion complete',
  error: 'Conversion failed',
}[progressState.value]));

function stageStatus(i) {
  if (progressState.value === 'done' || i < currentStageIndex.value) return 'done';
  if (i > currentStageIndex.value) return 'pending';
  return progressState.value === 'error' ? 'error' : 'active';
}

function formatElapsed(seconds) {
  const s = Math.floor(seconds);
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

function startProgress() {
  runIsPdf.value = file.value.name.toLowerCase().endsWith('.pdf');
  runFileName.value = file.value.name;
  runStages.value = buildStages(runIsPdf.value, useAi.value);
  progressState.value = 'running';
  progress.value = 0;
  elapsed.value = 0;
  progressOpen.value = true;
  runStartedAt = Date.now();

  const expectedSeconds = runIsPdf.value ? 120 : (useAi.value ? 12 : 3);

  // Eases toward 95% and never reaches it, so the bar keeps moving on a slow run
  // but only the real response can finish it.
  ticker = setInterval(() => {
    elapsed.value = (Date.now() - runStartedAt) / 1000;
    progress.value = 95 * (1 - Math.exp(-2.5 * elapsed.value / expectedSeconds));
  }, 250);
}

function stopProgress(state) {
  clearInterval(ticker);
  ticker = null;
  elapsed.value = (Date.now() - runStartedAt) / 1000;
  progressState.value = state;
  if (state === 'done') progress.value = 100;
}

async function closeProgress() {
  if (progressState.value === 'running') return;

  progressOpen.value = false;

  if (progressState.value === 'done') {
    await nextTick();
    resultEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

function cancelConvert() {
  abortController?.abort();
}

onUnmounted(() => {
  clearInterval(ticker);
  abortController?.abort();
});

async function convert() {
  if (!file.value || converting.value) return;

  converting.value = true;
  error.value = '';
  result.value = null;
  abortController = new AbortController();
  startProgress();

  try {
    const body = new FormData();
    body.append('file', file.value);
    body.append('use_ai', useAi.value ? '1' : '0');

    const response = await fetch(`/utilities/converters/${props.type}/preview`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
      body,
      signal: abortController.signal,
    });

    const data = await response.json();

    if (!response.ok || data.ok === false) {
      error.value = data.message || 'Could not convert that file.';
      stopProgress('error');
      return;
    }

    result.value = data;
    stopProgress('done');
  } catch (e) {
    if (e.name === 'AbortError') {
      stopProgress('error');
      progressOpen.value = false;
      return;
    }

    console.error('Sheet conversion failed:', e);
    error.value = 'Could not reach the server. Please try again.';
    stopProgress('error');
  } finally {
    converting.value = false;
    abortController = null;
  }
}

function filenameFrom(response) {
  const header = response.headers.get('Content-Disposition') ?? '';

  const encoded = header.match(/filename\*=UTF-8''([^;]+)/i);
  if (encoded) return decodeURIComponent(encoded[1]);

  const plain = header.match(/filename="?([^";]+)"?/i);
  return plain ? plain[1] : (result.value?.suggestedFilename || 'converted.xlsx');
}

async function download() {
  if (!result.value || downloading.value) return;
  downloading.value = true;

  try {
    const response = await fetch(`/utilities/converters/${props.type}/download`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrf(),
        Accept: 'application/json',
      },
      body: JSON.stringify({ rows: result.value.rows }),
    });

    if (!response.ok) {
      error.value = 'Could not build the file. Please try converting again.';
      return;
    }

    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    // Taken from the response, not the preview: the active event can be switched
    // after converting, and the server names the file for the current one.
    link.download = filenameFrom(response);
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  } catch (e) {
    console.error('Download failed:', e);
    error.value = 'Could not download the file. Please try again.';
  } finally {
    downloading.value = false;
  }
}
</script>

<style scoped>
.tool-blurb { margin: 0 0 16px; font-size: 13px; line-height: 1.55; color: var(--ink3); max-width: 780px; }

.card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; padding: 18px; margin-bottom: 16px;
}
.card-head {
  display: flex; align-items: center; justify-content: space-between;
  gap: 12px; flex-wrap: wrap; margin-bottom: 14px;
}
.card-title { font-size: 15px; font-weight: 650; color: var(--ink); margin: 0; }

.guide-slot { margin-bottom: 14px; }

.dropzone {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 8px; padding: 28px; cursor: pointer;
  border: 1.5px dashed var(--border); border-radius: 9px;
  color: var(--ink3); text-align: center;
  transition: border-color .15s, background .15s;
}
.dropzone:hover, .dropzone--active { border-color: var(--accent); background: var(--panel); }
.dropzone--filled { border-style: solid; }
.dropzone-file { font-size: 13.5px; font-weight: 600; color: var(--ink); }
.dropzone-hint { font-size: 12.5px; }

.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }

.card-actions {
  display: flex; align-items: center; justify-content: space-between;
  gap: 16px; flex-wrap: wrap; margin-top: 14px;
}
.ai-toggle { display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: var(--ink); cursor: pointer; }
.ai-toggle input { margin-top: 2px; }
.ai-toggle-hint { display: block; font-size: 11.5px; color: var(--ink3); }

.chips { display: flex; gap: 6px; flex-wrap: wrap; }
.chip {
  font-size: 11.5px; padding: 3px 9px; border-radius: 999px;
  background: var(--panel); color: var(--ink3); border: 1px solid var(--border);
}
.chip--ai { color: var(--accent); border-color: var(--accent); }

.table-scroll { overflow: auto; border: 1px solid var(--border); border-radius: 8px; }
.table-scroll--tall { max-height: 460px; }

.data-table { width: 100%; border-collapse: collapse; font-size: 12.5px; white-space: nowrap; }
.data-table th {
  position: sticky; top: 0; z-index: 1;
  text-align: left; font-weight: 650; color: var(--ink3);
  background: var(--panel); padding: 8px 10px; border-bottom: 1px solid var(--border);
}
.data-table td { padding: 7px 10px; border-bottom: 1px solid var(--border); color: var(--ink); }
.data-table tr:last-child td { border-bottom: 0; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.muted { color: var(--ink3); }

.tag { font-size: 11px; padding: 2px 7px; border-radius: 4px; background: var(--panel); color: var(--ink3); }
.tag--ai { background: color-mix(in srgb, var(--accent) 15%, transparent); color: var(--accent); }
.tag--high { color: #15803d; background: rgba(21, 128, 61, .12); }
.tag--medium { color: #b45309; background: rgba(180, 83, 9, .12); }
.tag--low { color: #b91c1c; background: rgba(185, 28, 28, .12); }

.alert { margin-top: 14px; padding: 10px 12px; border-radius: 8px; font-size: 12.5px; }
.alert--error { background: rgba(185, 28, 28, .1); color: #b91c1c; }
.alert--warn { background: rgba(180, 83, 9, .1); color: #b45309; }
.alert--ok { background: rgba(21, 128, 61, .1); color: #15803d; }

.progress-file {
  display: flex; align-items: center; gap: 8px; margin-bottom: 14px;
  font-size: 13px; font-weight: 600; color: var(--ink); word-break: break-all;
}
.progress-track { height: 8px; border-radius: 999px; background: var(--panel); border: 1px solid var(--border); overflow: hidden; }
.progress-fill { height: 100%; background: var(--accent); border-radius: 999px; transition: width .25s linear; }
.progress-fill--done { background: #15803d; }
.progress-fill--error { background: #b91c1c; }
.progress-meta {
  display: flex; justify-content: space-between; gap: 12px; margin-top: 6px;
  font-size: 11.5px; color: var(--ink3); font-variant-numeric: tabular-nums;
}

.stage-list { list-style: none; margin: 16px 0 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.stage { display: flex; align-items: flex-start; gap: 10px; font-size: 13px; }
.stage-icon {
  flex: none; display: inline-flex; align-items: center; justify-content: center;
  width: 20px; height: 20px; border-radius: 50%;
  border: 1.5px solid var(--border); color: var(--ink3); background: var(--surface);
}
.stage--done .stage-icon { border-color: #15803d; background: #15803d; color: #fff; }
.stage--active .stage-icon { border-color: var(--accent); }
.stage--error .stage-icon { border-color: #b91c1c; background: #b91c1c; color: #fff; }
.stage-spinner {
  width: 10px; height: 10px; border-radius: 50%;
  border: 2px solid color-mix(in srgb, var(--accent) 30%, transparent); border-top-color: var(--accent);
  animation: stage-spin .7s linear infinite;
}
@keyframes stage-spin { to { transform: rotate(360deg); } }
.stage-text { display: flex; flex-direction: column; gap: 2px; padding-top: 1px; }
.stage-label { color: var(--ink3); }
.stage--done .stage-label, .stage--active .stage-label { color: var(--ink); }
.stage--active .stage-label { font-weight: 600; }
.stage-detail { font-size: 12px; line-height: 1.5; color: var(--ink3); }

.ai-notes { margin-top: 14px; padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--panel); }
.ai-notes-head {
  display: flex; align-items: center; gap: 6px;
  font-size: 12px; font-weight: 650; color: var(--ink); margin-bottom: 6px;
}
.ai-notes-body { margin: 0; font-size: 12.5px; line-height: 1.6; color: var(--ink3); white-space: pre-wrap; }

.footnote { margin: 12px 0 0; font-size: 12px; color: var(--ink3); }
</style>
