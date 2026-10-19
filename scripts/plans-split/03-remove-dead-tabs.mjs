// One-off: remove the Checkpoints and Templates tabs of Plans.vue. They were never
// reachable (the tabs list always filtered them out) and showed hard-coded mock
// data, along with the template preview dialog only they could open.
import { readFileSync, writeFileSync } from 'node:fs';

const path = 'resources/js/Pages/Plans.vue';
const lines = readFileSync(path, 'utf8').split('\n').map((l) => l.replace(/\r$/, ''));
const expect = (n, text) => {
  if (!lines[n - 1].includes(text)) throw new Error(`Line ${n} is "${lines[n - 1]}", expected "${text}"`);
};

expect(1951, '<!-- Checkpoints tab -->');
expect(2139, '<!-- Conflicts tab -->');
expect(2331, '<!-- Templates tab -->');
expect(2412, '</template>');
expect(5848, '<!-- Template Preview modal -->');
expect(5851, 'v-if="showPreviewModal && selectedTemplate"');
expect(6043, '<!-- Add Team to Plan Modal -->');
expect(6659, 'const showPreviewModal');
expect(6660, 'const selectedTemplate');
expect(6943, '// Mock data for tabs');
expect(6950, '];');
expect(6957, 'const templates = [');
expect(7134, 'const cpTypeTone');
expect(7142, 'const sevTone');
expect(8787, 'function openPreviewModal');
expect(8797, 'function openNewTeamPlan');

const remove = [[1951, 2138], [2330, 2411], [5848, 5848], [5850, 6042], [6659, 6660], [6943, 6951], [6957, 7141], [8787, 8796]];
const kept = lines.filter((_, i) => !remove.some(([a, b]) => i + 1 >= a && i + 1 <= b));

writeFileSync(path, kept.join('\r\n'));
console.log(`Plans.vue: ${lines.length} -> ${kept.length} lines`);
