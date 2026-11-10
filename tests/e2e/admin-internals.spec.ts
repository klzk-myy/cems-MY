import { test, expect } from '@playwright/test';
import { gotoVerified } from './helpers/auth';
import { USERS } from './helpers/env';

// Admin internals — index + detail render checks plus the users create
// form. These surfaces are admin-only; each asserts a real 200 with
// content rather than relying on PHPUnit's HTTP coverage of POSTs.
for (const url of [
    '/admin/audit-logs',
    '/admin/thresholds',
    '/admin/branch-scope',
    '/admin/sanctions',
    '/system/alerts',
    '/users',
    '/users/create',
    '/branches/create',
    '/system/currencies',
    '/system/currencies/create',
]) {
    test(`admin surface ${url} renders`, async ({ page }) => {
        await gotoVerified(page, url, USERS.admin);
        const res = await page.goto(url);
        expect(res?.status(), url).toBe(200);
        await expect(page.locator('main')).not.toBeEmpty();
    });
}

test('audit log detail expands a chained entry', async ({ page }) => {
    await gotoVerified(page, '/admin/audit-logs', USERS.admin);
    const link = page.locator('a[href*="/admin/audit-logs/"]').first();
    await expect(link, 'no audit entries to open').toBeVisible();
    await link.click();
    await page.waitForURL(/\/admin\/audit-logs\/\d+/);
    await expect(page.locator('main')).not.toBeEmpty();
});

test('user create form provisions an account', async ({ page, context }) => {
    const suffix = Date.now().toString(36);
    await gotoVerified(page, '/users/create', USERS.admin);

    // users.store is behind password.confirm — step the session up first.
    const confirm = await context.request.post(`${new URL(page.url()).origin}/confirm-password`, {
        form: { _token: await page.locator('meta[name="csrf-token"]').getAttribute('content') ?? '', password: USERS.admin.password },
    });
    expect([200, 302, 303], `password confirm ${confirm.status()}`).toContain(confirm.status());
    await page.fill('input[name="username"]', `e2e_user_${suffix}`);
    await page.fill('input[name="email"]', `e2e_${suffix}@example.test`);
    await page.fill('input[name="password"]', 'E2e-Passw0rd!23');
    await page.fill('input[name="password_confirmation"]', 'E2e-Passw0rd!23');
    const role = page.locator('select[name="role"]');
    if (await role.count()) {
        const opt = await role.locator('option:not([value=""])').first().getAttribute('value');
        if (opt) await role.selectOption(opt);
    }
    const branch = page.locator('select[name="branch_id"]');
    if (await branch.count()) {
        const opt = await branch.locator('option:not([value=""])').first().getAttribute('value');
        if (opt) await branch.selectOption(opt);
    }
    await Promise.all([
        page.waitForURL(/\/users(\/\d+)?/, { timeout: 15_000 }).catch(() => null),
        page.locator('main form button[type="submit"]').last().click(),
    ]);
    await expect(page.locator('body')).toContainText(`e2e_user_${suffix}`, { timeout: 15_000 });
});

test('first branch edit page renders its form', async ({ page }) => {
    await gotoVerified(page, '/branches', USERS.admin);
    const edit = page.locator('a[href*="/branches/"][href$="/edit"]').first();
    if (await edit.count()) {
        await edit.click();
        await page.waitForLoadState('load');
        await expect(page.locator('main form input[name="name"], main form input').first()).not.toBeEmpty();
    } else {
        const link = page.locator('a[href*="/branches/"]').first();
        await expect(link).toBeVisible();
        await link.click();
        await expect(page.locator('main')).not.toBeEmpty();
    }
});
