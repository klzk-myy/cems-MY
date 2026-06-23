import { test, expect } from '@playwright/test';

const BASE_URL = 'http://local.host';

test.describe('Debug Page Interactions', () => {

  test('inspect login page DOM structure', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    
    // Get all divs with position fixed/absolute that might be overlays
    const overlays = await page.evaluate(() => {
      const allElements = document.querySelectorAll('*');
      const overlays = [];
      for (const el of allElements) {
        const style = window.getComputedStyle(el);
        const rect = el.getBoundingClientRect();
        
        // Check if element covers most of viewport and has pointer-events
        if (rect.width > window.innerWidth * 0.8 && 
            rect.height > window.innerHeight * 0.8 &&
            style.position !== 'static' &&
            style.display !== 'none') {
          overlays.push({
            tag: el.tagName,
            id: el.id,
            class: el.className,
            position: style.position,
            zIndex: style.zIndex,
            pointerEvents: style.pointerEvents,
            opacity: style.opacity,
            backgroundColor: style.backgroundColor,
            display: style.display,
            width: rect.width,
            height: rect.height
          });
        }
      }
      return overlays;
    });
    
    console.log('Potential overlays:', JSON.stringify(overlays, null, 2));
  });

  test('check for loading spinners or modals', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    
    // Common loading indicators
    const loadingElements = await page.evaluate(() => {
      const selectors = [
        '.loading', '.loader', '.spinner', '.overlay',
        '[class*="loading"]', '[class*="loader"]', '[class*="spinner"]',
        '[class*="overlay"]', '[class*="backdrop"]',
        '.modal', '.dialog', '.popup'
      ];
      
      const results = [];
      for (const selector of selectors) {
        try {
          const elements = document.querySelectorAll(selector);
          elements.forEach((el, i) => {
            const style = window.getComputedStyle(el);
            results.push({
              selector: `${selector}[${i}]`,
              tag: el.tagName,
              class: el.className,
              visible: style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0',
              zIndex: style.zIndex,
              pointerEvents: style.pointerEvents
            });
          });
        } catch (e) {}
      }
      return results;
    });
    
    console.log('Loading elements:', JSON.stringify(loadingElements, null, 2));
  });

  test('try to find and click login form elements', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(3000);
    
    // Get screenshot
    await page.screenshot({ path: 'test-results/screenshots/login-debug.png' });
    
    // List all interactive elements
    const elements = await page.evaluate(() => {
      const interactive = document.querySelectorAll('a, button, input, select, textarea, [onclick], [role="button"]');
      return Array.from(interactive).map(el => ({
        tag: el.tagName,
        type: (el as HTMLInputElement).type,
        id: el.id,
        class: el.className,
        text: el.textContent?.trim().substring(0, 50),
        visible: window.getComputedStyle(el).display !== 'none'
      }));
    });
    
    console.log('Interactive elements:', JSON.stringify(elements, null, 2));
  });

  test('check JavaScript errors on page', async ({ page }) => {
    const errors: string[] = [];
    
    page.on('pageerror', (error) => {
      errors.push(error.message);
      console.log('PAGE ERROR:', error.message);
    });
    
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        console.log('CONSOLE ERROR:', msg.text());
      }
    });
    
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(5000);
    
    console.log(`Total errors: ${errors.length}`);
  });

  test('test click on body and check event propagation', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    
    // Add click listener to check if clicks are being captured
    await page.evaluate(() => {
      window.clickedElement = null;
      document.addEventListener('click', (e) => {
        window.clickedElement = {
          tag: e.target.tagName,
          id: e.target.id,
          class: e.target.className
        };
      }, true);
    });
    
    // Try clicking at center of page
    await page.click('body', { position: { x: 500, y: 300 } });
    await page.waitForTimeout(500);
    
    const clicked = await page.evaluate(() => window.clickedElement);
    console.log('Clicked element:', clicked);
  });

});