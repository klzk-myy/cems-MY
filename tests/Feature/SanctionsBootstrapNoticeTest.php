<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SanctionEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SanctionsBootstrapNoticeTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsComplianceAdmin(): User
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin->value,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        return $user;
    }

    #[Test]
    public function screening_matches_index_warns_when_sanction_entries_are_empty(): void
    {
        $this->actingAsComplianceAdmin();

        $response = $this->get(route('compliance.screening.matches.index'));

        $response->assertOk();
        $response->assertSee('Sanctions lists not loaded');
        $response->assertSee('php artisan sanctions:update');
    }

    #[Test]
    public function screening_matches_index_hides_warning_once_entries_exist(): void
    {
        $this->actingAsComplianceAdmin();

        SanctionEntry::factory()->create();

        $response = $this->get(route('compliance.screening.matches.index'));

        $response->assertOk();
        $response->assertDontSee('Sanctions lists not loaded');
    }

    #[Test]
    public function risk_dashboard_warns_when_sanction_entries_are_empty(): void
    {
        $this->actingAsComplianceAdmin();

        $response = $this->get(route('compliance.risk-dashboard.index'));

        $response->assertOk();
        $response->assertSee('Sanctions lists not loaded');
        $response->assertSee('php artisan sanctions:update');
    }

    #[Test]
    public function risk_dashboard_hides_warning_once_entries_exist(): void
    {
        $this->actingAsComplianceAdmin();

        SanctionEntry::factory()->create();

        $response = $this->get(route('compliance.risk-dashboard.index'));

        $response->assertOk();
        $response->assertDontSee('Sanctions lists not loaded');
    }

    #[Test]
    public function login_page_renders_flashed_setup_notice(): void
    {
        $response = $this->withSession([
            '_flash' => ['new' => ['info'], 'old' => ['info']],
            'info' => 'Run "php artisan sanctions:update" now',
        ])->get(route('login'));

        $response->assertOk();
        $response->assertSee('Action required');
        $response->assertSee('php artisan sanctions:update');
    }
}
