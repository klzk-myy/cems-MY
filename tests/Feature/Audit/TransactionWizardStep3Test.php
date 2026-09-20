<?php

namespace Tests\Feature\Audit;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransactionWizardStep3Test extends TestCase
{
    use RefreshDatabase;

    /**
     * The submit step must fail closed on sessions it cannot resolve to the
     * authenticated user — an expired or unknown session id is a 404.
     * (Full submit coverage lives in tests/Feature/TransactionWizardTest.)
     */
    public function test_step3_rejects_unknown_wizard_session(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $this->actingAs($teller);
        $this->setMfaVerification($teller);

        $response = $this->postJson('/api/v1/wizard/transactions/step3', [
            'wizard_session_id' => 'nonexistent-session-'.Str::random(8),
            'confirm_details' => true,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(404);
    }
}
