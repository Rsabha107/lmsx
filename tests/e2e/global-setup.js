import { execFileSync } from 'node:child_process';

// Fresh lmsx_e2e for every run; never touches the dev or PHPUnit databases.
export default function globalSetup() {
  execFileSync(process.env.PHP_BINARY ?? 'php', [
    'artisan', 'migrate:fresh', '--env=e2e', '--force', '--seed', '--seeder=E2eSeeder',
  ], { stdio: 'inherit' });
}
