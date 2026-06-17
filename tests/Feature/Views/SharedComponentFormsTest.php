<?php

namespace Tests\Feature\Views;

use Tests\TestCase;

class SharedComponentFormsTest extends TestCase
{
    private function getViewPath(string $view): string
    {
        return resource_path('views/'.str_replace('.', '/', $view).'.blade.php');
    }

    public function test_customer_create_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('customers.create');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    public function test_customer_edit_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('customers.edit');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    public function test_customer_show_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('customers.show');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    public function test_sanctions_entry_create_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('compliance.sanctions.entries.create');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    public function test_sanctions_entry_edit_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('compliance.sanctions.entries.edit');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    public function test_transaction_cancel_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('transactions.cancel');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    public function test_transaction_approve_cancellation_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('transactions.approve-cancellation');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    public function test_transaction_reject_cancellation_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('transactions.reject-cancellation');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    public function test_counter_handover_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('counters.handover');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    public function test_counter_close_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('counters.close');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    public function test_counter_emergency_closure_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('counters.emergency-closure');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    public function test_counter_emergency_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('counters.emergency');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    public function test_rates_index_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('rates.index');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }

    public function test_stock_transfer_create_form_uses_textarea_component(): void
    {
        $path = $this->getViewPath('stock-transfers.create');
        $content = file_get_contents($path);

        $this->assertStringNotContainsString('<textarea', $content);
        $this->assertStringContainsString('<x-textarea', $content);
    }
}
