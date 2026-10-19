// One-off: move the Plans tab (plan list) of Plans.vue into Components/Plans/PlanList.vue.
import { readFileSync, writeFileSync } from 'node:fs';

const path = 'resources/js/Pages/Plans.vue';
const lines = readFileSync(path, 'utf8').split('\n').map((l) => l.replace(/\r$/, ''));
const expect = (n, text) => {
  if (!lines[n - 1].includes(text)) throw new Error(`Line ${n} is "${lines[n - 1]}", expected "${text}"`);
};

expect(653, '<!-- Plans tab -->');
expect(654, `v-if="activeTab === 'plans'"`);
expect(1149, '<!-- Movements tab -->');
expect(5728, 'const selectedPlanIds');
expect(5729, 'const visiblePlans');
expect(5734, 'const allVisiblePlansSelected');
expect(5738, ');');
expect(5766, 'function togglePlanSelection');
expect(5772, 'function toggleSelectAllPlans');
expect(5778, 'function openBulkDeletePlans()');
expect(5874, 'const plansPageView');
expect(5898, '// Plan type filter');
expect(5904, 'function detectPlanType');
expect(5922, 'const plansByDate');
expect(5977, 'const planTypesCounts');
expect(5995, '});');

const take = (a, b) => lines.slice(a - 1, b);

const template = take(655, 1146)
  .map((l) => (l.startsWith('    ') ? l.slice(4) : l))
  .map((l) => l
    .replace(/showNewPlan = true/g, "emit('new-plan')")
    .replace(/selectPlan\(plan\.id\)/g, "emit('open', plan.id)")
    .replace(/duplicatePlan\(plan\)/g, "emit('duplicate', plan)")
    .replace(/editPlan\(plan\)/g, "emit('edit', plan)")
    .replace(/deletePlan\(plan\)/g, "emit('delete', plan)")
    .replace('@click="openBulkDeletePlans"', `@click="emit('bulk-delete', selectedVisiblePlans)"`)
    .replace('@click="selectedPlanIds = new Set()"', '@click="selectedIds = new Set()"')
    .replace(/selectedPlanIds\.has/g, 'selectedIds.has')
    .replace(/\ballPlans\b/g, 'plans'));

const script = [
  take(5874, 5874),
  take(5898, 5901),
  take(5903, 5919),
  take(5921, 5974),
  take(5976, 5995).map((l) => l.replace(/allPlans\.value/g, 'props.plans')),
  take(5729, 5738).map((l) => l.replace(/selectedPlanIds\.value/g, 'selectedIds.value')),
  take(5766, 5776).map((l) => l.replace(/selectedPlanIds\.value/g, 'selectedIds.value')),
].map((b) => b.join('\n')).join('\n\n').replace(/allPlans\.value/g, 'props.plans');

const component = `<template>
  <div style="flex: 1; overflow: auto">
${template.join('\n')}
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import Badge from '../Badge.vue';
import Button from '../Button.vue';
import SvgIcon from '../SvgIcon.vue';
import TableActions from '../TableActions.vue';

// The event's plans grouped by date, with search, type filters and bulk selection.
const props = defineProps({
  plans: { type: Array, default: () => [] },
  prerequisites: { type: Array, default: () => [] },
  canCreatePlan: { type: Boolean, default: false },
  prerequisiteHint: { type: String, default: '' },
});

// Plan ids ticked for bulk delete; kept by the page so it can clear them after deleting.
const selectedIds = defineModel('selectedIds', { type: Set, required: true });

const emit = defineEmits(['new-plan', 'open', 'duplicate', 'edit', 'delete', 'bulk-delete']);

${script}
</script>
`;

const remove = [[653, 1148], [5729, 5738], [5766, 5777], [5874, 5874], [5898, 5902], [5903, 5920], [5921, 5975], [5976, 5996]];
const removed = (n) => remove.some(([a, b]) => n >= a && n <= b);
const kept = [];
lines.forEach((line, i) => {
  const n = i + 1;
  if (n === 653) {
    kept.push(
      '      <PlanList',
      `        v-if="activeTab === 'plans'"`,
      '        v-model:selected-ids="selectedPlanIds"',
      '        :plans="allPlans"',
      '        :prerequisites="prerequisites"',
      '        :can-create-plan="canCreatePlan"',
      '        :prerequisite-hint="prerequisiteHint"',
      '        @new-plan="showNewPlan = true"',
      '        @open="selectPlan"',
      '        @duplicate="duplicatePlan"',
      '        @edit="editPlan"',
      '        @delete="deletePlan"',
      '        @bulk-delete="openBulkDeletePlans"',
      '      />',
      '',
    );
  }
  if (n === 5729) kept.push('// The plans the bulk-delete dialog acts on: those ticked and on screen when it opened.', 'const bulkDeletePlans = ref([]);');
  if (removed(n)) return;
  let out = line;
  if (n === 5778) out = 'function openBulkDeletePlans(plans) {\n  bulkDeletePlans.value = plans;';
  kept.push(out.replace(/selectedVisiblePlans/g, 'bulkDeletePlans'));
  if (line.startsWith('import ConflictsTab from')) kept.push('import PlanList from "../Components/Plans/PlanList.vue";');
});

writeFileSync('resources/js/Components/Plans/PlanList.vue', component.replace(/\n/g, '\r\n'));
writeFileSync(path, kept.join('\r\n').replace(/\r?\n/g, '\r\n'));
console.log(`Plans.vue: ${lines.length} -> ${kept.length} lines`);
