<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class TransactionImportIdempotencyTest extends TestCase
{
    /**
     * Test that TransactionImportService includes idempotency check.
     */
    public function test_transaction_import_has_idempotency_check(): void
    {
        $file = base_path('app/Services/Transaction/TransactionImportService.php');
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString(
            "if (! empty(\$data['idempotency_key']))",
            $content,
            'Idempotency key check should be present'
        );
        $this->assertStringContainsString(
            "\$existing = Transaction::where('idempotency_key', \$data['idempotency_key'])->exists();",
            $content,
            'Should check for existing transaction with same idempotency key'
        );
    }
}
