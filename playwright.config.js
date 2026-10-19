import { defineConfig } from '@playwright/test';

// Browser smoke tests for the console. They run against their own server and
// database (.env.e2e -> lmsx_e2e), reset by tests/e2e/global-setup.js.
// The Vite dev server (npm run dev) must be running, so the tests see the
// current source rather than the last build.
const php = process.env.PHP_BINARY ?? 'php';

export default defineConfig({
  testDir: 'tests/e2e',
  globalSetup: './tests/e2e/global-setup.js',
  fullyParallel: false,
  workers: 1,
  // No retries: the tests are one serial flow on shared data, so a retry starts from changed data.
  timeout: 60_000,
  expect: { timeout: 20_000 },
  reporter: [['list']],
  use: {
    baseURL: 'http://127.0.0.1:8020',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  webServer: {
    command: `${php} artisan serve --host=127.0.0.1 --port=8020`,
    url: 'http://127.0.0.1:8020/login',
    env: { ...process.env, APP_ENV: 'e2e', PHP_CLI_SERVER_WORKERS: '4' },
    reuseExistingServer: true,
    timeout: 60_000,
  },
});
