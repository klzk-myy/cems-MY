import { test, expect } from '@playwright/test';

const BASE_URL = 'http://local.host';

test.describe('Remove Loading Overlay', () => {

  test('detect and remove loading overlay', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(3000);
    
    // Find all elements that could be overlays
    const overlays = await page.evaluate(() => {
      const allElements = document.querySelectorAll('*');
      const results = [];
      for (const el of allElements) {
        const style = window.getComputedStyle(el);
        const rect = el.getBoundingClientRect();
        
        // Check for overlay characteristics
        if ((style.position === 'fixed' || style.position === 'absolute') &&
            rect.width >= window.innerWidth * 0.9 &&
            rect.height >= window.innerHeight * 0.9 &&
            style.display !== 'none' &&
            style.visibility !== 'hidden') {
          
          results.push({
            tag: el.tagName,
            id: el.id,
            class: el.className,
            position: style.position,
            zIndex: style.zIndex,
            opacity: style.opacity,
            backgroundColor: style.backgroundColor,
            pointerEvents: style.pointerEvents,
            width: rect.width,
            height: rect.height,
            html: el.outerHTML.substring(0, 200)
          });
        }
      }
      return results;
    });
    
    console.log('Overlays found:', JSON.stringify(overlays, null, 2));
    
    // Remove any overlay found
    if (overlays.length > 0) {
      await page.evaluate(() => {
        const allElements = document.querySelectorAll('*');
        for (const el of allElements) {
          const style = window.getComputedStyle(el);
          const rect = el.getBoundingClientRect();
          
          if ((style.position === 'fixed' || style.position === 'absolute') &&
              rect.width >= window.innerWidth * 0.9 &&
              rect.height >= window.innerHeight * 0.9 &&
              style.display !== 'none') {
            (el as HTMLElement).style.display = 'none';
            console.log('Removed overlay:', el.tagName, el.className);
          }
        }
      });
    }
    
    // Take screenshot after removal
    await page.screenshot({ path: 'test-results/screenshots/after-overlay-removal.png' });
  });

  test('force remove all fixed/absolute positioned full-screen elements', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    
    // Remove any full-screen overlay
    await page.evaluate(() => {
      const removeOverlays = () => {
        const elements = document.querySelectorAll('*');
        let removed = 0;
        
        elements.forEach(el => {
          const style = window.getComputedStyle(el);
          const rect = el.getBoundingClientRect();
          
          // Check if it's a full-screen overlay
          if ((style.position === 'fixed' || style.position === 'absolute') &&
              rect.width >= window.innerWidth * 0.8 &&
              rect.height >= window.innerHeight * 0.8) {
            
            // Don't remove the body or html
            if (el.tagName !== 'BODY' && el.tagName !== 'HTML') {
              (el as HTMLElement).style.display = 'none';
              removed++;
              console.log('Removed:', el.tagName, el.className, el.id);
            }
          }
        });
        
        return removed;
      };
      
      return removeOverlays();
    });
    
    // Now try clicking
    await page.click('input#username');
    await page.fill('input#username', 'test@example.com');
    
    await page.screenshot({ path: 'test-results/screenshots/after-click-test.png' });
  });

  test('check for CSS animations causing overlay', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(3000);
    
    // Check for elements with opacity transition
    const animatedElements = await page.evaluate(() => {
      const elements = document.querySelectorAll('*');
      const results = [];
      
      elements.forEach(el => {
        const style = window.getComputedStyle(el);
        if (style.transition && style.transition.includes('opacity')) {
          results.push({
            tag: el.tagName,
            class: el.className,
            transition: style.transition,
            opacity: style.opacity,
            display: style.display
          });
        }
      });
      
      return results;
    });
    
    console.log('Animated elements:', JSON.stringify(animatedElements, null, 2));
  });

  test('disable pointer-events blocker', async ({ page }) => {
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await page.waitForTimeout(2000);
    
    // Find and enable pointer events
    await page.evaluate(() => {
      const elements = document.querySelectorAll('*');
      elements.forEach(el => {
        const style = window.getComputedStyle(el);
        if (style.pointerEvents === 'none' && el.tagName !== 'BODY' && el.tagName !== 'HTML') {
          (el as HTMLElement).style.pointerEvents = 'auto';
          console.log('Enabled pointer events for:', el.tagName, el.className);
        }
      });
    });
    
    // Try to interact
    await page.click('input#username');
    await page.fill('input#username', 'testuser');
    await page.click('input#password');
    await page.fill('input#password', 'testpassword');
    
    await page.screenshot({ path: 'test-results/screenshots/form-filled.png' });
  });

});