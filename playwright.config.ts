import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests against a real browser + the app served in a fully isolated e2e
 * environment (dedicated `..._e2e` databases — never dev data). The `webServer` first
 * builds the e2e DBs (scripts/e2e.sh up) and then serves the app; global-teardown
 * drops them again. A few critical flows only — this layer catches SSR/routing/JS
 * errors the jsdom (Vitest) layer can't.
 *
 *   bun run test:e2e
 */
export default defineConfig({
    testDir: './e2e',
    fullyParallel: false,
    workers: 1,
    forbidOnly: true,
    // One retry absorbs the rare transient hitch of the single-threaded dev server
    // (php artisan serve) under a long serial run — product failures still fail twice.
    retries: 1,
    reporter: [['list']],
    globalTeardown: './e2e/global-teardown.ts',
    timeout: 30_000,
    expect: { timeout: 10_000 },
    use: {
        baseURL: 'http://localhost:8123',
        trace: 'retain-on-failure',
    },
    projects: [
        // Authenticate once; every spec below reuses the saved session.
        { name: 'setup', testMatch: /auth\.setup\.ts/ },
        {
            name: 'chromium',
            testIgnore: /auth\.setup\.ts/,
            use: {
                ...devices['Desktop Chrome'],
                storageState: 'e2e/.auth/user.json',
            },
            dependencies: ['setup'],
        },
    ],
    webServer: {
        // Build the isolated e2e databases, then serve the app against them.
        command:
            'bash scripts/e2e.sh up && php artisan serve --env=e2e --port=8123 --no-reload',
        url: 'http://localhost:8123/e2e/login',
        reuseExistingServer: false,
        timeout: 120_000,
        stdout: 'pipe',
        stderr: 'pipe',
    },
});
