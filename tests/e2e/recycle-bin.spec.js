import { test, expect } from '@playwright/test';
import { signIn, openNewPlan, gotoPlansList } from './helpers.js';

// Recycle bin: a deleted plan can be found in Setups → Recycle bin and restored.
test.describe.configure({ mode: 'serial' });

const tomorrow = (() => {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
})();

test.beforeEach(async ({ page }) => {
  await signIn(page);
  const problems = [];
  page.on('console', (msg) => {
    const text = msg.text();
    if ((msg.type() === 'error' || text.startsWith('[Vue warn]')) && !/custom validator check failed for prop "variant"/.test(text)) problems.push(text);
  });
  page.on('pageerror', (error) => problems.push(error.message));
  test.info().problems = problems;
});

test.afterEach(async () => {
  expect(test.info().problems, 'console errors and Vue warnings').toEqual([]);
});

test('a deleted plan goes to the recycle bin and can be restored', async ({ page }) => {
  // An empty plan, so the delete needs no typing.
  const dialog = await openNewPlan(page);
  await dialog.getByLabel('Movement template').selectOption({ label: 'Blank: no movements' });
  await dialog.getByLabel('Team', { exact: true }).selectOption({ label: 'Qatar (QAT-17)' });
  await dialog.getByLabel('Date', { exact: true }).fill(tomorrow);
  await dialog.getByLabel('Plan name').fill('Bin test plan');
  await dialog.getByRole('button', { name: 'Create plan' }).click();
  await expect(dialog).toBeHidden();

  await gotoPlansList(page);
  await page.getByLabel('Search plans').fill('Bin test plan');
  await page.locator('input[type="checkbox"]').nth(1).check();
  await page.getByRole('button', { name: 'Delete selected' }).click();
  const confirm = page.locator('.modal').filter({ hasText: 'Delete 1 plan(s)' });
  await expect(confirm.getByText(/Recycle bin; it can be restored/)).toBeVisible();
  await confirm.getByRole('button', { name: 'Delete 1 Plan(s)' }).click();
  await expect(page.getByText(/Deleted 1 plan\. You can restore it from Setups → Recycle bin/)).toBeVisible();

  await expect(page.getByRole('link', { name: 'Recycle bin' })).toHaveCount(1);
  await page.goto('/setups/recycle-bin');
  await expect(page.getByRole('heading', { name: 'Recycle bin' })).toBeVisible();
  await page.getByRole('tab', { name: /Deleted/ }).click();
  const batch = page.locator('.rb-card', { hasText: '1 plan' }).first();
  await expect(batch).toContainText('days left');
  await batch.getByRole('button', { name: /Show 1 item/ }).click();
  await expect(batch).toContainText('Bin test plan');

  await batch.getByRole('button', { name: 'Restore all' }).click();
  await page.getByRole('button', { name: 'Restore', exact: true }).click();
  await expect(page.getByText('Restored 1 item(s).')).toBeVisible();

  await page.getByRole('tab', { name: /History/ }).click();
  await expect(page.locator('.rb-table', { hasText: '1 plan' })).toContainText('Restored');

  await gotoPlansList(page);
  await page.getByLabel('Search plans').fill('Bin test plan');
  await expect(page.getByText('Bin test plan').first()).toBeVisible();
});
