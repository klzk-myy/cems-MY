<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DateFilterValidationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function journal_index_rejects_a_malformed_date_filter(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]));

        $this->get('/accounting/journal?date=not-a-date')
            ->assertSessionHasErrors('date');
    }

    #[Test]
    public function journal_index_accepts_a_valid_date_filter(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]));

        $this->get('/accounting/journal?date=2026-09-17')
            ->assertStatus(200);
    }

    #[Test]
    public function findings_index_rejects_a_malformed_date_filter(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::ComplianceOfficer]));

        $this->get('/compliance/findings?date_from=not-a-date')
            ->assertSessionHasErrors('date_from');
    }

    #[Test]
    public function findings_index_rejects_a_status_that_is_not_a_finding_status(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::ComplianceOfficer]));

        $this->get('/compliance/findings?status=Open')
            ->assertSessionHasErrors('status');
    }

    #[Test]
    public function api_findings_index_rejects_a_malformed_date_filter(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'compliance_officer']));

        $this->getJson('/api/v1/compliance/findings?date_from=not-a-date')
            ->assertStatus(422)
            ->assertJsonValidationErrors('date_from');
    }
}
