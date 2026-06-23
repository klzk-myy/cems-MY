import { test, expect } from '@playwright/test';

const BASE_URL = 'http://local.host';

test.describe('Element Clickability Test', () => {

  test('check all elements visibility and clickability on login', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    
    // Get comprehensive element info
    const elements = await page.evaluate(() => {
      const allElements = document.querySelectorAll('*');
      const results = [];
      
      for (const el of allElements) {
        const style = window.getComputedStyle(el);
        const rect = el.getBoundingClientRect();
        
        // Only check visible elements in viewport
        if (rect.width > 0 && rect.height > 0 && 
            rect.top >= 0 && rect.top <= window.innerHeight &&
            rect.left >= 0 && rect.left <= window.innerWidth &&
            style.display !== 'none' && 
            style.visibility !== 'hidden' &&
            style.opacity !== '0') {
          
          results.push({
            tag: el.tagName,
            id: el.id,
            class: el.className?.substring(0, 50),
            x: rect.x,
            y: rect.y,
            width: rect.width,
            height: rect.height,
            pointerEvents: style.pointerEvents,
            opacity: style.opacity,
            visibility: style.visibility,
            zIndex: style.zIndex,
            cursor: style.cursor
          });
        }
      }
      return results;
    });
    
    console.log('Visible elements:', JSON.stringify(elements.slice(0, 20), null, 2));
  });

  test('try clicking at specific coordinates', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    
    // Click at the center of the page
    const viewport = page.viewportSize();
    if (viewport) {
      await page.mouse.click(viewport.width / 2, viewport.height / 2);
      console.log(`Clicked at center: ${viewport.width / 2}, ${viewport.height / 2}`);
    }
    
    // Click on the username field specifically
    const usernameBox = await page.locator('input#username').boundingBox();
    if (usernameBox) {
      await page.mouse.click(
        usernameBox.x + usernameBox.width / 2,
        usernameBox.y + usernameBox.height / 2
      );
      console.log('Clicked on username field');
    }
  });

  test('check for invisible blocking elements', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    
    // Find elements that might be invisible but blocking
    const blockers = await page.evaluate(() => {
      const allElements = document.querySelectorAll('*');
      const results = [];
      
      for (const el of allElements) {
        const style = window.getComputedStyle(el);
        const rect = el.getBoundingClientRect();
        
        // Check for elements with 0 opacity but taking up space
        if (rect.width > 0 && rect.height > 0 &&
            style.opacity === '0' &&
            style.display !== 'none') {
          results.push({
            tag: el.tagName,
            class: el.className?.substring(0, 50),
            width: rect.width,
            height: rect.height
          });
        }
        
        // Check for elements with visibility hidden
        if (rect.width > 0 && rect.height > 0 &&
            style.visibility === 'hidden') {
          results.push({
            tag: el.tagName,
            class: el.className?.substring(0, 50),
            visibility: style.visibility
          });
        }
      }
      return results;
    });
    
    console.log('Invisible blockers:', JSON.stringify(blockers, null, 2));
  });

});