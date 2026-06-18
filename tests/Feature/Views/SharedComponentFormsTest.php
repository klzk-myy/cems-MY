<?php

namespace Tests\Feature\Views;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SharedComponentFormsTest extends TestCase
{
    private function getViewPath(string $view): string
    {
        return resource_path('views/'.str_replace('.', '/', $view).'.blade.php');
    }

    #[Test]
    public function customer_create_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('customers.create');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    #[Test]
    public function customer_edit_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('customers.edit');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    #[Test]
    public function customer_show_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('customers.show');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    #[Test]
    public function sanctions_entry_create_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('compliance.sanctions.entries.create');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    #[Test]
    public function sanctions_entry_edit_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('compliance.sanctions.entries.edit');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    #[Test]
    public function transaction_cancel_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('transactions.cancel');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    #[Test]
    public function transaction_approve_cancellation_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('transactions.approve-cancellation');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    #[Test]
    public function transaction_reject_cancellation_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('transactions.reject-cancellation');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    #[Test]
    public function counter_handover_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('counters.handover');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    #[Test]
    public function counter_close_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('counters.close');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    #[Test]
    public function counter_emergency_closure_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('counters.emergency-closure');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    #[Test]
    public function counter_emergency_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('counters.emergency');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    #[Test]
    public function rates_index_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('rates.index');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    #[Test]
    public function stock_transfer_create_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('stock-transfers.create');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    #[Test]
    public function customer_create_uses_radio_group(): void
    {
        $path = $this->getViewPath('customers.create');
        $content = file_get_contents($path);

        // Raw radio inputs should be replaced
        $this->assertStringNotContainsString('type="radio"', $content);

        // Check radio-group component is present with correct name
        $this->assertStringContainsString('<x-radio-group', $content);
        $this->assertStringContainsString('name="risk_level"', $content);
    }

    #[Test]
    public function user_create_uses_checkbox_components(): void
    {
        $path = $this->getViewPath('users.create');
        $content = file_get_contents($path);

        // Raw checkbox inputs should be replaced
        $this->assertStringNotContainsString('type="checkbox"', $content);

        // Check checkbox components are present
        $this->assertStringContainsString('<x-checkbox', $content);
        $this->assertStringContainsString('name="is_active"', $content);
        $this->assertStringContainsString('name="mfa_enabled"', $content);
        $this->assertStringContainsString('label="Active User"', $content);
        $this->assertStringContainsString('label="Enable MFA (Required for all roles)"', $content);
    }

    #[Test]
    public function user_edit_uses_checkbox_component(): void
    {
        $path = $this->getViewPath('users.edit');
        $content = file_get_contents($path);

        // Raw checkbox inputs should be replaced
        $this->assertStringNotContainsString('type="checkbox"', $content);

        // Check checkbox component is present
        $this->assertStringContainsString('<x-checkbox', $content);
        $this->assertStringContainsString('name="is_active"', $content);
        $this->assertStringContainsString('label="Active"', $content);
    }

    #[Test]
    public function setup_index_uses_checkbox_components(): void
    {
        $path = $this->getViewPath('setup.index');
        $content = file_get_contents($path);

        // Raw x-input type="checkbox" should be replaced with x-checkbox
        $this->assertStringNotContainsString('<x-input type="checkbox"', $content);

        // Check x-checkbox components are present
        $this->assertStringContainsString('<x-checkbox', $content);
        $this->assertStringContainsString('name="currency_codes[]"', $content);
        $this->assertStringContainsString('name="use_default_rates"', $content);
    }

    #[Test]
    public function setup_index_uses_textarea_component(): void
    {
        $path = $this->getViewPath('setup.index');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('type="textarea"', $content);
        $this->assertStringContainsString('<x-textarea', $content);
        $this->assertStringContainsString('name="business_address"', $content);
    }

    #[Test]
    public function setup_index_uses_on_primary_foreground(): void
    {
        $path = $this->getViewPath('setup.index');
        $content = file_get_contents($path);

        $this->assertStringContainsString('bg-primary text-on-primary', $content);
        $this->assertStringNotContainsString('bg-primary text-white', $content);
    }

    #[Test]
    public function bank_reconciliation_uses_checkbox_components(): void
    {
        $path = $this->getViewPath('accounting.reconciliation');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<input type="checkbox"', $content);
        $this->assertStringContainsString('<x-checkbox', $content);
    }
}
