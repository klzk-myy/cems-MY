import { test, expect } from '@playwright/test';

const BASE_URL = 'http://local.host';

test.use({
  browserName: 'chromium',
  viewport: { width: 1920, height: 1080 },
  screenshot: 'on',
  video: 'on',
});

test.describe('Chrome Browser Test - Interactive Elements', () => {

  test('homepage loads and take screenshot', async ({ page }) => {
    await page.goto(BASE_URL, { waitUntil: 'networkidle' });
    await page.screenshot({ path: 'test-results/screenshots/homepage.png', fullPage: true });
    
    await expect(page.locator('body')).toBeVisible();
    console.log('Page title:', await page.title());
    console.log('Page URL:', page.url());
  });

  test('check for loading overlays', async ({ page }) => {
    await page.goto(BASE_URL, { waitUntil: 'networkidle' });
    
    // Common loading overlay selectors
    const loadingSelectors = [
      '.loading', '.loader', '.spinner', '.overlay', 
      '[class*="loading"]', '[class*="loader"]', '[class*="spinner"]',
      '#loading', '#loader', '.modal-backdrop', '.backdrop'
    ];
    
    for (const selector of loadingSelectors) {
      const elements = page.locator(selector);
      const count = await elements.count();
      if (count > 0) {
        for (let i = 0; i < count; i++) {
          const el = elements.nth(i);
          const isVisible = await el.isVisible().catch(() => false);
          const className = await el.getAttribute('class').catch(() => 'no-class');
          console.log(`Found loading element: ${selector}[${i}] class="${className}" visible=${isVisible}`);
        }
      }
    }
  });

  test('check all clickable elements', async ({ page }) => {
    await page.goto(BASE_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(3000); // Wait for any animations
    
    // Find all links
    const links = page.locator('a[href]');
    const linkCount = await links.count();
    console.log(`Found ${linkCount} links`);
    
    for (let i = 0; i < Math.min(linkCount, 10); i++) {
      const link = links.nth(i);
      const href = await link.getAttribute('href');
      const text = await link.textContent();
      const isVisible = await link.isVisible().catch(() => false);
      const isEnabled = await link.isEnabled().catch(() => false);
      console.log(`Link ${i}: text="${text?.trim()}" href="${href}" visible=${isVisible} enabled=${isEnabled}`);
    }
  });

  test('check for z-index issues', async ({ page }) => {
    await page.goto(BASE_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    
    // Check if there's an overlay with high z-index
    const overlays = await page.evaluate(() => {
      const allElements = document.querySelectorAll('*');
      const results = [];
      for (const el of allElements) {
        const style = window.getComputedStyle(el);
        const zIndex = parseInt(style.zIndex);
        if (!isNaN(zIndex) && zIndex > 100 && style.position !== 'static') {
          results.push({
            tag: el.tagName,
            class: el.className,
            id: el.id,
            zIndex: zIndex,
            position: style.position,
            width: style.width,
            height: style.height,
            display: style.display,
            visibility: style.visibility,
            pointerEvents: style.pointerEvents
          });
        }
      }
      return results;
    });
    
    console.log('Elements with z-index > 100:', overlays);
  });

  test('try clicking first visible link', async ({ page }) => {
    await page.goto(BASE_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(3000);
    
    // Try to find and click the first visible link
    const links = page.locator('a[href]');
    const count = await links.count();
    
    for (let i = 0; i < count; i++) {
      const link = links.nth(i);
      const isVisible = await link.isVisible().catch(() => false);
      
      if (isVisible) {
        const href = await link.getAttribute('href');
        const text = await link.textContent();
        console.log(`Trying to click: "${text?.trim()}" -> ${href}`);
        
        try {
          await link.click({ timeout: 5000 });
          await page.waitForTimeout(1000);
          console.log('Click succeeded!');
          
          await page.screenshot({ path: 'test-results/screenshots/after-click.png', fullPage: true });
          break;
        } catch (e) {
          console.log('Click failed:', (e as Error).message);
        }
      }
    }
  });

  test('check body and html styles', async ({ page }) => {
    await page.goto(BASE_URL, { waitUntil: 'networkidle' });
    
    const styles = await page.evaluate(() => {
      const bodyStyle = window.getComputedStyle(document.body);
      const htmlStyle = window.getComputedStyle(document.documentElement);
      
      return {
        body: {
          overflow: bodyStyle.overflow,
          overflowX: bodyStyle.overflowX,
          overflowY: bodyStyle.overflowY,
          position: bodyStyle.position,
          pointerEvents: bodyStyle.pointerEvents
        },
        html: {
          overflow: htmlStyle.overflow,
          overflowX: htmlStyle.overflowX,
          overflowY: htmlStyle.overflowY
        }
      };
    });
    
    console.log('Body styles:', styles.body);
    console.log('HTML styles:', styles.html);
  });

  test('check for JavaScript errors', async ({ page }) => {
    const errors: string[] = [];
    
    page.on('pageerror', (error) => {
      errors.push(error.message);
      console.log('Page error:', error.message);
    });
    
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        console.log('Console error:', msg.text());
      }
    });
    
    await page.goto(BASE_URL, { waitUntil: 'networkidle' });
    await page.waitForTimeout(5000);
    
    console.log(`Total JS errors: ${errors.length}`);
  });

});