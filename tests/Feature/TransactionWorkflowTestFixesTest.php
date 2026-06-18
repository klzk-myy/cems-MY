<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionWorkflowTestFixesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function transaction_workflow_test_does_not_accept_500_as_success(): void
    {
        $fileContent = file_get_contents(
            base_path('tests/Feature/TransactionWorkflowTest.php')
        );

        $this->assertStringNotContainsString(
            'in_array($response->status(), [200, 201, 500])',
            $fileContent,
            'TransactionWorkflowTest should not accept HTTP 500 as an expected status'
        );
    }

    #[Test]
    public function transaction_workflow_test_does_not_accept_500_for_view(): void
    {
        $fileContent = file_get_contents(
            base_path('tests/Feature/TransactionWorkflowTest.php')
        );

        $this->assertStringNotContainsString(
            'in_array($response->status(), [200, 404, 500])',
            $fileContent,
            'TransactionWorkflowTest should not accept HTTP 500 for view endpoint'
        );
    }
}
