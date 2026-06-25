<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class TransferNumberTest extends TestCase
{
    public function test_generate_transfer_number_uses_lock_and_retry(): void
    {
        $file = base_path('app/Models/StockTransfer.php');
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString('lockForUpdate()', $content);
        $this->assertStringContainsString('while (true)', $content);
        $this->assertStringContainsString('$maxRetries', $content);
    }
}
