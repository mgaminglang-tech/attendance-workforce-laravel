import { test as setup, expect } from '@playwright/test';
import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';

setup('log in as an existing Global Admin', async ({ page }) => {
    const email = process.env.E2E_ADMIN_EMAIL;
    const password = process.env.E2E_ADMIN_PASSWORD;

    if (!email || !password) {
        throw new Error('Set E2E_ADMIN_EMAIL and E2E_ADMIN_PASSWORD in the shell or .env.e2e before running E2E tests.');
    }

    await page.goto('/login');
    await page.getByLabel('Email address', { exact: true }).fill(email);
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();

    await expect(page).toHaveURL((url) => url.pathname === '/admin/dashboard');
    await expect(page.getByRole('heading', { name: 'Workforce today', exact: true })).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'Desktop primary navigation' })
        .getByRole('link', { name: 'DTR', exact: true })).toBeVisible();

    await mkdir(resolve('e2e/.auth'), { recursive: true });
    await page.context().storageState({ path: resolve('e2e/.auth/admin.json') });
});
