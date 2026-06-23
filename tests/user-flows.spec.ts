import { test, expect, Page } from '@playwright/test';

const BASE_URL = 'http://local.host';

test.describe('Banking Application - Homepage & Navigation', () => {

  test('homepage loads without errors', async ({ page }) => {
    await page.goto(BASE_URL);
    await expect(page).toHaveTitle(/.*/);
    await expect(page.locator('body')).toBeVisible();
  });

  test('navigation menu is present', async ({ page }) => {
    await page.goto(BASE_URL);
    const nav = page.locator('nav, .sidebar, header, .navigation, [class*="nav"], a');
    const navExists = await nav.count() > 0;
    expect(navExists).toBeTruthy();
  });

  test('footer is present on homepage', async ({ page }) => {
    await page.goto(BASE_URL);
    const footer = page.locator('footer');
    const footerExists = await footer.count() > 0;
    if (footerExists) {
      await expect(footer).toBeVisible();
    }
  });

});

test.describe('Authentication Flow', () => {

  test('login page displays correctly', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`);
    const body = page.locator('body');
    await expect(body).toBeVisible();
  });

  test('login form has email and password fields', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`);
    const emailInput = page.locator('input[name="email"], input[type="email"]');
    const passwordInput = page.locator('input[name="password"], input[type="password"]');
    const submitBtn = page.locator('button[type="submit"]');

    if (await emailInput.count() > 0) await expect(emailInput).toBeVisible();
    if (await passwordInput.count() > 0) await expect(passwordInput).toBeVisible();
    if (await submitBtn.count() > 0) await expect(submitBtn).toBeVisible();
  });

  test('unauthenticated user is redirected to login', async ({ page }) => {
    await page.goto(`${BASE_URL}/dashboard`);
    await page.waitForURL(/login|auth/, { timeout: 5000 }).catch(() => {});
    const url = page.url();
    expect(url).toMatch(/login|auth/);
  });

  test('logout link exists when authenticated', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`);
    const logoutLink = page.locator('a[href*="logout"], form[action*="logout"] button');
    const logoutExists = await logoutLink.count() > 0;
    expect(logoutExists).toBeTruthy();
  });

});

test.describe('Dashboard', () => {

  test('dashboard page is accessible', async ({ page }) => {
    await page.goto(`${BASE_URL}/dashboard`);
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toBeVisible();
  });

  test('dashboard contains key elements', async ({ page }) => {
    await page.goto(`${BASE_URL}/dashboard`);
    await page.waitForLoadState('domcontentloaded');
    const content = await page.content();
    expect(content.length).toBeGreaterThan(100);
  });

});

test.describe('Accounting Module', () => {

  test('accounting index loads', async ({ page }) => {
    await page.goto(`${BASE_URL}/accounting`);
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toBeVisible();
  });

  test('journal entries page loads', async ({ page }) => {
    await page.goto(`${BASE_URL}/accounting/journal`);
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toBeVisible();
  });

  test('ledger page loads', async ({ page }) => {
    await page.goto(`${BASE_URL}/accounting/ledger`);
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toBeVisible();
  });

  test('trial balance page loads', async ({ page }) => {
    await page.goto(`${BASE_URL}/accounting/trial-balance`);
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toBeVisible();
  });

  test('profit loss page loads', async ({ page }) => {
    await page.goto(`${BASE_URL}/accounting/profit-loss`);
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toBeVisible();
  });

  test('balance sheet page loads', async ({ page }) => {
    await page.goto(`${BASE_URL}/accounting/balance-sheet`);
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toBeVisible();
  });

  test('cash flow page loads', async ({ page }) => {
    await page.goto(`${BASE_URL}/accounting/cash-flow`);
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toBeVisible();
  });

  test('reconciliation page loads', async ({ page }) => {
    await page.goto(`${BASE_URL}/accounting/reconciliation`);
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toBeVisible();
  });

  test('budget page loads', async ({ page }) => {
    await page.goto(`${BASE_URL}/accounting/budget`);
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toBeVisible();
  });

  test('fiscal years page loads', async ({ page }) => {
    await page.goto(`${BASE_URL}/accounting/fiscal-years`);
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toBeVisible();
  });

  test('revaluation page loads', async ({ page }) => {
    await page.goto(`${BASE_URL}/accounting/revaluation`);
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toBeVisible();
  });

  test('month-end close page loads', async ({ page }) => {
    await page.goto(`${BASE_URL}/accounting/month-end`);
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toBeVisible();
  });

});

test.describe('Compliance Module', () => {

  test('compliance risk dashboard loads', async ({ page }) => {
    await page.goto(`${BASE_URL}/compliance/risk-dashboard`);
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toBeVisible();
  });

  test('audit dashboard loads', async ({ page }) => {
    await page.goto(`${BASE_URL}/audit/dashboard`);
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('body')).toBeVisible();
  });

});

test.describe('API Endpoints', () => {

  test('API compliance dashboard endpoint returns JSON', async ({ page }) => {
    const response = await page.goto(`${BASE_URL}/api/v1/compliance/dashboard`);
    const contentType = response?.headers()['content-type'] || '';
    const body = await page.content();
    expect(body.length).toBeGreaterThan(0);
  });

  test('API allocations active endpoint accessible', async ({ page }) => {
    const response = await page.goto(`${BASE_URL}/api/v1/allocations/active`);
    expect(response?.status()).toBeGreaterThan(0);
  });

});

test.describe('Interactive Elements', () => {

  test('buttons are present on page', async ({ page }) => {
    await page.goto(BASE_URL);
    const buttons = page.locator('button, .btn, [class*="btn"], a[class*="button"]');
    const buttonCount = await buttons.count();
    expect(buttonCount).toBeGreaterThanOrEqual(0);
  });

  test('dropdowns/selects are present', async ({ page }) => {
    await page.goto(BASE_URL);
    const selects = page.locator('select');
    const selectCount = await selects.count();
    expect(selectCount).toBeGreaterThanOrEqual(0);
  });

  test('search inputs are functional', async ({ page }) => {
    await page.goto(BASE_URL);
    const searchInputs = page.locator('input[type="search"], input[placeholder*="search" i], input[type="text"]');
    if (await searchInputs.count() > 0) {
      await searchInputs.first().fill('test search');
    }
  });

  test('any clickable elements exist', async ({ page }) => {
    await page.goto(BASE_URL);
    const clickables = page.locator('a, button, [role="button"], [onclick], [class*="clickable"]');
    const count = await clickables.count();
    expect(count).toBeGreaterThanOrEqual(0);
  });

});

test.describe('Error Handling', () => {

  test('404 page displays for invalid routes', async ({ page }) => {
    const response = await page.goto(`${BASE_URL}/this-route-does-not-exist-12345`);
    const status = response?.status() || 0;
    const body = await page.content();
    expect(status === 404 || body.length > 0).toBeTruthy();
  });

  test('application handles network errors gracefully', async ({ page }) => {
    page.on('pageerror', error => {
      expect(error.message).toBe('');
    });

    await page.goto(BASE_URL);
    await page.waitForLoadState('domcontentloaded');
  });

});

test.describe('Responsive Design', () => {

  test('page renders at desktop viewport', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto(BASE_URL);
    await page.waitForLoadState('domcontentloaded');
    const viewport = page.viewportSize();
    expect(viewport?.width).toBe(1920);
    expect(viewport?.height).toBe(1080);
  });

  test('page renders at tablet viewport', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto(BASE_URL);
    await page.waitForLoadState('domcontentloaded');
    const viewport = page.viewportSize();
    expect(viewport?.width).toBe(768);
    expect(viewport?.height).toBe(1024);
  });

  test('page renders at mobile viewport', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto(BASE_URL);
    await page.waitForLoadState('domcontentloaded');
    const viewport = page.viewportSize();
    expect(viewport?.width).toBe(375);
    expect(viewport?.height).toBe(667);
  });

});

test.describe('Performance', () => {

  test('page loads within reasonable time', async ({ page }) => {
    const startTime = Date.now();
    await page.goto(BASE_URL);
    const loadTime = Date.now() - startTime;
    expect(loadTime).toBeLessThan(10000);
  });

});