// Volume soak: 500 transactions across 5 nationalities, teller → manager →
// compliance. Local-only browser probe (AGENTS.md §6) — run via
// `npx playwright test tests/100-transactions.spec.ts`.
//
// The run is split into serial phases so a mid-run failure isolates cleanly:
// later phases are skipped instead of failing confusingly. Every phase ends
// in hard assertions — a run that books nothing, or leaves transactions in
// an unexplained state, fails instead of logging past the problem.
//
// Data profile: mostly small auto-completing deals, plus a balanced
// large-transaction mix (the first booking and every 20th pair ≥ RM 10k) so
// the compliance approval phase is genuinely exercised rather than vacuously
// skipping over auto-completed rows.
//
// Created customers/transactions are ADDITIVE (no HTTP deletion exists —
// AML retention); RUN_SEED keeps every run's rows unique.

import { test, expect } from '@playwright/test';
import {
  BASE_URL,
  CURRENCIES,
  MARKET_RATES,
  RUN_SEED,
  ensureCounterSession,
  loginAs,
  logout,
  createCustomer,
  createTransaction,
  approveTransaction,
  readHeaderStatus,
  NATIONALITY_CYCLE,
  getRealName,
  getRealAddress,
  getIdTypeAndNumber,
  getDob,
  phoneFor,
} from './support/helpers';

// Volume is tunable for quick local runs; the default is the soak profile.
const TX_COUNT = Number(process.env.TX_COUNT ?? 500);

// Large-transaction mix: every 20th booking is a large USD deal.
//   i % 20 === 0  → Buy  2200 USD @ 4.72 = RM 10,384 (pays MYR out)
//   i % 20 === 10 → Sell 2300 USD @ 4.81 = RM 11,063 (takes MYR in)
// The pairing keeps MYR/USD teller liquidity roughly net-neutral while
// pushing both sides past the RM 10k auto-approve threshold (and staying
// below the RM 50k manager-confirmation threshold).
const LARGE_BUY_QTY = '2200.00';
const LARGE_SELL_QTY = '2300.00';

test.describe.serial('500 Transaction Flow: Teller → Manager → Compliance', () => {
  // Shared across the serial phases — populated by earlier tests, asserted
  // by later ones. Serial mode skips the remainder when a phase fails.
  const txIds: string[] = [];

  test('phase 1-2: counter opening (teller request → manager approve-and-open)', async ({ page }) => {
    const counter = await ensureCounterSession(page);
    expect(counter.tellerId).toBeGreaterThan(0);
    expect(counter.branchId).toBeGreaterThan(0);
    expect(counter.counterId).toBeGreaterThan(0);
    console.log(`   teller=${counter.tellerId} branch=${counter.branchId} counter=${counter.counterId}`
      + ` (session ${counter.sessionReady ? 'already provisioned' : 'newly opened'})`);
  });

  test('phase 3: teller books transactions', async ({ page }) => {
    // ~21s per booking observed on the soak profile; keep a generous floor.
    test.setTimeout(Math.max(600_000, TX_COUNT * 25_000));
    page.setDefaultNavigationTimeout(30000);
    page.setDefaultTimeout(30000);

    const startTime = Date.now();
    await loginAs(page, 'teller1');

    const purposes = ['Travel', 'Education', 'Medical', 'Business', 'Investment',
      'Family Support', 'Migration', 'Other'];
    const fundSources = ['Salary', 'Savings', 'Business Income', 'Investment Returns',
      'Inheritance', 'Consulting Fees', 'Dividends'];
    const wealthSources = ['Employment', 'Business Ownership', 'Professional Services',
      'Real Estate', 'Financial Investments', 'Government Position', 'Agriculture'];

    // Returning customers (reuse fixed names, run-seeded emails/ICs)
    const returnedCustomers = [
      { name: 'Tun Abdullah bin Ahmad bin Haji Ibrahim', email: `ahmad.${RUN_SEED}@corp.my` },
      { name: 'Datin Sri Dr. Noraini binti Mohamed Ali', email: `noraini.${RUN_SEED}@bank.my` },
      { name: 'Datuk Lee Wei Chen', email: `weichen.${RUN_SEED}@trading.my` },
    ];

    let txCreated = 0;

    for (let i = 1; i <= TX_COUNT; i++) {
      // Total setup failure must not burn the whole loop producing warnings —
      // covers both customer-rejection and booking-failure paths.
      if (i > 10 && txCreated === 0) {
        throw new Error('No transactions booked after 10 attempts — counter session or till floats are not provisioned');
      }

      // Reuse customers at indices 1, 101, 201, 301, 401
      const isReturning = [0, 100, 200, 300, 400].includes(i - 1);
      const custIdx = isReturning ? Math.floor((i - 1) / 100) : 0;
      const cust = isReturning ? returnedCustomers[custIdx] : null;

      if (i % 100 === 0) {
        const nationality = NATIONALITY_CYCLE[(i - 1) % NATIONALITY_CYCLE.length];
        console.log(`[TX ${i}/${TX_COUNT}] ${isReturning ? 'Returning customer' : 'New customer'} — Nationality: ${nationality}`);
      }

      const nationality = NATIONALITY_CYCLE[(i - 1) % NATIONALITY_CYCLE.length];
      const name = cust ? cust.name : getRealName(i, nationality);
      const email = cust ? cust.email : `${RUN_SEED}${i}@test.com`;
      const { idType, idNumber } = getIdTypeAndNumber(nationality, i);
      const address = getRealAddress(nationality, i);
      const dob = getDob(nationality, i);

      const created = await createCustomer(
        page, name, email, idType, idNumber, nationality,
        phoneFor(i), address, dob
      );

      if (!created) {
        if (i % 50 === 0) {
          console.log(`   ⚠ Customer ${i} (${name}) not created`);
        }
        continue;
      }

      const purpose = purposes[i % purposes.length];
      const funds = fundSources[i % fundSources.length];
      const wealth = wealthSources[i % wealthSources.length];

      // 1:1 Buy:Sell keeps MYR/foreign till liquidity roughly self-balancing:
      // each Buy pays MYR out (refilled by the next Sell) and each Sell
      // drains foreign (refilled by the previous Buy). Modest quantities
      // keep the run inside the branch pool's capacity.
      let txType = i % 2 === 0 ? 'Sell' : 'Buy';
      let qty = (200 + i).toFixed(2);
      // Large-transaction mix: the first booking is always large (so even a
      // quick TX_COUNT run exercises the compliance phase), plus a balanced
      // pair every 20 bookings on the soak profile.
      if (i === 1 || i % 20 === 0) {
        txType = 'Buy';
        qty = LARGE_BUY_QTY;
      } else if (i % 20 === 10) {
        txType = 'Sell';
        qty = LARGE_SELL_QTY;
      }

      const currency = CURRENCIES[i % CURRENCIES.length];
      // Large deals are always USD so the large-mix float math above holds.
      const effCurrency = (i % 20 === 0 || i % 20 === 10) ? 'USD' : currency;
      const rate = txType === 'Buy' ? MARKET_RATES[effCurrency].buy : MARKET_RATES[effCurrency].sell;

      const txId = await createTransaction(page, name, txType, effCurrency, purpose, funds, wealth, qty, rate,
        { idType, idNumber, nationality, phone: phoneFor(i), address, dob, email });

      if (txId) {
        txCreated++;
        txIds.push(txId);
      } else if (i % 50 === 0) {
        console.log(`   WARN: TX ${i} (${txType} ${qty} ${effCurrency} @ ${rate}) was not booked`);
      }
    }

    const elapsed = ((Date.now() - startTime) / 1000).toFixed(0);
    console.log(`\n✅ Created ${txCreated} transactions (${txIds.length} IDs captured) in ${elapsed}s\n`);

    await logout(page);

    // A run that booked nothing is a failure, not a pass — the numeric-id
    // capture means txCreated only counts real persisted transactions.
    expect(txCreated).toBeGreaterThan(0);
    expect(txIds.length).toBe(txCreated);
  });

  test('phase 4: compliance resolves every booked transaction', async ({ page }) => {
    test.setTimeout(Math.max(300_000, TX_COUNT * 10_000));
    page.setDefaultNavigationTimeout(30000);
    page.setDefaultTimeout(30000);

    await loginAs(page, 'compliance1');

    // Pending list — informational count (the list is paginated 25/page, so
    // this is a first-page sample, not the total).
    await page.goto(`${BASE_URL}/transactions?status=pending_approval`);
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('table')).toBeVisible();
    const pendingCount = await page.locator('table tbody tr').count();
    console.log(`Found ${pendingCount} pending transactions on the first page`);

    const outcomes: Record<string, number> = {
      'approved': 0,
      'auto-completed': 0,
      'held': 0,
      'no-approve-button': 0,
    };

    for (let i = 0; i < txIds.length; i++) {
      const outcome = await approveTransaction(page, txIds[i]);
      outcomes[outcome]++;
      if ((i + 1) % 100 === 0) {
        console.log(`   Processed ${i + 1}/${txIds.length}`);
      }
      if (i < txIds.length - 1) {
        await page.waitForTimeout(300);
      }
    }

    console.log(`\n✅ Approved ${outcomes.approved}, auto-completed ${outcomes['auto-completed']},`
      + ` held ${outcomes.held}, unexplained ${outcomes['no-approve-button']}\n`);

    await logout(page);

    // Accounting invariant: every booked transaction resolves to exactly one
    // known outcome. 'no-approve-button' means a transaction is neither
    // approvable, final, nor held — a genuine unexplained state.
    expect(outcomes['no-approve-button']).toBe(0);
    expect(outcomes.approved + outcomes['auto-completed'] + outcomes.held).toBe(txIds.length);

    // The compliance phase must be real: the large-transaction mix
    // guarantees deals that need a compliance decision (approval or a
    // hold that requires clearing), not just auto-completed rows.
    expect(outcomes.approved + outcomes.held).toBeGreaterThan(0);
  });

  test('phase 5: verification', async ({ page }) => {
    page.setDefaultNavigationTimeout(30000);
    page.setDefaultTimeout(30000);

    await loginAs(page, 'compliance1');
    await page.goto(`${BASE_URL}/transactions`);
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('table')).toBeVisible();

    const totalTxCount = await page.locator('table tbody tr').count();
    console.log(`Total transactions visible on list page: ${totalTxCount}`);

    // Spot check the first, middle, and last booked transactions — each must
    // sit in an explained terminal-or-pending state.
    const spotChecks = [0, Math.floor(txIds.length / 2), txIds.length - 1];
    for (const idx of spotChecks) {
      if (idx < 0 || idx >= txIds.length) {
        continue;
      }
      await page.goto(`${BASE_URL}/transactions/${txIds[idx]}`);
      await page.waitForLoadState('domcontentloaded');
      const status = await readHeaderStatus(page);
      console.log(`  TX ${txIds[idx]}: ${status}`);
      // Approved deals complete immediately (non-refund path); held deals
      // legitimately remain pending until a compliance officer clears them.
      expect(status, `TX ${txIds[idx]} must be in an explained state`).toMatch(
        /^(Completed|Approved|Pending Approval)$/
      );
    }

    console.log('\n========================================');
    console.log(` DONE! ${txIds.length} booked transactions verified`);
    console.log('========================================');
  });


});
