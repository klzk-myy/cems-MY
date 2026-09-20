import { test, expect } from '@playwright/test';

const BASE_URL = 'http://local.host';

test.describe('Bulk Transaction Creation: 200 Transactions', () => {

  async function logout(page: any) {
    await page.evaluate(() => {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = '/logout';
      const csrf = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
      const csrfInput = document.createElement('input');
      csrfInput.type = 'hidden';
      csrfInput.name = '_token';
      csrfInput.value = csrf?.content || '';
      form.appendChild(csrfInput);
      document.body.appendChild(form);
      form.submit();
    });
    await page.waitForLoadState('networkidle');
    await page.waitForURL(/login/, { timeout: 10000 });
  }

  async function loginAsTeller(page: any) {
    await page.goto(BASE_URL + '/login');
    await expect(page.locator('form[action*="login"]')).toBeVisible({ timeout: 10000 });
    await page.fill('input[name="username"]', 'teller1');
    await page.fill('input[name="password"]', 'Password123!');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForSelector('aside', { timeout: 5000 });
  }

  async function createCustomer(page: any, name: string, email: string, ic: string, phone: string, address: string, dob: string) {
    await page.goto(BASE_URL + '/customers/create');
    await page.waitForLoadState('networkidle');
    await page.waitForSelector('form[action*="customers"]', { timeout: 5000 });
    
    const form = page.locator('form[action*="customers"]');
    await form.locator('input[name="full_name"]').fill(name);
    await form.locator('input[name="email"]').fill(email);
    await form.locator('select[name="id_type"]').selectOption('MyKad');
    await form.locator('input[name="id_number"]').fill(ic);
    await form.locator('select[name="nationality"]').selectOption('MY');
    await form.locator('input[name="phone"]').fill(phone);
    await form.locator('textarea[name="address"]').fill(address);
    await form.locator('input[name="date_of_birth"]').fill(dob);
    await form.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');
  }

  async function createTransaction(page: any, name: string, txType: string, currency: string, purpose: string, funds: string, wealth: string, qty: string, rate: string) {
    await page.goto(BASE_URL + '/transactions/create');
    await page.waitForLoadState('networkidle');
    await page.waitForSelector('form[action*="transactions"]', { timeout: 10000 });

    const form = page.locator('form[action*="transactions"]');
    
    await form.locator('select[name="type"]').selectOption(txType);
    
    const currencySelect = form.locator('select[name="currency_code"]');
    await currencySelect.click();
    await page.waitForTimeout(300);
    await currencySelect.selectOption({ value: currency });
    
    await form.locator('input[name="quantity"]').fill(qty);
    await form.locator('input[name="rate"]').fill(rate);
    await form.locator('select[name="purpose"]').selectOption(purpose);
    await form.locator('input[name="source_of_funds"]').fill(funds);
    await form.locator('input[name="source_of_wealth"]').fill(wealth);

    const ci = form.locator('input[placeholder*="customer"], input[placeholder*="Type customer"]').first();
    if (await ci.count() > 0) {
      await ci.click();
      await page.waitForTimeout(800);
      await ci.fill(name.split(' ').slice(0, 3).join(' '));
      await page.waitForTimeout(1500);
      const res = form.locator('li[role="option"]');
      if (await res.count() > 0) await res.first().click();
    }

    const cs = form.locator('select[name="counter_id"]');
    if (await cs.count() > 0) {
      await cs.waitFor({ timeout: 3000 });
      await cs.selectOption(/1/);
    }

    await form.locator('button[type="submit"]').click();
    await page.waitForLoadState('networkidle');
  }

  test('create 200 transactions with 1% returning customers', async ({ page }) => {
    const startTime = Date.now();

    console.log('=== Starting Bulk Transaction Creation: 200 Transactions ===\n');
    console.log('Total: 200 transactions');
    console.log('Returning customers: 2 (1%)');
    console.log('New customers: 198 (99%)\n');

    const returningCustomers = [
      { name: 'Tan Sri Datok Sri Ahmad bin Ibrahim', email: 'ahmad.ibrahim@test.com' },
      { name: 'Datin Seri Dr. Noraini binti Mohamad', email: 'noraini.mohamad@test.com' }
    ];

    const currencies = ['USD', 'SGD', 'JPY', 'EUR', 'GBP', 'AUD', 'CNY', 'THB'];
    const purposes = ['Travel', 'Education', 'Business', 'Investment', 'Medical', 'Family Support', 'Migration'];
    const fundSources = ['Salary', 'Savings', 'Business Income', 'Investment Returns', 'Inheritance', 'Consulting Fees', 'Dividends'];
    const wealthSources = ['Employment', 'Business Ownership', 'Professional Services', 'Real Estate', 'Financial Investments', 'Government Position', 'Agriculture'];

    let txCreated = 0;

    await loginAsTeller(page);

    for (let i = 1; i <= 200; i++) {
      const isReturning = i <= 2;
      const cust = isReturning ? returningCustomers[i - 1] : null;

      if (isReturning) {
        console.log('TX ' + i + '/200 - Returning: ' + cust!.name.substring(0, 40));
      } else if (i % 25 === 0) {
        console.log('TX ' + i + '/200 - New customer ' + i);
      }

      const name = cust ? cust.name : 'Customer ' + i;
      const email = cust ? cust.email : 'cust' + i + '@test.com';
      const ic = '99' + String(i).padStart(6, '0');
      const phone = '+60123456' + String(i).padStart(3, '0');
      const address = 'Jalan Test ' + i + ', Kuala Lumpur';

      await createCustomer(page, name, email, ic, phone, address, '1990-01-01');

      const currency = currencies[i % currencies.length];
      const purpose = purposes[i % purposes.length];
      const funds = fundSources[i % fundSources.length];
      const wealth = wealthSources[i % wealthSources.length];
      const qty = (1000 + i * 10).toFixed(2);
      const rate = (4.50 + (i % 10) * 0.01).toFixed(4);
      const txType = i % 3 === 0 ? 'Sell' : 'Buy';

      await createTransaction(page, name, txType, currency, purpose, funds, wealth, qty, rate);
      txCreated++;

      if (i % 50 === 0) {
        const elapsed = ((Date.now() - startTime) / 1000).toFixed(0);
        console.log('\n[PROGRESS] ' + i + '/200 (' + (i * 100 / 200).toFixed(0) + '%) - Elapsed: ' + elapsed + 's\n');
      }

      if (i < 200) await page.waitForTimeout(300);
    }

    console.log('\n=== Complete ===');
    console.log('Transactions created: ' + txCreated);
    console.log('Duration: ' + ((Date.now() - startTime) / 1000).toFixed(0) + 's');

    await page.goto(BASE_URL + '/transactions');
    await page.waitForLoadState('networkidle');
    console.log('DB count: ' + (await page.locator('table tbody tr').count()));
  });

});
