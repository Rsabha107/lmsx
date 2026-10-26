import { test, expect } from '@playwright/test';
import { signIn } from './helpers.js';

// Library → Movement Templates: list, type filter, detail panel, Duplicate and delete.
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

test('browse movement templates, open a leg, duplicate one and delete the copy', async ({ page }) => {
  await page.goto('/library');
  await page.locator('.tab', { hasText: 'Movement Templates' }).click();

  const arrival = page.locator('.mt-item').filter({ has: page.locator('.mt-name-text', { hasText: /^Team Arrival$/ }) });
  await expect(arrival).toContainText('Airport');
  await expect(arrival).toContainText('Team Hotel');
  await expect(arrival.locator('.mt-type')).toHaveText(/arrival/i);

  // Type filter
  await page.locator('.mt-pill', { hasText: 'Match' }).click();
  await expect(arrival).toHaveCount(0);
  await page.locator('.mt-pill', { hasText: 'All' }).click();
  await expect(arrival).toBeVisible();

  // Detail panel: legs, then a leg's checkpoint template and its steps
  await arrival.click();
  const panel = page.getByRole('complementary', { name: 'Movement template' });
  await expect(panel.locator('.mt-panel-name')).toHaveText('Team Arrival');
  await expect(panel.locator('.mt-stats')).toContainText('Legs');
  await panel.locator('.mt-ct-head').first().click();
  await expect(panel.locator('.mt-step').first()).toBeVisible();
  await panel.getByRole('button', { name: 'Open in Checkpoint Templates →' }).click();
  const steps = page.locator('.modal-container').filter({ hasText: 'Edit Checkpoint Template' });
  await expect(steps).toBeVisible();
  await steps.getByRole('button', { name: 'Cancel' }).click();
  await expect(page.locator('.tab--active')).toContainText('Checkpoint Templates');

  // Duplicate, then delete the copy (unused, so it goes straight to the recycle bin)
  await page.locator('.tab', { hasText: 'Movement Templates' }).click();
  await page.getByRole('button', { name: 'Duplicate Team Arrival', exact: true }).click();
  const copy = page.locator('.mt-item', { hasText: 'Team Arrival (copy)' });
  await expect(copy).toBeVisible();
  await expect(copy.locator('.mt-code')).toHaveText('MVT-ARR-2');
  await expect(copy).toContainText('Team Hotel');

  await copy.getByRole('button', { name: 'Delete Team Arrival (copy)' }).click();
  const confirm = page.locator('.modal-container').filter({ hasText: 'Delete Movement Template' });
  await confirm.getByRole('button', { name: 'Delete', exact: true }).click();
  await expect(copy).toHaveCount(0);
});

test('create a template in the editor, then edit it', async ({ page }) => {
  await page.goto('/library');
  await page.locator('.tab', { hasText: 'Movement Templates' }).click();
  await page.getByRole('button', { name: 'New Movement Template' }).click();

  const editor = page.locator('.modal-container').filter({ hasText: 'New movement template' });
  await expect(editor.getByRole('button', { name: 'Create template' })).toBeDisabled();
  await expect(editor.locator('.mte-status')).toContainText('Name required');
  await expect(editor.locator('.mte-status')).toContainText('1 leg without a checkpoint template');

  await editor.getByLabel('Template name').fill('E2E Training Run');
  await editor.getByRole('radio', { name: 'Training' }).click();
  await editor.getByLabel('Leg 1 from', { exact: true }).fill('Team Hotel');
  await editor.getByLabel('Leg 1 to', { exact: true }).fill('Training site');
  await editor.getByRole('radiogroup', { name: 'Leg 1 transport' }).getByRole('radio', { name: 'Van' }).click();
  await editor.getByLabel('Leg 1 checkpoint template', { exact: true }).selectOption({ index: 1 });
  await expect(editor.locator('.mte-cp').first()).toBeVisible();

  await editor.getByRole('button', { name: /Add return leg · Training site → Team Hotel/ }).click();
  await editor.getByLabel('Leg 2 checkpoint template', { exact: true }).selectOption({ index: 1 });
  await expect(editor.locator('.mte-summary-nums')).toContainText('2');
  await expect(editor.locator('.mte-code-chip')).toHaveText('MVT-TRN-TH');
  await expect(editor.locator('.mte-status')).toHaveText('Ready to save');

  await editor.getByRole('button', { name: 'Create template' }).click();
  await expect(editor).toBeHidden();
  const row = page.locator('.mt-item', { hasText: 'E2E Training Run' });
  await expect(row).toContainText('Training site');
  await expect(row).toContainText('2 legs · Van, Bus');
  await expect(row.locator('.mt-code')).toHaveText('MVT-TRN-TH');

  // Edit: the same editor, filled in
  await row.getByRole('button', { name: 'Edit E2E Training Run' }).click();
  const edit = page.locator('.modal-container').filter({ hasText: 'Edit movement template' });
  await expect(edit.getByLabel('Template name')).toHaveValue('E2E Training Run');
  await expect(edit.getByLabel('Leg 1 from', { exact: true })).toHaveValue('Team Hotel');
  await expect(edit.getByText('= 1h 30m')).toHaveCount(0);
  await edit.getByLabel('Leg 1 minutes', { exact: true }).fill('90');
  await expect(edit.getByText('= 1h 30m')).toBeVisible();
  await edit.getByLabel('Template name').fill('E2E Training Run B');
  await edit.getByRole('button', { name: 'Save changes' }).click();
  await expect(edit).toBeHidden();
  const renamed = page.locator('.mt-item', { hasText: 'E2E Training Run B' });
  await expect(renamed).toBeVisible();

  await renamed.getByRole('button', { name: 'Delete E2E Training Run B' }).click();
  await page.locator('.modal-container').filter({ hasText: 'Delete Movement Template' }).getByRole('button', { name: 'Delete', exact: true }).click();
  await expect(renamed).toHaveCount(0);
});
