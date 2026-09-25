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
        <div v-else class="dropzone-hint">Drop a spreadsheet here, or click to browse (.xlsx, .xls, .csv)</div>
      </div>
      <input ref="fileInput" type="file" class="sr-only" accept=".xlsx,.xls,.csv,.txt" @change="onFileSelected" />

      <div class="card-actions">
        <label v-if="canUseAi" class="ai-toggle">
          <input v-model="useAi" type="checkbox" />
          <span>
            Use AI to match unknown columns
            <span class="ai-toggle-hint">Only runs when the built-in column names don't fit the file.</span>
          </span>
        </label>
        <span v-else class="ai-toggle-hint">AI column matching needs the <code>ai.use</code> permission.</span>

        <Button variant="primary" size="md" :processing="converting" :disabled="!file" @click="convert">
          Convert
        </Button>
      </div>

      <div v-if="error" class="alert alert--error">{{ error }}</div>
    </div>

    <!-- Step 2: review -->
    <template v-if="result">
      <div class="card">
        <div class="card-head">
          <h2 class="card-title">Column mapping</h2>
          <div class="chips">
            <span class="chip">Header row {{ result.headerRow }}</span>
            <span class="chip" :class="result.usedAi ? 'chip--ai' : 'chip--plain'">
              {{ result.usedAi ? 'Matched by column names + AI' : 'Matched by column names' }}
            </span>
          </div>
        </div>

        <div class="table-scroll">
          <table class="data-table">
            <thead>
              <tr>
                <th>Source column</th>
                <th>Source header</th>
                <th>Template field</th>
                <th>Matched by</th>
                <th>Confidence</th>
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
          No source column found for: <strong>{{ result.unmappedFields.join(', ') }}</strong>. These will be blank.
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
import { ref, computed } from 'vue';
import Button from './Button.vue';
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

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const isMatches = computed(() => props.type === 'matches');

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
}

async function convert() {
  if (!file.value || converting.value) return;

  converting.value = true;
  error.value = '';
  result.value = null;

  try {
    const body = new FormData();
    body.append('file', file.value);
    body.append('use_ai', useAi.value ? '1' : '0');

    const response = await fetch(`/utilities/converters/${props.type}/preview`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
      body,
    });

    const data = await response.json();

    if (!response.ok || data.ok === false) {
      error.value = data.message || 'Could not convert that file.';
      return;
    }

    result.value = data;
  } catch (e) {
    console.error('Sheet conversion failed:', e);
    error.value = 'Could not reach the server. Please try again.';
  } finally {
    converting.value = false;
  }
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
    link.download = result.value.suggestedFilename || 'converted.xlsx';
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

.ai-notes { margin-top: 14px; padding: 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--panel); }
.ai-notes-head {
  display: flex; align-items: center; gap: 6px;
  font-size: 12px; font-weight: 650; color: var(--ink); margin-bottom: 6px;
}
.ai-notes-body { margin: 0; font-size: 12.5px; line-height: 1.6; color: var(--ink3); white-space: pre-wrap; }

.footnote { margin: 12px 0 0; font-size: 12px; color: var(--ink3); }
</style>
