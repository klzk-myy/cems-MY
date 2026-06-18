<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionCancellationApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function legacy_cancel_method_removed()
    {
        $transaction = Transaction::factory()->create();

        $response = $this->actingAs(User::factory()->create(['role' => UserRole::Manager]))
            ->postJson("/api/v1/transactions/{$transaction->id}/cancel", [
                'cancellation_reason' => 'Test reason',
            ]);

        $response->assertStatus(404); // Method should not exist
    }
}
