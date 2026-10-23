import { test, expect } from '@playwright/test';
import { signIn } from './helpers.js';

// Event-based pages show the shared "No Active Event" screen, and an event can be picked from it.
test.describe.configure({ mode: 'serial' });

async function clearActiveEvent(page) {
  const status = await page.evaluate(async () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const response = await fetch('/session/active-event', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
      body: JSON.stringify({ event_id: null }),
    });
    return response.status;
  });
  expect(status).toBeLessThan(400);
}

test.beforeEach(async ({ page }) => {
  await signIn(page);
  await clearActiveEvent(page);
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

const pages = [
  ['/matches', 'matches'],
  ['/event-teams', 'event teams'],
  ['/plans', 'plans'],
  ['/jobs', 'the jobs queue'],
  ['/crew-assignment', 'crew assignments'],
  ['/resource-schedule', 'resource schedules'],
  ['/day-board', 'the day board'],
  ['/duty-roster', 'the duty roster'],
];

test('every event-based page shows the same empty state', async ({ page }) => {
  for (const [url, what] of pages) {
    await page.goto(url);
    await expect(page.getByRole('heading', { name: 'No Active Event' }), url).toBeVisible();
    await expect(page.getByText(`Select an event to view ${what}.`), url).toBeVisible();
    await expect(page.locator('.nae-event').first(), url).toBeVisible();
  }

  await page.goto('/library');
  await page.locator('.tab', { hasText: 'Movement Templates' }).click();
  await expect(page.getByText('Select an event to view movement templates.')).toBeVisible();
});

test('picking an event from the empty state opens the page for it', async ({ page }) => {
  await page.goto('/matches');
  await expect(page.getByRole('heading', { name: 'No Active Event' })).toBeVisible();
  await page.locator('.nae-event').first().click();
  await expect(page.getByRole('heading', { name: 'No Active Event' })).toBeHidden();
  await expect(page.locator('.page-header').first()).toBeVisible();
});
