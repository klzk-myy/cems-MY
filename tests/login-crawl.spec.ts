import { test, expect } from '@playwright/test';

const BASE_URL = 'http://local.host';

async function removeOverlay(page) {
  console.log('Checking for overlay...');
  
  // Check for the specific overlay
  const overlayCount = await page.locator('.fixed.inset-0').count();
  console.log(`Found ${overlayCount} potential overlays`);
  
  if (overlayCount > 0) {
    // Remove the overlay via JavaScript
    await page.evaluate(() => {
      const overlays = document.querySelectorAll('.fixed.inset-0, [class*="overlay"], [class*="modal-backdrop"]');
      overlays.forEach(el => {
        if (el.tagName !== 'BODY' && el.tagName !== 'HTML') {
          (el as HTMLElement).style.display = 'none';
          console.log('Removed overlay:', el.className);
        }
      });
    });
    await page.waitForTimeout(500);
  }
  
  // Also check and remove any high z-index elements
  await page.evaluate(() => {
    const allElements = document.querySelectorAll('*');
    allElements.forEach(el => {
      const style = window.getComputedStyle(el);
      if ((style.position === 'fixed' || style.position === 'absolute') &&
          parseInt(style.zIndex) > 40 &&
          el.tagName !== 'BODY' && el.tagName !== 'HTML') {
        (el as HTMLElement).style.display = 'none';
        console.log('Removed high z-index element:', el.tagName, el.className, 'z-index:', style.zIndex);
      }
    });
  });
}

test.describe('Login and Crawl - Remove Overlay First', () => {

  test('login and remove overlay', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    
    await page.fill('input#username', 'admin');
    await page.fill('input#password', 'Admin@123456');
    await page.click('button[type="submit"]');
    
    await page.waitForTimeout(3000);
    console.log('Logged in, URL:', page.url());
    
    // Remove overlay
    await removeOverlay(page);
    
    await page.screenshot({ path: 'test-results/screenshots/01-after-login-no-overlay.png', fullPage: true });
    
    // Now try clicking
    const links = await page.locator('a[href]').all();
    console.log(`Found ${links.length} links after removing overlay`);
    
    // Try first visible link
    for (const link of links.slice(0, 5)) {
      const isVisible = await link.isVisible().catch(() => false);
      const text = await link.textContent();
      const href = await link.getAttribute('href');
      
      if (isVisible && href && !href.startsWith('#')) {
        console.log(`Attempting to click: "${text?.trim()}" -> ${href}`);
        try {
          await link.click({ timeout: 5000 });
          console.log('✓ Click successful!');
          await page.waitForTimeout(2000);
          await page.screenshot({ path: 'test-results/screenshots/02-click-success.png', fullPage: true });
          break;
        } catch (e) {
          console.log('✗ Click failed:', (e as Error).message);
        }
      }
    }
  });

  test('crawl multiple pages', async ({ page }) => {
    // Login
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.fill('input#username', 'admin');
    await page.fill('input#password', 'Admin@123456');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(3000);
    
    // Pages to test
    const pages = [
      '/dashboard',
      '/transactions',
      '/customers',
      '/accounting',
      '/compliance/risk-dashboard'
    ];
    
    for (const path of pages) {
      console.log(`\n--- Testing ${path} ---`);
      await page.goto(`${BASE_URL}${path}`, { waitUntil: 'networkidle' });
      await page.waitForTimeout(2000);
      
      // Remove any overlay
      await removeOverlay(page);
      
      console.log('URL:', page.url());
      
      // Try to click first link
      const links = await page.locator('a[href]').all();
      let clicked = false;
      
      for (const link of links.slice(0, 3)) {
        const isVisible = await link.isVisible().catch(() => false);
        if (isVisible) {
          const text = await link.textContent();
          try {
            await link.click({ timeout: 3000 });
            console.log(`✓ Clicked: "${text?.trim()}"`);
            clicked = true;
            await page.waitForTimeout(1000);
            break;
          } catch (e) {
            console.log(`✗ Failed: "${text?.trim()}"`);
          }
        }
      }
      
      await page.screenshot({ path: `test-results/screenshots/page-${path.replace(/\//g, '-')}.png`, fullPage: true });
    }
  });

  test('interactive elements test', async ({ page }) => {
    // Login
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.fill('input#username', 'admin');
    await page.fill('input#password', 'Admin@123456');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(3000);
    
    await removeOverlay(page);
    
    // Test inputs
    const inputs = await page.locator('input[type="text"], input[type="search"]').all();
    console.log(`Found ${inputs.length} text inputs`);
    
    for (let i = 0; i < Math.min(inputs.length, 3); i++) {
      const input = inputs[i];
      const isVisible = await input.isVisible().catch(() => false);
      if (isVisible) {
        try {
          await input.fill(`test${i}`);
          console.log(`✓ Filled input ${i}`);
        } catch (e) {
          console.log(`✗ Failed to fill input ${i}`);
        }
      }
    }
    
    // Test buttons
    const buttons = await page.locator('button').all();
    console.log(`Found ${buttons.length} buttons`);
    
    for (let i = 0; i < Math.min(buttons.length, 3); i++) {
      const btn = buttons[i];
      const text = await btn.textContent();
      const isVisible = await btn.isVisible().catch(() => false);
      
      if (isVisible) {
        try {
          await btn.click({ timeout: 3000 });
          console.log(`✓ Clicked button: "${text?.trim()}"`);
        } catch (e) {
          console.log(`✗ Failed button: "${text?.trim()}"`);
        }
      }
    }
    
    await page.screenshot({ path: 'test-results/screenshots/interactive-test.png', fullPage: true });
  });

});