import { test, expect } from '@playwright/test';

const BASE_URL = 'http://local.host';

test.describe('Comprehensive Click Test', () => {

  test('test actual click on login page elements', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(3000);
    
    // Take initial screenshot
    await page.screenshot({ path: 'test-results/screenshots/01-initial.png' });
    
    // Try clicking on the username input
    try {
      await page.click('input#username', { timeout: 5000 });
      console.log('✓ Clicked username input');
      await page.screenshot({ path: 'test-results/screenshots/02-after-username-click.png' });
    } catch (e) {
      console.log('✗ Failed to click username:', (e as Error).message);
    }
    
    // Try typing
    try {
      await page.fill('input#username', 'testuser');
      console.log('✓ Filled username');
      await page.screenshot({ path: 'test-results/screenshots/03-after-username-type.png' });
    } catch (e) {
      console.log('✗ Failed to type username:', (e as Error).message);
    }
    
    // Try clicking password
    try {
      await page.click('input#password', { timeout: 5000 });
      console.log('✓ Clicked password input');
      await page.fill('input#password', 'testpass');
      console.log('✓ Filled password');
      await page.screenshot({ path: 'test-results/screenshots/04-after-password.png' });
    } catch (e) {
      console.log('✗ Failed password interaction:', (e as Error).message);
    }
    
    // Try clicking the submit button
    try {
      await page.click('button[type="submit"]', { timeout: 5000 });
      console.log('✓ Clicked submit button');
      await page.waitForTimeout(1000);
      await page.screenshot({ path: 'test-results/screenshots/05-after-submit.png' });
    } catch (e) {
      console.log('✗ Failed to click submit:', (e as Error).message);
    }
  });

  test('check for invisible overlay with pointer-events', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    
    // Check all elements with pointer-events: none
    const elements = await page.evaluate(() => {
      const all = document.querySelectorAll('*');
      const results = [];
      for (const el of all) {
        const style = window.getComputedStyle(el);
        if (style.pointerEvents === 'none' || style.pointerEvents === 'auto') {
          results.push({
            tag: el.tagName,
            id: el.id,
            class: el.className?.substring(0, 50),
            pointerEvents: style.pointerEvents,
            position: style.position,
            zIndex: style.zIndex,
            width: el.getBoundingClientRect().width,
            height: el.getBoundingClientRect().height
          });
        }
      }
      return results;
    });
    
    console.log('Pointer events analysis:', JSON.stringify(elements.filter(e => e.pointerEvents === 'none'), null, 2));
  });

  test('use force click if normal click fails', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    
    // Try force click
    try {
      await page.click('input#username', { force: true });
      await page.fill('input#username', 'forceduser');
      console.log('✓ Force click and fill worked');
      await page.screenshot({ path: 'test-results/screenshots/06-force-click.png' });
    } catch (e) {
      console.log('✗ Force click failed:', (e as Error).message);
    }
  });

  test('check if page is fully loaded', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    
    // Check if document is ready
    const readyState = await page.evaluate(() => document.readyState);
    console.log('Document ready state:', readyState);
    
    // Check for any pending requests
    const performanceEntries = await page.evaluate(() => {
      return performance.getEntriesByType('resource').map(r => ({
        name: r.name,
        duration: r.duration,
        initiatorType: (r as PerformanceResourceTiming).initiatorType
      }));
    });
    
    console.log('Resource load times:', performanceEntries.slice(-5));
  });

});