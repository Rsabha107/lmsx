import { test, expect } from '@playwright/test';
import { signIn } from './helpers.js';

// Crew Assignment table: window · movement · crew chips · status.
test.describe.configure({ mode: 'serial' });

const tomorrow = (() => {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
})();

test.beforeEach(async ({ page }) => {
  await signIn(page);
  await page.evaluate(() => localStorage.setItem('crew.view', 'table'));
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

test('a shared event movement shows in the table and its crew can be picked from the chips', async ({ page }) => {
  await page.goto(`/crew-assignment?date=${tomorrow}`);
  await expect(page.locator('.ct-head')).toContainText('Window');

  await page.locator('.ev-pick summary').click();
  await page.getByLabel('Show E2E Shield').check();
  await expect(page.locator('.ev-pick summary')).toContainText('2 events');
  // A click elsewhere closes the panel so it doesn't cover the table.
  await page.locator('.rh-h1').click();
  await expect(page.locator('.ev-pick-panel')).toBeHidden();

  const row = page.locator('.ct-item', { hasText: 'TRP-90001' });
  await expect(row).toBeVisible();
  await expect(row.locator('.ct-team-name')).toHaveText('Oman');
  await expect(row.locator('.chip-text', { hasText: 'Driver One' })).toBeVisible();
  await expect(row.locator('.ct-pill')).toHaveText('Needs crew');
  await expect(row.getByText('Missing vehicle, supervisor')).toBeVisible();

  // The shared event's pool has no vehicles, and another provider's are never offered.
  const vehicle = row.getByLabel('Vehicle 1 for TRP-90001');
  await expect(vehicle.locator('option', { hasText: 'V-OTHER' })).toHaveCount(0);
  await expect(vehicle.locator('option', { hasText: 'V-01' })).toHaveCount(0);

  await row.getByLabel('Supervisor 1 for TRP-90001').selectOption({ label: 'E2E Supervisor' });
  await expect(row.locator('.chip-text', { hasText: 'E2E Supervisor' })).toBeVisible();
  await expect(row.getByText('Missing vehicle', { exact: true })).toBeVisible();
  await expect(page.locator('.savebar')).toContainText('1 movement changed');

  await row.getByRole('button', { name: '+ Add vehicle' }).click();
  await expect(row.locator('.ct-label', { hasText: 'Vehicle 2' })).toBeVisible();
  await row.getByRole('button', { name: 'Remove vehicle 2' }).click();
  await expect(row.locator('.ct-label', { hasText: 'Vehicle 2' })).toHaveCount(0);

  await page.locator('.savebar').getByRole('button', { name: 'Discard' }).click();
  await expect(page.locator('.savebar')).toHaveCount(0);
  await expect(row.locator('.ct-pill')).toHaveText('Needs crew');

  await page.getByRole('button', { name: /^Completed/ }).click();
  await expect(page.getByText('Nothing matches this filter.')).toBeVisible();
});
