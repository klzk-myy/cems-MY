import { test, expect } from '@playwright/test';

const BASE_URL = 'http://local.host';
const PAGES = [
  '/',
  '/login',
  '/dashboard',
  '/accounting',
  '/compliance/risk-dashboard'
];

test.describe('Check All Pages for Loading Overlays', () => {

  for (const path of PAGES) {
    test(`check ${path} for overlays`, async ({ page }) => {
      await page.goto(`${BASE_URL}${path}`, { waitUntil: 'networkidle' });
      await page.waitForTimeout(3000);
      
      // Look for any element that covers the full viewport
      const overlays = await page.evaluate(() => {
        const allElements = document.querySelectorAll('*');
        const results = [];
        
        for (const el of allElements) {
          const style = window.getComputedStyle(el);
          const rect = el.getBoundingClientRect();
          
          // Check for full-screen overlay characteristics
          if ((style.position === 'fixed' || style.position === 'absolute') &&
              rect.width >= window.innerWidth * 0.9 &&
              rect.height >= window.innerHeight * 0.9 &&
              style.display !== 'none' &&
              el.tagName !== 'BODY' &&
              el.tagName !== 'HTML') {
            
            results.push({
              tag: el.tagName,
              id: el.id,
              class: el.className?.substring(0, 100),
              position: style.position,
              zIndex: style.zIndex,
              opacity: style.opacity,
              backgroundColor: style.backgroundColor,
              pointerEvents: style.pointerEvents,
              innerHTML: el.innerHTML?.substring(0, 200)
            });
          }
        }
        return results;
      });
      
      console.log(`\n=== ${path} ===`);
      if (overlays.length > 0) {
        console.log('OVERLAYS FOUND:', JSON.stringify(overlays, null, 2));
      } else {
        console.log('No overlays found');
      }
      
      // Take screenshot
      await page.screenshot({ path: `test-results/screenshots/page-${path.replace(/\//g, '-')}.png` });
    });
  }

});