import { test, expect } from '@playwright/test';
import { signIn } from './helpers.js';

// Library → Checkpoint Templates: list, detail panel, the editor (new / edit) and its route suggestion.
test.describe.configure({ mode: 'serial' });

test.beforeEach(async ({ page }) => {
  await signIn(page);
  const problems = [];
  page.on('console', (msg) => {
    const text = msg.text();
    if (msg.type() === 'error' || text.startsWith('[Vue warn]')) problems.push(text);
  });
  page.on('pageerror', (error) => problems.push(error.message));
  test.info().problems = problems;
});

test.afterEach(async () => {
  expect(test.info().problems, 'console errors and Vue warnings').toEqual([]);
});

const openTab = async (page) => {
  await page.goto('/library');
  await page.locator('.tab', { hasText: 'Checkpoint Templates' }).click();
};

test('browse checkpoint templates and jump to a movement template that uses one', async ({ page }) => {
  await openTab(page);
  const arrival = page.locator('.ctl-item', { hasText: 'Arrival sequence' });
  await expect(arrival).toContainText('2 checkpoints');
  await expect(arrival.locator('.ctl-type')).toHaveText(/arrival/i);
  await expect(arrival).toContainText('1 template');

  await page.locator('.ctl-pill', { hasText: 'Match' }).click();
  await expect(arrival).toHaveCount(0);
  await page.locator('.ctl-pill', { hasText: 'All' }).click();

  await arrival.click();
  const panel = page.getByRole('complementary', { name: 'Checkpoint template' });
  await expect(panel.locator('.ctl-panel-name')).toHaveText('Arrival sequence');
  await expect(panel.locator('.ctl-step')).toHaveCount(2);
  await panel.getByRole('button', { name: 'Team Arrival' }).click();
  await expect(page.locator('.tab--active')).toContainText('Movement Templates');
  await expect(page.getByRole('complementary', { name: 'Movement template' }).locator('.mt-panel-name')).toHaveText('Team Arrival');
});

test('create a template in the editor, see it suggested for its route, edit and delete it', async ({ page }) => {
  await openTab(page);
  await page.getByRole('button', { name: 'New Checkpoint Template' }).click();
  const editor = page.locator('.modal-container').filter({ hasText: 'New checkpoint template' });
  await expect(editor.getByRole('button', { name: 'Create template' })).toBeDisabled();
  await expect(editor.locator('.cte-status')).toHaveText('Add a template name');

  await editor.getByLabel('Template name').fill('E2E Kit Run');
  await editor.getByRole('radio', { name: 'Transfer' }).click();
  await editor.getByLabel('Typical route from').fill('Team Hotel');
  await editor.getByLabel('Typical route to').fill('Stadium');
  await expect(editor.locator('.cte-code-chip')).toHaveText('TMPL-TRF-TH-STA');
  await expect(editor.locator('.cte-status')).toHaveText('Add at least one checkpoint');

  await editor.getByRole('button', { name: 'Add Depart hotel' }).click();
  await editor.getByRole('button', { name: 'Add Arrive at stadium' }).click();
  await expect(editor.getByRole('button', { name: 'Depart hotel added' })).toBeDisabled();
  await editor.getByRole('button', { name: 'Step 1 5 minutes more' }).click();
  await editor.getByRole('switch', { name: 'Step 2 required' }).click();
  await expect(editor.locator('.cte-clock').nth(1)).toHaveText('T+15');
  await expect(editor.locator('.cte-summary-nums')).toContainText('15m');

  // A checkpoint that isn't in the library yet is created from the search box.
  await editor.getByLabel('Search checkpoints to add').fill('E2E LOAD CHECK');
  await editor.getByRole('button', { name: 'Create “E2E LOAD CHECK” as a new checkpoint' }).click();
  await expect(editor.locator('.cte-row .cte-cp-name', { hasText: 'E2E LOAD CHECK' })).toBeVisible();

  await expect(editor.locator('.cte-status')).toHaveText('Ready to create');
  await editor.getByRole('button', { name: 'Create template' }).click();
  await expect(editor).toBeHidden();
  const row = page.locator('.ctl-item', { hasText: 'E2E Kit Run' });
  await expect(row).toContainText('Team Hotel → Stadium');
  await expect(row).toContainText('3 checkpoints');

  // The movement template editor suggests it for a Team Hotel → Stadium leg.
  await page.locator('.tab', { hasText: 'Movement Templates' }).click();
  await page.getByRole('button', { name: 'New Movement Template' }).click();
  const mte = page.locator('.modal-container').filter({ hasText: 'New movement template' });
  await mte.getByLabel('Leg 1 from', { exact: true }).fill('Team Hotel');
  await mte.getByLabel('Leg 1 to', { exact: true }).fill('Stadium');
  await expect(mte.getByRole('button', { name: /Suggested for this route: E2E Kit Run/ })).toBeVisible();
  await mte.getByRole('button', { name: 'Cancel' }).click();

  // Edit, then delete
  await page.locator('.tab', { hasText: 'Checkpoint Templates' }).click();
  await row.getByRole('button', { name: 'Edit E2E Kit Run' }).click();
  const edit = page.locator('.modal-container').filter({ hasText: 'Edit checkpoint template' });
  await expect(edit.getByLabel('Typical route to')).toHaveValue('Stadium');
  await expect(edit.locator('.cte-row:not(.cte-row--head)')).toHaveCount(3);
  await edit.getByLabel('Template name').fill('E2E Kit Run B');
  await edit.getByRole('button', { name: 'Save changes' }).click();
  await expect(edit).toBeHidden();
  const renamed = page.locator('.ctl-item', { hasText: 'E2E Kit Run B' });
  await expect(renamed).toBeVisible();

  await renamed.getByRole('button', { name: 'Delete E2E Kit Run B' }).click();
  await page.locator('.modal-container').filter({ hasText: 'Delete Checkpoint Template' }).getByRole('button', { name: 'Delete', exact: true }).click();
  await expect(renamed).toHaveCount(0);
});
