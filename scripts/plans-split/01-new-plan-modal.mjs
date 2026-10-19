// One-off: move the New Plan dialog out of Plans.vue into
// Components/Plans/NewPlanModal.vue, and the shared date/code formatters into
// Composables/planFormat.js. Line numbers are for Plans.vue as of 2026-10-17;
// the script refuses to run if the boundary lines don't match.
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';

const path = 'resources/js/Pages/Plans.vue';
const lines = readFileSync(path, 'utf8').split('\n');
const at = (n) => lines[n - 1].replace(/\r$/, '');
const expect = (n, text) => {
  if (!at(n).includes(text)) throw new Error(`Line ${n} is "${at(n)}", expected "${text}"`);
};

expect(3663, '<teleport to="body">');
expect(3665, 'v-if="showNewPlan"');
expect(5229, '<!-- Edit Plan Modal -->');
expect(8302, 'const formatMovementCode');
expect(8328, 'const newPlanMode');
expect(8355, 'const matchDuplicateCheckLoading');
expect(8499, 'function handleTeamChange');
expect(8544, 'watch(showNewPlan');
expect(10152, 'const selectedNewPlanTemplate');
expect(10195, 'function describeKickoffOffset');
expect(10493, 'function previewLegTime');
expect(10563, 'function formatDateTime');
expect(10599, 'function formatDate');
expect(10641, 'function toggleTeamFlightExpansion');
expect(10712, '// Check for duplicate movements');
expect(11269, 'function createMatchPlans');
expect(11357, 'function addTeamToPlan');

const take = (a, b) => lines.slice(a - 1, b).map((l) => l.replace(/\r$/, ''));
const dedent = (block, n) => block.map((l) => (l.startsWith(' '.repeat(n)) ? l.slice(n) : l.trimStart()));

// --- Shared formatters -------------------------------------------------------
const formatters = [
  '// Plan and movement formatting shared by the Planning page and its dialogs.',
  '',
  ...take(8301, 8302).map((l) => l.replace(/^const /, 'export const ')),
  '',
  ...take(10563, 10639).map((l) => l.replace(/^function /, 'export function ')),
  '',
];

// --- New Plan dialog ---------------------------------------------------------
const template = dedent(take(3664, 5227), 2)
  .map((l) => l.replace(/\(showNewPlan = false\)/g, "emit('close')").replace(/showNewPlan = false/g, "emit('close')"));

const scriptBlocks = [
  take(8328, 8355),
  take(8496, 8521),
  take(10152, 10175),
  take(10195, 10519),
  take(10641, 10656),
  take(10712, 11355),
];
const script = scriptBlocks
  .map((block) => block.join('\n'))
  .join('\n\n')
  .replace(/showNewPlan\.value = false;/g, "emit('close');");

const component = [
  '<template>',
  ...template,
  '</template>',
  '',
  '<script setup>',
  "import { ref, computed, watch } from 'vue';",
  "import { router } from '@inertiajs/vue3';",
  "import axios from 'axios';",
  "import Button from '../Button.vue';",
  "import SvgIcon from '../SvgIcon.vue';",
  "import Badge from '../Badge.vue';",
  "import { formatDate, formatDateTime, formatMovementCode } from '../../Composables/planFormat';",
  '',
  '// Mounted only while open (v-if), so every opening starts from a clean form.',
  'const props = defineProps({',
  '  activeEvent: { type: Object, default: null },',
  '  teams: { type: Array, default: () => [] },',
  '  movementTemplates: { type: Array, default: () => [] },',
  '  matches: { type: Array, default: () => [] },',
  '  nextMovementNumber: { type: Number, default: 1 },',
  '});',
  '',
  "const emit = defineEmits(['close']);",
  '',
  script,
  '</script>',
  '',
];

// --- What stays in Plans.vue ------------------------------------------------
const remove = [
  [3664, 5227], [8301, 8302], [8328, 8355], [8496, 8521], [8542, 8560],
  [10152, 10175], [10195, 10519], [10563, 10639], [10641, 10656], [10712, 11355],
];
const removed = (n) => remove.some(([a, b]) => n >= a && n <= b);

const kept = [];
lines.forEach((raw, i) => {
  const n = i + 1;
  if (n === 3664) {
    kept.push(
      '      <NewPlanModal',
      '        v-if="showNewPlan"',
      '        :active-event="activeEvent"',
      '        :teams="props.teams"',
      '        :movement-templates="props.movementTemplates"',
      '        :matches="props.matches"',
      '        :next-movement-number="nextMovementNumber"',
      '        @close="showNewPlan = false"',
      '      />',
    );
  }
  if (removed(n)) return;
  kept.push(raw.replace(/\r$/, ''));
  if (raw.startsWith('import ConfirmModal from')) {
    kept.push('import NewPlanModal from "../Components/Plans/NewPlanModal.vue";');
    kept.push('import { formatDate, formatDateTime, formatMovementCode } from "../Composables/planFormat";');
  }
});

mkdirSync('resources/js/Components/Plans', { recursive: true });
writeFileSync('resources/js/Composables/planFormat.js', formatters.join('\r\n'));
writeFileSync('resources/js/Components/Plans/NewPlanModal.vue', component.join('\n').replace(/\r?\n/g, '\r\n'));
writeFileSync(path, kept.join('\r\n'));
console.log(`Plans.vue: ${lines.length} -> ${kept.length} lines; NewPlanModal.vue: ${component.length} lines`);
