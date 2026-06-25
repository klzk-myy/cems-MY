<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class TransactionWizardStep3Test extends TestCase
{
    public function test_wizard_step3_passes_user_id_and_ip(): void
    {
        $file = base_path('app/Http/Controllers/TransactionWizardController.php');
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString('createTransaction(', $content);
        $this->assertStringContainsString('auth()->id()', $content);
        $this->assertStringContainsString('request()->ip()', $content);
    }
}
