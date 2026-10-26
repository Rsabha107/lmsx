import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { signIn } from './helpers.js';

// Setups → Users: add (invite / SC/LOC), create manually with a temporary password, deactivate and reactivate.
test.describe.configure({ mode: 'serial' });

const watch = (page) => {
  const problems = [];
  page.on('console', (msg) => {
    const text = msg.text();
    if (msg.type() === 'error' || text.startsWith('[Vue warn]')) problems.push(text);
  });
  page.on('pageerror', (error) => problems.push(error.message));
  test.info().problems = problems;
};

test.afterEach(async () => {
  expect(test.info().problems, 'console errors and Vue warnings').toEqual([]);
});

/** The mail is not readable here, so give the latest invitation a known code (only its hash is stored). */
function setInvitationCode(code) {
  execFileSync(process.env.PHP_BINARY || 'php', [
    'artisan', 'tinker', '--env=e2e',
    `--execute=App\\Models\\UserInvitation::latest('id')->first()->update(['code_hash' => hash('sha256', '${code}')]);`,
  ], { stdio: 'pipe' });
}

const row = (page, text) => page.locator('.us-row', { hasText: text });

test('invite an external user, who creates the account from the short link', async ({ page }) => {
  await signIn(page);
  watch(page);
  await page.goto('/setups/users');

  await page.getByRole('button', { name: '+ Add user' }).click();
  const dialog = page.getByRole('dialog', { name: 'Invite an external user' });
  await dialog.locator('#am-email').fill('invited.person@gwc.example');
  await expect(dialog.getByText(/single-use link, valid for 7 days/)).toBeVisible();
  await expect(dialog.getByText('Needs a role, an event')).toBeVisible();
  await dialog.getByRole('button', { name: /Ground control/ }).click();
  await dialog.locator('.ac-event').first().click();
  await dialog.getByRole('button', { name: 'Send invitation' }).click();
  await expect(dialog).toBeHidden();

  await expect(row(page, 'invited.person@gwc.example')).toContainText('INVITED');
  await page.getByRole('button', { name: /^Invited/ }).click();
  await expect(page.locator('.us-row:not(.us-row--head)')).toHaveCount(1);
  await row(page, 'invited.person@gwc.example').click();
  await expect(page.getByLabel('User details')).toContainText('Invitation pending');

  setInvitationCode('E2eInvite123');
  await page.context().clearCookies();
  await page.goto('/i/E2eInvite123');
  await expect(page.getByRole('heading', { name: 'Create your account' })).toBeVisible();
  expect(page.url()).toContain('signature=');
  await page.locator('#name').fill('Invited Person');
  await page.locator('#password').fill('a-long-password-1');
  await page.locator('#password_confirmation').fill('a-long-password-1');
  await page.getByRole('button', { name: 'Create account' }).click();
  await page.waitForURL((url) => !url.pathname.startsWith('/invitation'));

  await page.context().clearCookies();
  await page.goto('/i/E2eInvite123');
  await expect(page.getByRole('heading', { name: 'Invitation unavailable' })).toBeVisible();
});

test('add SC/LOC staff, then deactivate and reactivate them from the panel', async ({ page }) => {
  await signIn(page);
  watch(page);
  await page.goto('/setups/users');

  await page.getByRole('button', { name: '+ Add user' }).click();
  await page.getByRole('tab', { name: 'SC/LOC staff · SSO' }).click();
  const dialog = page.getByRole('dialog', { name: 'Add SC/LOC staff' });
  await dialog.locator('#am-email').fill('e2e.staff@sc.qa');
  await dialog.locator('#am-name').fill('E2E Staff');
  await dialog.getByRole('button', { name: /^Transport/ }).click();
  await dialog.locator('.ac-event').first().click();
  await dialog.getByRole('button', { name: 'Grant access' }).click();
  await expect(dialog).toBeHidden();

  const panel = page.getByLabel('User details');
  await expect(panel).toContainText('E2E Staff');
  await expect(panel).toContainText('SC/LOC account');

  await panel.getByRole('button', { name: 'Deactivate' }).click();
  await page.getByRole('dialog', { name: 'Deactivate user' }).getByRole('button', { name: 'Deactivate' }).click();
  await expect(row(page, 'e2e.staff@sc.qa')).toContainText('INACTIVE');
  await page.getByRole('button', { name: /^Inactive/ }).click();
  await expect(row(page, 'e2e.staff@sc.qa')).toBeVisible();

  await page.getByLabel('User details').getByRole('button', { name: 'Activate' }).click();
  await expect(row(page, 'e2e.staff@sc.qa')).toHaveCount(0);
});

test('create a user manually with a temporary password they must change', async ({ page }) => {
  await signIn(page);
  watch(page);
  await page.goto('/setups/users');

  await page.getByRole('button', { name: 'Create manually' }).click();
  const dialog = page.getByRole('dialog', { name: 'Create user manually' });
  await dialog.locator('#uf-name').fill('Temp Person');
  await dialog.locator('#uf-email').fill('temp.person@gwc.example');
  await dialog.locator('#uf-pw').fill('temporary-1');
  await dialog.getByRole('button', { name: /^Transport/ }).click();
  await dialog.locator('.ac-event').first().click();
  await expect(dialog.getByText('Ready to create')).toBeVisible();
  await dialog.getByRole('button', { name: 'Create user' }).click();
  await expect(dialog).toBeHidden();
  await expect(row(page, 'temp.person@gwc.example')).toBeVisible();

  await page.context().clearCookies();
  await page.goto('/login');
  await page.locator('#email').fill('temp.person@gwc.example');
  await page.locator('#password').fill('temporary-1');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page.getByRole('heading', { name: 'Choose your password' })).toBeVisible();
  await page.goto('/jobs');
  await expect(page).toHaveURL(/\/password\/change/);
  await page.locator('#password').fill('my-own-password-1');
  await page.locator('#password_confirmation').fill('my-own-password-1');
  await page.getByRole('button', { name: 'Set password and continue' }).click();
  await page.waitForURL((url) => !url.pathname.startsWith('/password'));
});
