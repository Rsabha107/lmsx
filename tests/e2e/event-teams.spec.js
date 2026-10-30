import { test, expect } from '@playwright/test';
import { signIn } from './helpers.js';

// Event Teams (Teams & travel): the page renders its figures, filters and rows without console errors.
test.use({ viewport: { width: 1500, height: 1000 } });

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

test('teams and travel: figures, filters, a row per team and the flight refresh line', async ({ page }) => {
  await page.goto('/event-teams');
  await expect(page.getByRole('heading', { name: 'Teams & travel' })).toBeVisible();
  await expect(page.locator('.tl-kpi')).toHaveCount(5);

  const rows = page.locator('.tl-row');
  const total = await rows.count();
  expect(total).toBeGreaterThan(0);
  await expect(page.locator('.tl-count')).toHaveText(`${total} of ${total} teams`);

  // A flight with a number shows its refresh state under it.
  await expect(page.locator('.tl-refresh').first()).toBeVisible();
  // A travelling team without a departure says so.
  await expect(page.locator('.tl-not-booked').first()).toHaveText('Not booked');

  // Search narrows the list; clearing it brings everything back.
  await page.getByLabel('Search teams').fill('zzz-no-such-team');
  await expect(page.getByText('No teams match.')).toBeVisible();
  await page.getByLabel('Search teams').fill('');
  await expect(rows).toHaveCount(total);

  if (process.env.SHOT_DIR) await page.screenshot({ path: `${process.env.SHOT_DIR}/event-teams.png` });

  // "What if late?" asks Daleel with the flight as context (no real AI call here).
  const sent = [];
  await page.route('**/ai/query', (route) => { sent.push(route.request().postDataJSON()); route.fulfill({ json: { ok: true, answer: 'Nothing new is at risk.' } }); });
  await page.getByRole('button', { name: /^What if .* is late\?$/ }).first().click();
  await page.getByLabel('Minutes late').fill('45');
  await page.getByRole('button', { name: 'Ask Daleel' }).click();
  await expect(page.getByRole('dialog', { name: 'Daleel' }).getByText('Nothing new is at risk.')).toBeVisible();
  expect(sent[0].context.page).toBe('event-teams');
  expect(sent[0].question).toContain('is 45 min late');
  await page.getByRole('button', { name: 'Close Daleel' }).click();

  // Ticking a team shows the selection bar.
  await rows.first().locator('input[type=checkbox]').check();
  await expect(page.locator('.tl-selbar')).toContainText('1 team selected');
  await rows.first().locator('input[type=checkbox]').uncheck();

  // "Add team" opens an empty dialog.
  await page.getByRole('button', { name: 'Add team' }).click();
  const add = page.getByRole('dialog', { name: 'Add a team' });
  await expect(add.locator('.td-kicker')).toHaveText('New team');
  await expect(add.getByRole('button', { name: 'Delete team' })).toHaveCount(0);
  await add.getByRole('button', { name: 'Cancel' }).click();
  await expect(add).toHaveCount(0);

  // The pencil opens the team in the same dialog; adding a session and saving closes it.
  await rows.first().getByRole('button', { name: /^Edit / }).click();
  const edit = page.getByRole('dialog', { name: /^Edit team / });
  await expect(edit.locator('.td-kicker')).toHaveText('Edit team');
  await expect(edit.getByLabel('Team name')).not.toHaveValue('');
  await edit.getByRole('radio', { name: 'Guest' }).click();
  await expect(edit.getByRole('radio', { name: 'Guest' })).toHaveAttribute('aria-checked', 'true');
  // Travelling party: the total is players + staff.
  await edit.getByLabel('Players', { exact: true }).fill('23');
  await edit.getByLabel('Staff', { exact: true }).fill('12');
  await expect(edit.locator('.td-party-cell--total strong')).toHaveText('35');
  if (process.env.SHOT_DIR) {
    await edit.getByLabel('Players', { exact: true }).focus();
    await edit.locator('.td-party').screenshot({ path: `${process.env.SHOT_DIR}/team-party.png` });
  }
  // Gate & notes fold open from the toggle; the chip in the card header shows the gate.
  await edit.locator('.td-leg--arrival').getByRole('button', { name: 'Add gate & notes' }).click();
  await edit.getByLabel('arrival gate number').fill('C12');
  await expect(edit.getByRole('button', { name: 'arrival gate' })).toHaveText('Gate C12');
  // Remove marks the flight for removal on save; Undo brings the card back.
  await edit.getByRole('button', { name: 'Remove arrival flight' }).click();
  await expect(edit.locator('.td-leg--arrival .td-removed')).toContainText('will be removed when you save');
  await edit.locator('.td-leg--arrival').getByRole('button', { name: 'Undo' }).click();
  await expect(edit.getByLabel('arrival flight', { exact: true })).toBeVisible();
  // Both ends of a flight are editable; the card title follows the home airport.
  const into = edit.getByLabel('arrival to airport');
  if ((await into.locator('option').count()) > 1) {
    await into.selectOption({ index: 1 });
    const code = (await into.locator('option:checked').textContent()).split(' · ')[0].trim();
    await expect(edit.locator('.td-leg--arrival .td-leg-head')).toContainText(`Arrival · into ${code}`);
  }
  await edit.getByLabel('Training ground').fill('E2E pitch');
  await edit.getByLabel('Training date and time').fill('2030-01-05T16:00');
  await edit.getByRole('button', { name: 'Add session' }).click();
  await expect(edit.locator('.td-session').filter({ hasText: 'E2E pitch' })).toBeVisible();
  if (process.env.SHOT_DIR) await page.screenshot({ path: `${process.env.SHOT_DIR}/team-dialog.png`, fullPage: true });
  await edit.getByRole('button', { name: 'Save changes' }).click();
  await expect(edit).toHaveCount(0);

  // The saved session, party and gate are there when the dialog opens again.
  await rows.first().getByRole('button', { name: /^Edit / }).click();
  await expect(edit.locator('.td-session').filter({ hasText: 'E2E pitch' })).toBeVisible();
  await expect(edit.locator('.td-party-cell--total strong')).toHaveText('35');
  await expect(edit.getByRole('button', { name: 'arrival gate' })).toHaveText('Gate C12');

  await expect(edit.getByRole('button', { name: 'More flight details' })).toHaveCount(0);
  await edit.getByRole('button', { name: 'Cancel' }).click();
  await expect(edit).toHaveCount(0);

  // A row's flight date opens the same dialog (there is no separate flight form).
  await rows.first().locator('.tl-leg-main').first().click();
  await expect(edit).toBeVisible();
  await expect(page.getByText('Edit Flight')).toHaveCount(0);
  await edit.getByRole('button', { name: 'Cancel' }).click();
  await expect(edit).toHaveCount(0);

  // So does a click anywhere else on the row; the checkbox only selects.
  await rows.first().locator('.tl-hotel').click();
  await expect(edit).toBeVisible();
  await edit.getByRole('button', { name: 'Cancel' }).click();
  await rows.first().locator('input[type=checkbox]').check();
  await expect(edit).toHaveCount(0);
  await expect(page.locator('.tl-selbar')).toContainText('1 team selected');
  await rows.first().locator('input[type=checkbox]').uncheck();
});
