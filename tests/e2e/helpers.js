import { expect } from '@playwright/test';

export const ADMIN = { email: 'e2e.admin@example.test', password: 'e2e-password' };

/** Sign in as the E2E admin and make the seeded event the active one. */
export async function signIn(page) {
  await page.goto('/login');
  await page.locator('#email').fill(ADMIN.email);
  await page.locator('#password').fill(ADMIN.password);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await page.waitForURL((url) => !url.pathname.startsWith('/login'));

  const status = await page.evaluate(async () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const response = await fetch('/session/active-event', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
      body: JSON.stringify({ event_id: 1 }),
    });
    return response.status;
  });
  expect(status).toBeLessThan(400);
}

/** The Plans list; the page remembers the last plan or movement view. */
export async function gotoPlansList(page) {
  await page.goto('/plans');
  const back = page.getByText('View All Plans');
  const newPlan = page.getByRole('button', { name: 'New Plan' }).first();
  await expect(back.or(newPlan).first()).toBeVisible();
  if (await back.isVisible()) await back.click();
  await expect(newPlan).toBeVisible();
}

/** The New Plan dialog, opened from the Plans tab. */
export async function openNewPlan(page) {
  await gotoPlansList(page);
  await page.getByRole('button', { name: 'New Plan' }).first().click();
  const dialog = page.locator('.modal').filter({ hasText: 'New plan' });
  await expect(dialog).toBeVisible();
  return dialog;
}

