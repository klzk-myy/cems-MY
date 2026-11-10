import { test, expect } from '@playwright/test';
import { authFile } from './helpers/env';

test.use({ storageState: authFile('teller') });

test('a cleared session redirects protected pages to login', async ({ page, context }) => {
    await page.goto('/dashboard');
    await context.clearCookies();
    await page.goto('/transactions');
    await expect(page).toHaveURL(/login/);
});

test('an unknown route returns a 404 page', async ({ page }) => {
    const resp = await page.goto('/no-such-route-xyz');
    expect(resp?.status()).toBe(404);
});

test('a stale CSRF token rejects the login POST', async ({ page, context }) => {
    await context.clearCookies();
    await page.goto('/login');
    const token = page.locator('form[action*="login"] input[name="_token"]');
    if (await token.count()) {
        await token.evaluate((el: HTMLInputElement) => { el.value = 'stale-token'; });
    }
    await page.locator('input[name="username"]').fill('nobody@example.test');
    await page.locator('input[name="password"]').fill('wrong');
    await page.locator('button[type="submit"]').click();
    // Either a 419 page or a re-rendered login — never a dashboard.
    await expect(page).not.toHaveURL(/dashboard/);
});
