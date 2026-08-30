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
        $content = $this->readSource('app/Services/Transaction/TransactionImportService.php');

        $this->assertStringContainsString(
            "\$data['idempotency_key'] = hash('sha256', \$encoded);",
            $content,
            'Should generate idempotency key for every import row'
        );
        $this->assertStringContainsString(
            "throw new ImportValidationException('Row data could not be encoded for idempotency key')",
            $content,
            'Should fail loudly when a row cannot be encoded for its idempotency key'
        );
        $this->assertStringContainsString(
            'createForImport',
            $content,
            'Should delegate dedup to TransactionCreationService via createForImport'
        );
    }

    private function readSource(string $relativePath): string
    {
        $path = base_path($relativePath);

        $this->assertFileExists($path);

        $content = file_get_contents($path);

        if ($content === false) {
            $this->fail("Unable to read {$relativePath}");
        }

        return $content;
    }
}
