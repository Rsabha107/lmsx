import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { signIn } from './helpers.js';

// Library → Checkpoints: list, New checkpoint, edit panel, filters and the bulk actions.
test.describe.configure({ mode: 'serial' });

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

test('create a checkpoint, edit it in the panel and change its category in bulk', async ({ page }) => {
  await page.goto('/library');
  await expect(page.locator('.ck-head')).toContainText('Checkpoint');

  await page.getByRole('button', { name: 'New Checkpoint' }).click();
  const dialog = page.locator('.modal-container').filter({ hasText: 'New checkpoint' });
  await expect(dialog.getByRole('button', { name: 'Create checkpoint' })).toBeDisabled();
  await dialog.getByLabel('Name').fill('E2E BAG CHECK');
  await dialog.getByRole('button', { name: '+ Bag count' }).click();
  await expect(dialog.getByRole('button', { name: '✓ Bag count' })).toBeVisible();
  await dialog.getByRole('button', { name: 'Create checkpoint' }).click();
  await expect(dialog).toBeHidden();

  // The new checkpoint opens in the edit panel.
  const panel = page.getByRole('complementary', { name: 'Edit checkpoint' });
  await expect(panel.getByLabel('Name')).toHaveValue('E2E BAG CHECK');
  await expect(panel).toContainText('Not used in any checkpoint template');
  const row = page.locator('.ck-item', { hasText: 'E2E BAG CHECK' });
  await expect(row).toContainText('Bag count');
  await expect(row).toContainText('Unused');

  await expect(panel.getByRole('button', { name: 'Save' })).toBeDisabled();
  await panel.getByPlaceholder('What the crew must do at this checkpoint').fill('Count the bags at the hotel');
  await panel.getByRole('radio', { name: 'GPS' }).click();
  await panel.getByRole('button', { name: 'Save' }).click();
  await expect(row).toContainText('Count the bags at the hotel');
  await expect(row).toContainText('GPS');

  // Filters
  await page.getByLabel('Search checkpoints').fill('E2E BAG');
  await page.getByLabel('Show checkpoints').selectOption('used');
  await expect(row).toHaveCount(0);
  await page.getByLabel('Show checkpoints').selectOption('unused');
  await expect(row).toBeVisible();

  // Bulk: change category
  await row.getByRole('checkbox').check();
  const bar = page.getByRole('toolbar', { name: 'Selected checkpoints' });
  await expect(bar).toContainText('1 selected');
  await bar.getByRole('button', { name: 'Change category' }).click();
  const category = page.locator('.modal-container').filter({ hasText: 'Change category' });
  await category.locator('select').selectOption('A&D');
  await category.getByRole('button', { name: 'Apply' }).click();
  await expect(category).toBeHidden();
  await expect(row.locator('.ck-cat')).toContainText('A&D');
  await expect(bar).toBeHidden();

  // Bulk: add to template shows what would happen (the add itself is covered by CheckpointLibraryTest).
  await row.getByRole('checkbox').check();
  await bar.getByRole('button', { name: 'Add to template…' }).click();
  const add = page.locator('.modal-container').filter({ hasText: 'Add to checkpoint template' });
  await add.getByLabel('Checkpoint template').selectOption({ index: 1 });
  await expect(add).toContainText('1 will be added after the last step, in code order.');
  await add.getByRole('button', { name: 'Cancel' }).click();
  await expect(add).toBeHidden();
});

/** One job whose first step is the seeded "Depart hotel" library checkpoint. */
function seedJobUsingDepartHotel() {
  execFileSync(process.env.PHP_BINARY || 'php', [
    'artisan', 'tinker', '--env=e2e',
    "--execute=$m = App\\Models\\Movement::where('code', 'TRP-90001')->first();"
      + "$j = App\\Models\\JobOperation::create(['job_id' => 'JOB-E2E-RENAME', 'event_id' => $m->event_id, 'movement_id' => $m->id, 'team_id' => $m->team_id, 'functional_area' => 'LOG', 'status' => 'pending']);"
      + "App\\Models\\JobCheckpoint::create(['event_id' => $m->event_id, 'job_id' => $j->id, 'order' => 1, 'name' => 'Depart hotel', 'type' => 'manual', 'state' => 'pending', 'is_required' => true, 'checkpoint_id' => App\\Models\\Checkpoint::where('code', 'CP-DEP')->value('id')]);",
  ], { stdio: 'pipe' });
}

test('renaming a checkpoint that jobs use asks first, then renames it on those jobs', async ({ page }) => {
  seedJobUsingDepartHotel();
  await page.goto('/library');
  await page.locator('.ck-item', { hasText: 'Depart hotel' }).click();
  const panel = page.getByRole('complementary', { name: 'Edit checkpoint' });
  await panel.getByLabel('Name').fill('Depart team hotel');
  await panel.getByRole('button', { name: 'Save' }).click();

  const confirm = page.getByRole('dialog', { name: 'Rename this step on existing jobs?' });
  await expect(confirm).toContainText('Depart hotel becomes Depart team hotel');
  await expect(confirm).toContainText('renamed on 1 job, past and future');

  // Cancel keeps the edit unsaved; confirming saves and renames.
  await confirm.getByRole('button', { name: 'Cancel' }).click();
  await expect(confirm).toBeHidden();
  await expect(panel.getByLabel('Name')).toHaveValue('Depart team hotel');
  await panel.getByRole('button', { name: 'Save' }).click();
  await confirm.getByRole('button', { name: 'Rename and save' }).click();
  await expect(page.getByText(/renamed on 1 job step/)).toBeVisible();
  await expect(page.locator('.ck-item', { hasText: 'Depart team hotel' })).toBeVisible();
});
