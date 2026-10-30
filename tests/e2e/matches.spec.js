import { test, expect } from '@playwright/test';
import { signIn } from './helpers.js';

// Matches: the team, venue and date filters narrow the list and Clear filters brings it back.
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

test('team, venue and date filters', async ({ page }) => {
  await page.goto('/matches');
  const count = page.locator('.page-sub');
  // E2eSeeder: QAT–CHN and UAE–BHR on one day, QAT–UAE three days later, all at one venue.
  await expect(count).toHaveText('3 matches');

  await page.getByLabel('Team', { exact: true }).selectOption({ label: 'Qatar (QAT-17) · 2' });
  await expect(count).toHaveText('2 matches');

  // The first match day (2 matches that day), together with the team: 1.
  const dates = page.getByLabel('Match date');
  await expect(dates.locator('option')).toHaveCount(3);
  await dates.selectOption({ index: 1 });
  await expect(count).toHaveText('1 match');

  await page.getByRole('button', { name: 'Clear filters' }).click();
  await expect(count).toHaveText('3 matches');
  await expect(page.getByLabel('Team', { exact: true })).toHaveValue('');

  // One venue holds every match.
  const venue = page.getByLabel('Venue', { exact: true });
  await venue.selectOption({ index: 1 });
  await expect(count).toHaveText('3 matches');
  await expect(page.getByRole('button', { name: 'Clear filters' })).toBeVisible();
});
