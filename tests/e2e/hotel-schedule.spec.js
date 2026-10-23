import { test, expect } from '@playwright/test';
import { signIn } from './helpers.js';

// Duty Roster → Hotel schedule, against the seeded E2E Cup: QAT and CHN stay at E2E Grand Hotel,
// UAE and BHR at E2E Bay Hotel; E2E-001 (QAT v CHN, KO 18:00) is three days from now.
test.describe.configure({ mode: 'serial' });

const matchDay = (() => {
  const d = new Date();
  d.setDate(d.getDate() + 3);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
})();

test.beforeEach(async ({ page }) => {
  await signIn(page);
  const problems = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error' || msg.text().startsWith('[Vue warn]')) problems.push(msg.text());
  });
  page.on('pageerror', (error) => problems.push(error.message));
  test.info().problems = problems;
});

test.afterEach(async () => {
  expect(test.info().problems, 'console errors and Vue warnings').toEqual([]);
});

test('hotel schedule: supervisor, crew teams, kit runs and VSA crews', async ({ page }) => {
  await page.goto('/duty-roster');
  await page.getByRole('tab', { name: /Hotel schedule/ }).click();
  await expect(page.getByRole('heading', { name: 'Hotel team schedule' })).toBeVisible();

  // Teams and report times come from the stays and kick-offs (KO 18:00 − 4h30).
  const teamRow = (code) => page.locator('.hs-team', { has: page.getByLabel(`Crew team for ${code} on ${matchDay}`) });
  await expect(teamRow('QAT')).toContainText('13:30');
  await expect(teamRow('CHN')).toContainText('13:30');

  await page.getByLabel(`Supervisor at E2E Grand Hotel on ${matchDay}`).selectOption({ label: 'E2E Supervisor' });
  await page.getByLabel(`Crew team for QAT on ${matchDay}`).selectOption({ label: 'Crew 1' });
  await expect(page.getByLabel(`Crew team for QAT on ${matchDay}`)).toHaveValue('1');

  // Auto-fill numbers the rest and suggests the kit runs (departure with the first team to report).
  await page.getByRole('button', { name: /Auto-fill crews/ }).click();
  await expect(page.getByLabel(`Crew team for CHN on ${matchDay}`).locator('option:checked')).toHaveText('Crew 2');
  const departure = page.getByLabel(`Kits departure to venue at E2E Grand Hotel on ${matchDay}`);
  await expect(departure).toHaveText('Crew 2 @ 13:30');

  // Edit the arrival run by hand.
  await page.getByLabel(`Kits arrival from venue at E2E Grand Hotel on ${matchDay}`).click();
  const editor = page.getByRole('dialog', { name: 'Kits arrival from venue' });
  await editor.getByLabel('Time').fill('99:00');
  await editor.getByRole('button', { name: 'Save' }).click();
  await expect(editor.getByText('Type a 24-hour time')).toBeVisible();
  await editor.getByLabel('Time').fill('1500');
  await editor.getByRole('button', { name: 'Save' }).click();
  await expect(page.getByLabel(`Kits arrival from venue at E2E Grand Hotel on ${matchDay}`)).toContainText('@ 15:00');

  await page.getByLabel(`Aspire VSA crews on ${matchDay}`).click();
  await page.getByRole('dialog', { name: 'Aspire VSA team' }).getByRole('button', { name: 'Crew 5', exact: true }).click();
  await page.getByRole('dialog', { name: 'Aspire VSA team' }).getByRole('button', { name: 'Save' }).click();
  await expect(page.getByLabel(`Aspire VSA crews on ${matchDay}`)).toHaveText('Crew 5');

  // Everything is still there after a reload.
  await page.reload();
  await expect(page.getByLabel(`Supervisor at E2E Grand Hotel on ${matchDay}`).locator('option:checked')).toHaveText('E2E Supervisor');
  await expect(page.getByLabel(`Kits arrival from venue at E2E Grand Hotel on ${matchDay}`)).toContainText('@ 15:00');
  await expect(page.getByLabel(`Aspire VSA crews on ${matchDay}`)).toHaveText('Crew 5');
});

test('the same supervisor at two hotels on one day is flagged', async ({ page }) => {
  await page.goto('/duty-roster?tab=hotels');
  await page.getByLabel(`Supervisor at E2E Bay Hotel on ${matchDay}`).selectOption({ label: 'E2E Supervisor' });
  await expect(page.getByLabel(`Supervisor at E2E Bay Hotel on ${matchDay}`)).toHaveAttribute('title', /Also at E2E Grand Hotel/);
});
