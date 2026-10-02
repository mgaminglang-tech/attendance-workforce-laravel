import { defineConfig, devices } from '@playwright/test';
import { existsSync } from 'node:fs';
import { resolve } from 'node:path';
import { loadEnvFile } from 'node:process';

const environmentFile = resolve('.env.e2e');

if (existsSync(environmentFile)) {
    loadEnvFile(environmentFile);
}

export default defineConfig({
    testDir: './e2e',
    outputDir: './test-results',
    fullyParallel: false,
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    reporter: [
        ['list'],
        ['html', { outputFolder: 'playwright-report', open: 'never' }],
    ],
    use: {
        baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:8000',
        actionTimeout: 10_000,
        navigationTimeout: 15_000,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'setup',
            testMatch: '**/*.setup.js',
            use: {
                ...devices['Desktop Chrome'],
                trace: 'off',
                screenshot: 'off',
                video: 'off',
            },
        },
        {
            name: 'chromium',
            testMatch: '**/*.spec.js',
            dependencies: ['setup'],
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1440, height: 900 },
                storageState: resolve('e2e/.auth/admin.json'),
            },
        },
        {
            name: 'mobile-chromium',
            testMatch: '**/*.spec.js',
            grep: /@month-control/,
            dependencies: ['setup'],
            use: {
                ...devices['Pixel 7'],
                storageState: resolve('e2e/.auth/admin.json'),
            },
        },
    ],
});
