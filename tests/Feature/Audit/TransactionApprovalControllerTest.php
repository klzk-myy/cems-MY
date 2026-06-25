<?php

namespace Tests\Feature\Audit;

use Tests\TestCase;

class TransactionApprovalControllerTest extends TestCase
{
    public function test_undefined_user_id_is_not_used(): void
    {
        $file = base_path('app/Http/Controllers/Transaction/TransactionApprovalController.php');
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringNotContainsString('$userId', $content, 'Should not use undefined $userId variable');
        $this->assertStringContainsString('auth()->id()', $content, 'Should use auth()->id() for user identification');
    }
}
