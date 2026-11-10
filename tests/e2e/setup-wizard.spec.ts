import { expect, test } from '@playwright/test';

/**
 * Setup wizard gate. On a completed install every /setup/* route is
 * hard-blocked (403) — re-entering the wizard on a live system would be a
 * takeover path. On a fresh install the wizard renders its 6-step flow.
 */
test('setup routes respect the completed-install block', async ({ page }) => {
  const response = await page.goto('/setup');

  if (response && response.status() === 403) {
    // Completed install: the wizard must not render at all.
    await expect(page.getByText('Step 1 of 6')).toHaveCount(0);
    return;
  }

  // Fresh install: wizard index renders the step indicator.
  await expect(page.getByText(/Step \d of 6/)).toBeVisible();
  await expect(page.getByText('Company Information')).toBeVisible();
});
