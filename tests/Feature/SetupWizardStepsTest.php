<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Setup wizard step endpoints: each step POST validates its payload and
 * parks it in the setup session so the final executeSetup call can seed the
 * install atomically. Steps 1 (business + admin), 5 (initial stock), and
 * 6 (opening balances) are covered here; the completion path is covered by
 * SetupControllerTest/SetupQuickSetupParityTest.
 */
class SetupWizardStepsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function step1_stores_business_details_and_advances_to_step_2(): void
    {
        $this->post(route('setup.step1'), [
            'business_name' => 'Test Money Services Sdn Bhd',
            'business_email' => 'ops@setup-test.local',
        ])
            ->assertRedirect(route('setup.wizard', ['step' => 2]));

        $business = session('setup.business');
        $this->assertNotNull($business, 'Step 1 must park the business payload in the setup session');
        $this->assertSame('Test Money Services Sdn Bhd', $business['business_name']);
        $this->assertSame('ops@setup-test.local', $business['business_email']);
    }

    #[Test]
    public function step1_requires_the_business_name(): void
    {
        $this->post(route('setup.step1'), [
            'business_name' => '',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('business_name');

        $this->assertNull(session('setup.business'), 'A failed step 1 must not park anything');
    }

    #[Test]
    public function step5_merges_initial_stock_into_the_setup_session(): void
    {
        $this->post(route('setup.step5'), [
            'initial_myr_cash' => '100000',
            'initial_stock' => ['USD' => '5000', 'EUR' => '3000'],
        ])
            ->assertRedirect(route('setup.wizard', ['step' => 6]));

        $stock = session('setup.stock');
        $this->assertNotNull($stock, 'Step 5 must park the stock payload in the setup session');
        $this->assertSame('100000', (string) $stock['initial_myr_cash']);
        $this->assertSame('5000', (string) $stock['initial_stock']['USD']);
        // MYR cash is folded into the stock map alongside the foreign stock.
        $this->assertArrayHasKey('MYR', $stock['initial_stock']);
    }

    #[Test]
    public function step5_requires_a_non_negative_myr_float(): void
    {
        $this->post(route('setup.step5'), [
            'initial_myr_cash' => '-1',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('initial_myr_cash');

        $this->assertNull(session('setup.stock'));
    }

    #[Test]
    public function step6_stores_the_opening_balances(): void
    {
        $this->post(route('setup.step6'), [
            'opening_balance_myr' => '250000',
            'opening_balance_foreign' => ['USD' => '10000'],
        ])
            ->assertRedirect(route('setup.wizard', ['step' => 7]));

        $balances = session('setup.opening_balance');
        $this->assertNotNull($balances, 'Step 6 must park the opening balances in the setup session');
        $this->assertSame('250000', (string) $balances['opening_balance_myr']);
    }

    #[Test]
    public function step6_requires_the_myr_opening_balance(): void
    {
        $this->post(route('setup.step6'), [])
            ->assertRedirect()
            ->assertSessionHasErrors('opening_balance_myr');
    }
}
