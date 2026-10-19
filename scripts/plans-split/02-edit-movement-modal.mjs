// One-off: move the Edit Movement dialog markup out of Plans.vue into
// Components/Plans/EditMovementModal.vue (script part is written by hand).
// Refuses to run if the boundary lines don't match.
import { readFileSync, writeFileSync } from 'node:fs';

const path = 'resources/js/Pages/Plans.vue';
const lines = readFileSync(path, 'utf8').split('\n').map((l) => l.replace(/\r$/, ''));
const at = (n) => lines[n - 1];
const expect = (n, text) => {
  if (!at(n).includes(text)) throw new Error(`Line ${n} is "${at(n)}", expected "${text}"`);
};

expect(5408, '<!-- Edit Movement Modal -->');
expect(5411, 'v-if="showEditMovement"');
expect(5848, '</teleport>');
expect(5689, 'minmax(0, 1fr) minmax(0, 1fr)');
expect(5764, '+ Add another supervisor');
expect(5767, 'MATCH (OPTIONAL)');
expect(6707, 'useCrewBusy');
expect(6708, 'supervisorsFor, personLabel');
expect(6890, 'const showEditMovement');
expect(6891, 'const editingMovement');
expect(7125, '// Edit Movement form state');
expect(7150, 'const emMatchId');
expect(7685, 'const relevantMatches');
expect(8931, 'function editMovement(movement)');
expect(8979, 'function formatDateTimeForInput');
expect(8992, 'function submitEditMovement');
expect(9026, 'function deleteMovement');

const crewFields = [
  '            <CrewFields',
  '              v-model:crew="crew"',
  '              :vehicles="vehicles"',
  '              :drivers="drivers"',
  '              :supervisors="supervisorOptions"',
  '              :note="crewNote"',
  '            />',
];

const template = [
  ...lines.slice(5410 - 1, 5688 - 1),
  ...crewFields,
  ...lines.slice(5766 - 1, 5847),
]
  .filter((l) => !l.includes('v-if="showEditMovement"'))
  .map((l) => (l.startsWith('  ') ? l.slice(2) : l))
  .map((l) => l
    .replace(/\(showEditMovement = false\)/g, "emit('close')")
    .replace(/showEditMovement = false/g, "emit('close')")
    .replace(/editingMovement/g, 'movement'));

const component = readFileSync('scripts/plans-split/edit-movement-script.vue.part', 'utf8').replace(/\r\n/g, '\n');
writeFileSync(
  'resources/js/Components/Plans/EditMovementModal.vue',
  ['<template>', ...template, '</template>', '', component].join('\n').replace(/\n/g, '\r\n'),
);

const remove = [[5410, 5847], [6707, 6708], [6889, 6892], [7125, 7151], [7684, 7694], [8931, 9025]];
const removed = (n) => remove.some(([a, b]) => n >= a && n <= b);
const kept = [];
lines.forEach((line, i) => {
  const n = i + 1;
  if (n === 5410) {
    kept.push(
      '      <EditMovementModal',
      '        v-if="editingMovement"',
      '        :key="editingMovement.id"',
      '        :movement="editingMovement"',
      '        :plan-date="selectedPlanObj?.date ?? null"',
      '        :vehicles="props.vehicles"',
      '        :drivers="props.drivers"',
      '        :supervisors="props.supervisors"',
      '        :matches="props.matches"',
      '        @close="editingMovement = null"',
      '      />',
    );
  }
  if (n === 6889) kept.push('// The movement open in the Edit Movement dialog.', 'const editingMovement = ref(null);');
  if (n === 8931) {
    kept.push('function editMovement(movement) {', '  editingMovement.value = movement;', '}', '');
  }
  if (removed(n)) return;
  kept.push(line);
  if (line.startsWith('import NewPlanModal from')) kept.push('import EditMovementModal from "../Components/Plans/EditMovementModal.vue";');
});

writeFileSync(path, kept.join('\r\n'));
console.log(`Plans.vue: ${lines.length} -> ${kept.length} lines; EditMovementModal template: ${template.length} lines`);
