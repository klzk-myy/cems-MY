import { test, expect } from '@playwright/test';

const BASE_URL = 'http://local.host';

async function removeOverlay(page) {
  await page.evaluate(() => {
    const overlays = document.querySelectorAll('.fixed.inset-0, [class*="overlay"], [class*="modal-backdrop"]');
    overlays.forEach(el => {
      if (el.tagName !== 'BODY' && el.tagName !== 'HTML') {
        (el as HTMLElement).style.display = 'none';
      }
    });
  });
  await page.waitForTimeout(200);
}

test.describe('Counter Management Browser Tests', () => {

  test.beforeEach(async ({ page }) => {
    // Login
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'domcontentloaded' });
    await page.fill('input#username', 'admin');
    await page.fill('input#password', 'Admin@123456');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(2000);
    await removeOverlay(page);
  });

  test('counters page loads correctly', async ({ page }) => {
    await page.goto(`${BASE_URL}/counters`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);
    await removeOverlay(page);
    
    // Check page title
    await expect(page.locator('h1').first()).toContainText('Counters');
    
    // Check for counters table
    const table = page.locator('table');
    await expect(table).toBeVisible();
    
    // Check for action links
    const viewLinks = page.locator('a:has-text("View"), a:has-text("Open")');
    const linkCount = await viewLinks.count();
    console.log(`Found ${linkCount} action links`);
    
    await page.screenshot({ path: 'test-results/screenshots/counters-page.png', fullPage: true });
  });

  test('open counter flow', async ({ page }) => {
    await page.goto(`${BASE_URL}/counters`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);
    await removeOverlay(page);
    
    // Look for Open links
    const openLinks = page.locator('a:has-text("Open")');
    const openCount = await openLinks.count();
    console.log(`Found ${openCount} counters with Open option`);
    
    if (openCount > 0) {
      // Click first Open link
      await openLinks.first().click();
      await page.waitForTimeout(2000);
      await removeOverlay(page);
      
      console.log('Current URL:', page.url());
      await page.screenshot({ path: 'test-results/screenshots/counter-open.png', fullPage: true });
      
      // Check if it's the open counter form
      const url = page.url();
      if (url.includes('open')) {
        console.log('On counter open page');
      }
    }
  });

  test('view counter details', async ({ page }) => {
    await page.goto(`${BASE_URL}/counters`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);
    await removeOverlay(page);
    
    // Look for View links (counters that are open)
    const viewLinks = page.locator('a:has-text("View")');
    const viewCount = await viewLinks.count();
    console.log(`Found ${viewCount} counters with View option`);
    
    if (viewCount > 0) {
      await viewLinks.first().click();
      await page.waitForTimeout(2000);
      await removeOverlay(page);
      
      console.log('Current URL:', page.url());
      await page.screenshot({ path: 'test-results/screenshots/counter-view.png', fullPage: true });
    }
  });

  test('navigate through counter sidebar', async ({ page }) => {
    // Go to counters page
    await page.goto(`${BASE_URL}/counters`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);
    await removeOverlay(page);
    
    // Check sidebar navigation exists
    const sidebar = page.locator('.sidebar');
    if (await sidebar.count() > 0) {
      console.log('Sidebar found');
      
      // Look for counter-related nav items
      const navItems = await page.locator('.sidebar .nav-item').allTextContents();
      console.log('Nav items:', navItems.slice(0, 10).join(', '));
    }
    
    await page.screenshot({ path: 'test-results/screenshots/counters-sidebar.png', fullPage: true });
  });

  test('check counter statistics', async ({ page }) => {
    await page.goto(`${BASE_URL}/counters`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1000);
    await removeOverlay(page);
    
    // Look for stat cards
    const statCards = page.locator('.card, [class*="stat"], [class*="counter"]');
    const cardCount = await statCards.count();
    console.log(`Found ${cardCount} stat cards`);
    
    // Check for numbers/stats
    const pageText = await page.textContent('body');
    const hasNumbers = /\d+/.test(pageText || '');
    console.log('Page has numbers:', hasNumbers);
    
    await page.screenshot({ path: 'test-results/screenshots/counters-stats.png', fullPage: true });
  });

});