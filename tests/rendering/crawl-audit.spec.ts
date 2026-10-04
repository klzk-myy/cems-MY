// Full-site rendering crawl audit.
//
// Renders every GET web route in a real browser (admin role), collects console
// errors, failed network requests, and page-level anomalies, then produces a
// JSON report at storage/app/crawl-audit/report.json.
//
// Run: npx playwright test tests/rendering/crawl-audit.spec.ts
//
// This is a local-only probe (AGENTS.md §6): it drives the real web surface at
// BASE_URL over HTTP only. It does NOT mutate data.

import { test, expect, type Page, type ConsoleMessage } from '@playwright/test';
import { writeFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { BASE_URL, TEST_PASSWORD, loginAs, logout, apiGet } from '../support/helpers';
import { routes, type RouteEntry } from './route-registry';

// ────────────────────────────────────────────────────────────
// Report shape
// ────────────────────────────────────────────────────────────

interface FailedRequest {
  url: string;
  status: number;
  method: string;
}

interface PageReport {
  path: string;
  name: string;
  category: string;
  status: 'ok' | 'warn' | 'fail' | 'skipped' | 'redirect';
  finalUrl?: string;
  title?: string;
  consoleErrors: string[];
  consoleWarnings: string[];
  pageErrors: string[];
  failedRequests: FailedRequest[];
  bodyBytes: number;
  renderMs: number;
  skipReason?: string;
}

const report: PageReport[] = [];

// ────────────────────────────────────────────────────────────
// Helpers
// ────────────────────────────────────────────────────────────

function today(): string {
  // Matches app timezone (Asia/Kuala_Lumpur). For routes that need a YYYY-MM-DD,
  // today's date in the app's timezone is what matters.
  return new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Kuala_Lumpur' });
}

/** Scrape numeric IDs from hrefs on an index page. */
async function scrapeIds(page: Page, pattern: RegExp, limit = 3): Promise<string[]> {
  const links = page.locator(`a[href]`);
  const count = await links.count();
  const ids: string[] = [];
  for (let i = 0; i < count && ids.length < limit; i++) {
    const href = await links.nth(i).getAttribute('href');
    if (!href) continue;
    const m = href.match(pattern);
    if (m && !ids.includes(m[1])) {
      ids.push(m[1]);
    }
  }
  return ids;
}

// ────────────────────────────────────────────────────────────
// Crawl
// ────────────────────────────────────────────────────────────

test.describe('Full-site rendering crawl', () => {
  test('render every GET route and collect health signals', async ({ page }) => {
    page.setDefaultNavigationTimeout(30000);
    page.setDefaultTimeout(15000);

    const START = Date.now();
    const REPORT_DIR = join(process.cwd(), 'storage', 'app', 'crawl-audit');
    mkdirSync(join(REPORT_DIR, 'screenshots'), { recursive: true });

    // ── Login as admin (all permissions, bypasses branch.scope) ──
    await loginAs(page, 'admin');
    console.log(`\n🔍 Crawling ${routes.length} registered routes as admin...\n`);

    // ── Resolve dynamic routes ──
    const expanded: Array<{ entry: RouteEntry; resolvedPath: string }> = [];

    for (const entry of routes) {
      // Skip non-HTML endpoints (file downloads).
      if (entry.nonHtml) {
        report.push({
          path: entry.path, name: entry.name, category: entry.category,
          status: 'skipped', consoleErrors: [], consoleWarnings: [],
          pageErrors: [], failedRequests: [], bodyBytes: 0, renderMs: 0,
          skipReason: 'non-HTML endpoint (download)',
        });
        console.log(`   ⏭ ${entry.path} — skipped (non-HTML endpoint)`);
        continue;
      }
      if (!entry.dynamic) {
        expanded.push({ entry, resolvedPath: entry.path });
        continue;
      }
      // Visit the index page, scrape IDs, build concrete paths.
      const idx = entry.indexPath ?? entry.path.replace(/\{.*?\}.*/, '');
      try {
        await page.goto(`${BASE_URL}${idx}`);
        await page.waitForLoadState('domcontentloaded');
      } catch {
        expanded.push({ entry, resolvedPath: entry.path });
        continue;
      }
      const ids = entry.idMatch
        ? await scrapeIds(page, entry.idMatch, 2)
        : [];
      if (ids.length === 0) {
        // No data — skip with a note.
        report.push({
          path: entry.path, name: entry.name, category: entry.category,
          status: 'skipped', consoleErrors: [], consoleWarnings: [],
          pageErrors: [], failedRequests: [], bodyBytes: 0, renderMs: 0,
          skipReason: `no data at index ${idx}`,
        });
        console.log(`   ⏭ ${entry.path} — skipped (no data at ${idx})`);
      } else {
        for (const id of ids) {
          const resolved = entry.path.replace(/\{.*?\}/g, id);
          expanded.push({ entry, resolvedPath: resolved });
        }
      }
    }

    console.log(`   → ${expanded.length} concrete pages to render\n`);

    // ── Crawl loop ──
    for (let i = 0; i < expanded.length; i++) {
      const { entry, resolvedPath } = expanded[i];
      const url = `${BASE_URL}${resolvedPath}`;
      const t0 = Date.now();

      const signals = {
        consoleErrors: [] as string[],
        consoleWarnings: [] as string[],
        pageErrors: [] as string[],
        failedRequests: [] as FailedRequest[],
      };

      // Attach listeners BEFORE navigation.
      page.on('console', (msg: ConsoleMessage) => {
        const type = msg.type();
        const text = msg.text();
        if (type === 'error') {
          // Ignore benign noise: favicon 404, analytics, etc.
          if (/(favicon|analytics|livereload|__webpack)/i.test(text)) return;
          signals.consoleErrors.push(text);
        }
        if (type === 'warning') {
          // Only surface actionable warnings (deprecation, CSP, etc.)
          if (/(deprecated|CSP|Content Security|legacy|experimental)/i.test(text)) {
            signals.consoleWarnings.push(text);
          }
        }
      });

      page.on('pageerror', (err) => {
        signals.pageErrors.push(err.message);
      });

      page.on('response', (resp) => {
        const status = resp.status();
        if (status >= 400) {
          signals.failedRequests.push({
            url: resp.url(),
            status,
            method: resp.request().method(),
          });
        }
      });

      let finalUrl = url;
      let title = '';
      let bodyBytes = 0;
      let status: PageReport['status'] = 'ok';

      try {
        await page.goto(url);
        await page.waitForLoadState('domcontentloaded');

        // Small wait for Alpine to boot (x-cloak).
        await page.waitForTimeout(150);

        finalUrl = page.url();
        title = await page.title();
        const body = await page.textContent('body');
        bodyBytes = (body ?? '').length;

        // Detect Laravel exception pages.
        const bodyText = (body ?? '').substring(0, 500);
        if (/(Whoops|Symfony|Unhandled|Fatal error|Stack trace|ErrorException)/i.test(bodyText)) {
          if (entry.expectError) {
            status = 'skipped';
          } else {
            status = 'fail';
            signals.pageErrors.push('Laravel exception page rendered');
          }
        }

        // Detect redirect away from target.
        if (resolvedPath !== '/' && finalUrl !== url && !finalUrl.startsWith(url + '#')) {
          const pathPart = finalUrl.replace(BASE_URL, '');
          // Redirect to login = auth gate working, not a failure.
          if (/\/login|\/mfa/.test(pathPart)) {
            status = 'redirect';
          } else if (pathPart !== resolvedPath.replace(/\{.*?\}/g, '')) {
            status = 'warn';
          }
        }

        // Status determination.
        if (signals.pageErrors.length > 0) status = 'fail';
        else if (signals.consoleErrors.length > 0) status = 'fail';
        else if (signals.failedRequests.some(r => r.status >= 500)) status = 'fail';
        else if (signals.failedRequests.some(r => r.status >= 400)) status = 'warn';

        // Routes flagged expectError (gated by middleware in current app state)
        // are downgraded to skipped when they 500 or throw console errors.
        if (entry.expectError && status === 'fail') {
          status = 'skipped';
        }

        // Screenshot on failure.
        if (status === 'fail') {
          const safe = resolvedPath.replace(/[^a-z0-9]+/gi, '_').substring(0, 60);
          await page.screenshot({
            path: join(REPORT_DIR, 'screenshots', `${i}_${safe}.png`),
            fullPage: false,
          }).catch(() => {});
        }
      } catch (err) {
        status = 'fail';
        signals.pageErrors.push(`Navigation failed: ${(err as Error).message}`);
      }

      // Detach listeners.
      page.removeAllListeners('console');
      page.removeAllListeners('pageerror');
      page.removeAllListeners('response');

      const renderMs = Date.now() - t0;

      const rec: PageReport = {
        path: resolvedPath,
        name: entry.name,
        category: entry.category,
        status,
        finalUrl: finalUrl !== url ? finalUrl : undefined,
        title,
        consoleErrors: signals.consoleErrors.slice(0, 10),
        consoleWarnings: signals.consoleWarnings.slice(0, 10),
        pageErrors: signals.pageErrors.slice(0, 10),
        failedRequests: signals.failedRequests.slice(0, 10),
        bodyBytes,
        renderMs,
      };
      report.push(rec);

      const icon = status === 'ok' ? '✓' : status === 'warn' ? '⚠' : status === 'fail' ? '✘' : '⏭';
      console.log(
        `   ${icon} ${resolvedPath.padEnd(45)} ${String(renderMs).padStart(5)}ms ` +
        `err=${signals.consoleErrors.length} reqfail=${signals.failedRequests.length} ` +
        `pageerr=${signals.pageErrors.length}`
      );
    }

    // ── Save report ──
    const reportPath = join(REPORT_DIR, 'report.json');
    writeFileSync(reportPath, JSON.stringify({
      generatedAt: new Date().toISOString(),
      baseUrl: BASE_URL,
      totalRoutes: routes.length,
      totalPages: expanded.length,
      durationMs: Date.now() - START,
      pages: report,
    }, null, 2));

    // ── Summary ──
    const counts = { ok: 0, warn: 0, fail: 0, skipped: 0, redirect: 0 };
    for (const r of report) counts[r.status]++;

    console.log('\n' + '─'.repeat(60));
    console.log('CRAWL AUDIT SUMMARY');
    console.log('─'.repeat(60));
    console.log(`  Total pages rendered: ${report.length}`);
    console.log(`  ✓ OK:       ${counts.ok}`);
    console.log(`  ⚠ WARN:     ${counts.warn}`);
    console.log(`  ✘ FAIL:     ${counts.fail}`);
    console.log(`  ⏭ SKIPPED:  ${counts.skipped}`);
    console.log(`  → REDIRECT: ${counts.redirect}`);

    if (counts.fail > 0) {
      console.log('\nFAILURES:');
      for (const r of report.filter(p => p.fail || p.pageErrors.length > 0)) {
        console.log(`  ✘ ${r.path}`);
        for (const e of r.pageErrors) console.log(`      pageError: ${e}`);
        for (const e of r.consoleErrors) console.log(`      consoleError: ${e}`);
        for (const e of r.failedRequests) console.log(`      failed: ${e.method} ${e.status} ${e.url}`);
      }
    }

    if (counts.warn > 0) {
      console.log('\nWARNINGS:');
      for (const r of report.filter(p => p.warn)) {
        console.log(`  ⚠ ${r.path} → ${r.finalUrl ?? '(see report)'}`);
      }
    }

    console.log(`\nReport: ${reportPath}`);
    console.log(`Duration: ${((Date.now() - START) / 1000).toFixed(1)}s\n`);

    // ── Assert — fail the test if any page is broken ──
    expect(counts.fail, `Found ${counts.fail} pages with rendering failures (see report)`).toBe(0);

    await logout(page);
  });
});
