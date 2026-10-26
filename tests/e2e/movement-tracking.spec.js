import { test, expect } from '@playwright/test';
import { signIn } from './helpers.js';

// Movement Tracking: the page renders with Export to Excel, and the export returns a workbook.
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

test('Movement Tracking offers an Excel export of the filtered table', async ({ page }) => {
  // All events: dimmed, with the reason on hover.
  await page.goto('/kit-truck?kind=arrival&event=');
  const button = page.getByRole('button', { name: 'Export to Excel' });
  await expect(button).toBeDisabled();
  await expect(page.locator('.export-wrap')).toHaveAttribute('title', /Pick an event to export/);

  // One event but no jobs: still dimmed, saying why.
  await page.goto('/kit-truck?kind=arrival&event=1');
  await expect(button).toBeDisabled();
  await expect(page.locator('.export-wrap')).toHaveAttribute('title', 'Nothing to export for these filters.');

  const response = await page.request.get('/kit-truck/export?kind=arrival&event=1');
  expect(response.status()).toBe(200);
  expect(response.headers()['content-type']).toContain('spreadsheetml');
  expect(response.headers()['content-disposition']).toContain('Movement-Tracking_Arrivals_');
});
