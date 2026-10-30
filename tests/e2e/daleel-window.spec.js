import { test, expect } from '@playwright/test';
import { signIn } from './helpers.js';

// Daleel opens from the sidebar as a floating window over the page: movable, resizable, kept across pages.
// Room around the window to drag it.
test.use({ viewport: { width: 1440, height: 1000 } });

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

test('Daleel opens as a movable window and keeps the conversation across pages', async ({ page }) => {
  // No real AI call in the browser test.
  await page.route('**/ai/query', (route) => route.fulfill({ json: { ok: true, answer: 'Two movements are delayed.' } }));
  await page.evaluate(() => localStorage.removeItem('daleel.window'));

  await page.goto('/plans');
  await page.locator('.sidebar-nav').getByRole('link', { name: 'Daleel' }).click();

  const win = page.getByRole('dialog', { name: 'Daleel' });
  await expect(win).toBeVisible();
  await expect(page).toHaveURL(/\/plans/);
  const before = await win.boundingBox();
  expect(before.width).toBeGreaterThan(600);

  // Drag by the header.
  const head = win.locator('.dw-head');
  const h = await head.boundingBox();
  await page.mouse.move(h.x + 60, h.y + 15);
  await page.mouse.down();
  await page.mouse.move(h.x - 40, h.y + 75, { steps: 5 });
  await page.mouse.up();
  const moved = await win.boundingBox();
  expect(Math.round(moved.x)).toBe(Math.round(before.x) - 100);
  expect(Math.round(moved.y)).toBe(Math.round(before.y) + 60);

  // Resize from the corner.
  const grip = await win.locator('.dw-resize').boundingBox();
  await page.mouse.move(grip.x + 5, grip.y + 5);
  await page.mouse.down();
  await page.mouse.move(grip.x - 95, grip.y - 45, { steps: 5 });
  await page.mouse.up();
  const resized = await win.boundingBox();
  expect(Math.round(resized.width)).toBe(Math.round(moved.width) - 100);

  await win.getByRole('button', { name: 'What movements are delayed right now?' }).click();
  await expect(win.getByText('Two movements are delayed.')).toBeVisible();

  // Another page: the window stays open with the same conversation, where it was left.
  await page.locator('.sidebar-nav').getByRole('link', { name: 'Notifications' }).click();
  await expect(page).toHaveURL(/\/notifications/);
  await expect(win.getByText('Two movements are delayed.')).toBeVisible();
  expect(Math.round((await win.boundingBox()).x)).toBe(Math.round(resized.x));

  // Minimise: a tab in the corner, kept across pages; restoring brings the window back where it was.
  await win.getByRole('button', { name: 'Minimise Daleel' }).click();
  await expect(win).toBeHidden();
  const tab = page.getByRole('button', { name: 'Restore Daleel' });
  await expect(tab).toBeVisible();
  await page.locator('.sidebar-nav').getByRole('link', { name: 'Planning' }).click();
  await expect(page).toHaveURL(/\/plans/);
  await expect(tab).toBeVisible();
  await tab.click();
  await expect(win).toBeVisible();
  await expect(win.getByText('Two movements are delayed.')).toBeVisible();
  expect(Math.round((await win.boundingBox()).x)).toBe(Math.round(resized.x));

  await win.getByRole('button', { name: 'Close Daleel' }).click();
  await expect(win).toBeHidden();
  await expect(tab).toBeHidden();
});

test('answer links open app pages with Daleel still open; page buttons ask with context', async ({ page }) => {
  const bodies = [];
  await page.route('**/ai/query', (route) => {
    bodies.push(route.request().postDataJSON());
    route.fulfill({ json: { ok: true, answer: '2 items need attention. See [the Day Board](/day-board?date=2026-11-20) or [this site](https://example.com/x).' } });
  });
  await page.evaluate(() => localStorage.removeItem('daleel.window'));

  // A page button opens Daleel and sends the question with what is on screen.
  await page.goto('/day-board?date=2026-11-20');
  await page.getByRole('button', { name: 'Brief me on this day' }).click();
  const win = page.getByRole('dialog', { name: 'Daleel' });
  await expect(win).toBeVisible();
  await expect(win.getByText('2 items need attention.')).toBeVisible();
  expect(bodies[0].context).toEqual({ page: 'day-board', date: '2026-11-20' });

  // Only app pages become links.
  await expect(win.getByText('this site')).toBeVisible();
  await expect(win.getByRole('link', { name: 'this site' })).toHaveCount(0);
  // Move away in the app (a browser reload would clear the conversation), then follow the link back.
  await page.locator('.sidebar-nav').getByRole('link', { name: 'Planning' }).click();
  await expect(page).toHaveURL(/\/plans/);
  await win.getByRole('link', { name: 'the Day Board' }).click();
  await expect(page).toHaveURL(/\/day-board\?date=2026-11-20/);
  await expect(win).toBeVisible();
});
