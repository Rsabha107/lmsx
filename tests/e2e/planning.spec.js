import { test, expect } from '@playwright/test';
import { signIn, openNewPlan, gotoPlansList } from './helpers.js';

// Planning smoke tests: the flows the Plans.vue split must keep working.
// They run in order against one freshly seeded event (see E2eSeeder).
test.describe.configure({ mode: 'serial' });

const isoDate = (offsetDays) => {
  const d = new Date();
  d.setDate(d.getDate() + offsetDays);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

// Warnings that predate the split; anything else fails the test.
const KNOWN = [/custom validator check failed for prop "variant"[\s\S]*<Badge type="plan-type" variant="other"/];

test.beforeEach(async ({ page }) => {
  await signIn(page);

  // A component that lost a variable in the split shows up as a Vue warning or a page error.
  // Collected after sign-in: the Dashboard has its own known warnings.
  const problems = [];
  page.on('console', (msg) => {
    const text = msg.text();
    if ((msg.type() === 'error' || text.startsWith('[Vue warn]')) && !KNOWN.some((re) => re.test(text))) problems.push(text);
  });
  page.on('pageerror', (error) => problems.push(error.message));
  test.info().problems = problems;
});

test.afterEach(async () => {
  expect(test.info().problems, 'console errors and Vue warnings').toEqual([]);
});

test('single plan from a template', async ({ page }) => {
  const dialog = await openNewPlan(page);

  await dialog.getByLabel('Team', { exact: true }).selectOption({ label: 'Qatar (QAT-17)' });
  await dialog.getByLabel('Movement template').selectOption({ label: 'Team Arrival (MVT-ARR)' });
  await dialog.getByLabel('Date', { exact: true }).fill(isoDate(1));
  await expect(dialog.getByText('No conflicts: QAT-17 is free on this date.')).toBeVisible();
  await dialog.getByLabel('Plan name').fill('E2E single plan');
  await expect(dialog.getByText('1 plan for QAT-17 · 1 movement')).toBeVisible();
  await dialog.getByRole('button', { name: 'Create plan' }).click();

  await expect(dialog).toBeHidden();
  await expect(page.getByText('E2E single plan').first()).toBeVisible();
});

test('the same arrival again is blocked as a duplicate', async ({ page }) => {
  const dialog = await openNewPlan(page);
  await dialog.getByLabel('Team', { exact: true }).selectOption({ label: 'Qatar (QAT-17)' });
  await dialog.getByLabel('Movement template').selectOption({ label: 'Team Arrival (MVT-ARR)' });
  await dialog.getByLabel('Date', { exact: true }).fill(isoDate(1));

  await expect(dialog.getByText('Duplicate movement.')).toBeVisible();
  await expect(dialog.getByText('Resolve the conflict to continue')).toBeVisible();
  await expect(dialog.getByRole('button', { name: 'Create plan' })).toBeDisabled();
});

test('edit movement opens the team in the Event Teams dialog and stays open after saving', async ({ page }) => {
  await gotoPlansList(page);
  await page.getByText('E2E single plan').first().click();
  // Before generating, the movements toolbar (and the checklist) offer to refresh the movements' flights.
  await expect(page.getByRole('button', { name: /^Refresh flights \(1\)/ }).first()).toBeVisible();
  await page.getByTitle('Edit').first().click();
  const movement = page.locator('.modal').filter({ hasText: 'Update movement' });
  await expect(movement).toBeVisible();

  await movement.getByRole('button', { name: 'Edit team' }).click();
  const team = page.getByRole('dialog', { name: /^Edit team / });
  await expect(team.getByLabel('Team code')).toHaveValue('QAT-17');
  // Planning has no flight form or team delete.
  await expect(team.getByRole('button', { name: 'Delete team' })).toHaveCount(0);
  await expect(team.getByRole('button', { name: 'More flight details' })).toHaveCount(0);

  await team.getByLabel('Training ground').fill('E2E planning pitch');
  await team.getByLabel('Training date and time').fill('2030-01-06T10:00');
  await team.getByRole('button', { name: 'Save changes' }).click();
  await expect(team).toHaveCount(0);
  await expect(page.getByText(/^Team updated\./)).toBeVisible();
  await expect(movement).toBeVisible();
  await movement.getByRole('button', { name: 'Cancel' }).click();
});

test('the plan checklist shows where the plan stands and links each step', async ({ page }) => {
  await gotoPlansList(page);
  await expect(page.locator('.pl-prog-next').first()).toContainText('Next:');
  await page.getByText('E2E single plan').first().click();

  const checklist = page.getByRole('region', { name: 'Plan checklist' });
  const step = (label) => checklist.locator('.pc-step', { hasText: label });
  await expect(step('Team, date & template')).toContainText('Team Arrival');
  await expect(step('Review movements')).toContainText('1 movement');
  await expect(step('Assign crew')).toContainText('0 / 1 crewed');
  await expect(step('Generate jobs')).toContainText('0 / 1 generated');
  // Nothing is crewed yet, so there is nothing to generate.
  await expect(step('Generate jobs').getByRole('button', { name: 'Generate jobs' })).toBeDisabled();
  await expect(step('Assign crew').getByRole('link', { name: 'Open Crew Assignment' })).toHaveAttribute('href', `/crew-assignment?date=${isoDate(1)}`);

  await step('Resolve conflicts').getByRole('button', { name: /conflicts$/ }).click();
  await expect(page.locator('.ct-scope-btn--on')).toContainText('This plan');
  await step('Review movements').getByRole('button', { name: 'Show movements' }).click();
  await expect(page.locator('button.tab--active')).toContainText('Movements');

  await checklist.getByRole('button', { name: 'Hide' }).click();
  await expect(checklist.locator('.pc-step')).toHaveCount(0);
  await expect(checklist).toContainText('Next:');
  await checklist.getByRole('button', { name: 'Show steps' }).click();
  await expect(checklist.locator('.pc-step')).toHaveCount(5);
});

test('bulk plans by flights', async ({ page }) => {
  const dialog = await openNewPlan(page);
  await dialog.getByRole('tab', { name: /By arrivals/ }).click();
  await dialog.getByLabel('Movement template').selectOption({ label: 'Team Arrival (MVT-ARR)' });

  // Qatar already has its arrival from the single plan: listed as already planned, not tickable.
  await expect(dialog.getByText('1 already planned · skipped')).toBeVisible();
  await expect(dialog.getByRole('checkbox', { name: 'QAT-17' })).toHaveCount(0);
  const legend = dialog.locator('.np-legend');
  await expect(legend).toContainText('3 will be created');
  await expect(legend).toContainText('1 already planned');
  const create = dialog.getByRole('button', { name: /^Create \d+ plans?$/ });

  const china = dialog.getByRole('checkbox', { name: 'CHN-17' });
  await china.uncheck();
  await expect(legend).toContainText('2 will be created');
  await expect(legend).toContainText('1 unticked');

  // The filter narrows the list; the day's tick box switches the whole day.
  await dialog.getByLabel('Filter teams').fill('UAE');
  await expect(china).toBeHidden();
  await dialog.getByLabel('Filter teams').fill('');
  const day = dialog.getByRole('checkbox', { name: /^Include every team on/ });
  await day.click();
  await expect(legend).toContainText('3 will be created');
  await expect(day).toBeChecked();
  await day.click();
  await expect(legend).toContainText('0 will be created');
  await expect(dialog.getByRole('button', { name: 'Select at least one team' })).toBeDisabled();
  await expect(dialog.getByText('Tick at least one team')).toBeVisible();
  await day.click();
  await expect(legend).toContainText('3 will be created');

  await expect(create).toBeEnabled();
  await create.click();
  await expect(dialog).toBeHidden();
});

test('bulk plans by matches, then the same matches are blocked', async ({ page }) => {
  let dialog = await openNewPlan(page);
  await dialog.getByRole('tab', { name: /By matches/ }).click();
  await dialog.getByLabel('Movement template').selectOption({ label: 'Team Match Day (MVT-MD)' });

  await expect(dialog.getByText(/E2E-001 · vs/).first()).toBeVisible();
  const create = dialog.getByRole('button', { name: /^Create \d+ plans?$/ });
  await expect(create).toBeEnabled();
  await create.click();
  await expect(dialog).toBeHidden();

  dialog = await openNewPlan(page);
  await dialog.getByRole('tab', { name: /By matches/ }).click();
  await dialog.getByLabel('Movement template').selectOption({ label: 'Team Match Day (MVT-MD)' });
  await expect(dialog.getByRole('button', { name: 'Nothing to create' })).toBeDisabled();
});

test('assign crew to a movement and generate its job', async ({ page }) => {
  await gotoPlansList(page);
  await page.getByText('E2E single plan').first().click();

  await page.getByTitle('Edit').first().click();
  const dialog = page.locator('.modal').filter({ hasText: 'Update movement' });
  await expect(dialog).toBeVisible();

  const pick = async (label, text) => {
    const select = dialog.getByLabel(label, { exact: true });
    const value = await select.locator('option', { hasText: text }).first().getAttribute('value');
    await select.selectOption(value);
  };
  // Only the movement's provider's fleet is offered, as in Crew Assignment.
  await expect(dialog.locator('option', { hasText: 'V-OTHER' })).toHaveCount(0);
  await expect(dialog.getByText(/^Still needed: .*supervisor$/)).toBeVisible();
  await expect(dialog.getByRole('button', { name: 'Update movement' })).toBeDisabled();
  await pick('Vehicle 1', 'V-01');
  await pick('Driver 1', 'Driver One');
  await pick('Add field supervisor', 'E2E Supervisor');
  await expect(dialog.locator('.em-sup', { hasText: 'E2E Supervisor' })).toBeVisible();
  await dialog.getByRole('button', { name: '+ Add vehicle' }).click();
  await pick('Vehicle 2', 'V-02');
  await pick('Driver 2', 'Driver Two');
  await expect(dialog.getByText('2 of 2 assigned')).toBeVisible();
  await expect(dialog.getByText(/^\d+ changes? ready to save$/)).toBeVisible();

  // The window can't end before it starts.
  const end = dialog.getByLabel('Window end', { exact: true });
  const endTime = await end.inputValue();
  await end.fill(await dialog.getByLabel('Window start', { exact: true }).inputValue());
  await end.dispatchEvent('change');
  await expect(dialog.getByText('The window has to end after it starts.')).toBeVisible();
  await expect(dialog.getByRole('button', { name: 'Update movement' })).toBeDisabled();
  await end.fill(endTime);
  await end.dispatchEvent('change');
  await expect(dialog.getByText('The window has to end after it starts.')).toBeHidden();
  // Typed times are 24-hour; nonsense puts the old value back.
  await end.fill('99:99');
  await end.dispatchEvent('change');
  await expect(end).toHaveValue(endTime);

  await dialog.getByRole('button', { name: 'Update movement' }).click();
  await expect(dialog).toBeHidden();

  // Reopening shows what was saved, extra vehicle included.
  await page.getByTitle('Edit').first().click();
  await expect(dialog).toBeVisible();
  const selected = (label) => dialog.getByLabel(label, { exact: true }).locator('option:checked');
  await expect(selected('Vehicle 1')).toContainText('V-01');
  await expect(selected('Driver 2')).toContainText('Driver Two');
  await expect(dialog.locator('.em-sup', { hasText: 'E2E Supervisor' })).toBeVisible();
  await expect(dialog.getByText('No changes')).toBeVisible();
  await dialog.getByRole('button', { name: 'Cancel' }).click();
  await expect(dialog).toBeHidden();

  await page.getByRole('button', { name: 'Generate', exact: true }).first().click();
  await expect(page.getByText(/JOB-\d{8}-\d{4}/).first()).toBeVisible();

  const checklist = page.getByRole('region', { name: 'Plan checklist' });
  await expect(checklist.locator('.pc-step', { hasText: 'Assign crew' })).toContainText('1 / 1 crewed');
  await expect(checklist.locator('.pc-step', { hasText: 'Generate jobs' })).toContainText('1 / 1 generated');
});

test('crew assignment offers the same crew as planning', async ({ page }) => {
  await page.goto(`/crew-assignment?date=${isoDate(1)}`);
  const vehicle = page.getByLabel(/^Vehicle 1 for /).first();
  await expect(vehicle).toBeVisible();
  await expect(vehicle.locator('option:checked')).toContainText('V-01');
  await expect(vehicle.locator('option', { hasText: 'V-OTHER' })).toHaveCount(0);
});

test('crew assignment shows a second event and its double-booking of a shared driver', async ({ page }) => {
  await page.goto(`/crew-assignment?date=${isoDate(1)}&events=`);
  const shieldRow = page.locator('.ct-code', { hasText: 'TRP-90001' });
  await expect(page.locator('.ct-code').first()).toBeVisible();
  await expect(shieldRow).toHaveCount(0);
  // Even with one event shown, the saved clash with the other event's movement is named.
  await expect(page.getByText(/TRP-90001/).first()).toBeVisible();

  await page.getByLabel('Events shown').click();
  await page.getByLabel('Show E2E Shield').check();

  // The other event's movement joins the table, tagged with its event, and the shared driver clashes.
  await expect(shieldRow).toBeVisible();
  await expect(page.locator('.ct-tag--event', { hasText: 'SHD' }).first()).toBeVisible();
  await expect(page.getByText(/Driver One is also on/).first()).toBeVisible();

  // Its vehicles come from its own event's pool: V-01 isn't in it.
  const shieldVehicle = page.getByLabel('Vehicle 1 for TRP-90001');
  await expect(shieldVehicle.locator('option', { hasText: 'V-01' })).toHaveCount(0);

  // Remembered until unticked.
  await page.reload();
  await expect(shieldRow).toBeVisible();
  await page.getByLabel('Events shown').click();
  await page.getByLabel('Show E2E Shield').uncheck();
  await expect(shieldRow).toHaveCount(0);
});

test('conflicts tab lists the plan conflicts and View focuses their movements', async ({ page }) => {
  await gotoPlansList(page);
  await page.getByText('E2E single plan').first().click();
  await page.locator('button.tab', { hasText: 'Conflicts' }).click();

  await expect(page.getByText(/No conflicts detected|conflicts detected/).first()).toBeVisible();
  const view = page.getByRole('button', { name: 'View', exact: true });
  if (await view.count()) {
    await view.first().click();
    // Event-wide movements, narrowed to the ones that conflict involves.
    await expect(page.getByText(/Showing \d+ of \d+ movements/)).toBeVisible();
  }
});

test('a checkpoint note shows in the Jobs Queue and on Job Detail', async ({ page }) => {
  await page.goto('/jobs');
  await page.locator('.job-item').first().click();

  // The web completion's reason is stored as the checkpoint's note, like the mobile app's note field.
  await page.getByRole('button', { name: 'Mark complete' }).first().click();
  const reason = page.locator('#complete-reason');
  await reason.selectOption({ label: 'Operational change' });
  await page.getByRole('button', { name: 'Mark complete' }).last().click();
  await expect(reason).toBeHidden();

  const queueNote = page.locator('.checkpoint-section .dc-cp-note');
  await expect(queueNote).toHaveCount(1);
  await expect(queueNote).toContainText('Operational change');
  // The job line flags that the detail holds a note.
  await expect(page.locator('.job-item--active .jl-note-badge')).toHaveAttribute('aria-label', '1 checkpoint note');

  const jobId = (await page.locator('.job-item--active .jl-time').getAttribute('title')) ?? '';
  await page.goto(`/job/${jobId}`);
  const detailNote = page.locator('.cp-note');
  await expect(detailNote).toHaveCount(1);
  await expect(detailNote).toContainText('Operational change');
});

test('deleting a movement that has a job explains why in a dialog', async ({ page }) => {
  await gotoPlansList(page);
  await page.getByText('E2E single plan').first().click();

  await page.getByTitle('Delete').first().click();
  let dialog = page.locator('.modal').filter({ hasText: 'Cannot Delete Movement' });
  await expect(dialog).toBeVisible();
  await expect(dialog.getByText(/has a generated job, JOB-\d{8}-\d{4}, so it can't be deleted/)).toBeVisible();
  await expect(dialog.getByRole('button', { name: 'Delete Movement' })).toHaveCount(0);
  await dialog.getByRole('button', { name: 'Close' }).click();
  await expect(dialog).toBeHidden();

  // The plan's only movement: the header checkbox selects it.
  await page.locator('.plan-table-card input[type="checkbox"]').first().check();
  await page.getByRole('button', { name: /^Delete Selected/ }).click();
  dialog = page.locator('.modal').filter({ hasText: 'Delete Selected Movements' });
  await expect(dialog.getByText('The selected movement has a generated job, so nothing can be deleted')).toBeVisible();
  await expect(dialog.getByRole('button', { name: /^Delete \d+ Movement/ })).toHaveCount(0);
  await dialog.getByRole('button', { name: 'Close' }).click();
  await expect(dialog).toBeHidden();
});

test('plan list search and bulk-delete selection', async ({ page }) => {
  await gotoPlansList(page);
  await page.getByLabel('Search plans').fill('E2E single');
  await expect(page.getByText('E2E single plan').first()).toBeVisible();
  await expect(page.getByText(/Team Match Day/)).toHaveCount(0);

  await page.locator('input[type="checkbox"]').nth(1).check();
  await expect(page.getByText('1 selected')).toBeVisible();
  await page.getByRole('button', { name: 'Delete selected' }).click();
  const dialog = page.locator('.modal').filter({ hasText: 'Delete 1 plan(s)' });
  await expect(dialog).toBeVisible();
  // Its movement has a job, so the plan is kept.
  await dialog.locator('.modal-close').click();
  await expect(dialog).toBeHidden();
});

test('the plan picker opens every plan\'s movements, then the plan list again', async ({ page }) => {
  await gotoPlansList(page);
  await page.getByText('Select a Plan').click();
  await page.getByRole('button', { name: /^All movements/ }).click();
  await expect(page.getByText('All Movements (Event-wide)')).toBeVisible();

  // Rows from several plans, each tagged with its plan code.
  const planCodes = async () => new Set(await page.getByText(/^PLN-\d{4}-\d{4}-\d+$/).allInnerTexts()).size;
  await expect.poll(planCodes).toBeGreaterThan(1);

  // Adding a movement needs a plan: off here, with a "Coming soon" tooltip.
  const add = page.getByRole('button', { name: 'Add movement' });
  await expect(add).toBeDisabled();
  await expect(page.locator('[title="Coming soon"]').filter({ has: add })).toHaveCount(1);

  await page.getByText('All Movements (Event-wide)').click();
  await page.getByRole('button', { name: /^All plans/ }).click();
  await expect(page.getByText('Select a Plan')).toBeVisible();
  await expect(page.getByLabel('Search plans')).toBeVisible();
});
