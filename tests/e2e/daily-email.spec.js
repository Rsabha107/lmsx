import { test, expect } from '@playwright/test';
import { signIn } from './helpers.js';

// Settings → Notifications → Daily Email audience, then the Daily Email page sends to it.
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

test('choose the Daily Email audience and send to it', async ({ page }) => {
  await page.goto('/email');
  await expect(page.getByText('No audience is set')).toBeVisible();
  await page.getByRole('link', { name: /Choose recipients/ }).click();

  await expect(page.getByRole('heading', { name: 'Daily Email' })).toBeVisible();
  const row = page.locator('.de-row', { hasText: 'E2E Supervisor' });
  await row.locator('input[type=checkbox]').check();
  await expect(page.locator('.de-chip', { hasText: 'E2E Supervisor' })).toBeVisible();
  await page.getByRole('button', { name: 'Save recipients' }).click();
  await expect(page.getByText('All changes saved')).toBeVisible();

  await page.goto('/email');
  await expect(page.getByText(/Yesterday \(/)).toBeVisible();
  await expect(page.getByText('Today at a glance')).toBeVisible();
  const send = page.getByRole('button', { name: 'Send to 1 recipient' });
  await expect(send).toBeEnabled();
  await send.click();
  await expect(page.getByText('Email sent to 1 recipient')).toBeVisible();
});
