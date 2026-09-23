<?php

namespace Tests\Feature;

use App\Enums\ReportType;
use App\Enums\UserRole;
use App\Models\ReportGenerated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * BNM regulatory report export endpoints (pd-00 reporting obligations):
 * MSB(2), Form LMCA, Quarterly Large-Value, and Position Limit. Each export
 * must stream a download and register a reports_generated artifact so the
 * archival sweep can track it. The export routes step up with
 * password.confirm, hence the confirmed session on every request.
 */
class RegulatoryReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        // Manager carries view_reports in the default matrix; the BNM
        // license number comes from phpunit.xml (TEST-LICENSE-001).
        $this->manager = User::factory()->create(['role' => UserRole::Manager]);
    }

    #[Test]
    public function msb2_export_downloads_csv_and_records_the_artifact(): void
    {
        $response = $this->export('reports.msb2.export', ['date' => now()->toDateString()]);

        $response->assertDownload();
        $this->assertArtifactRegistered(ReportType::Msb2);
    }

    #[Test]
    public function msb2_export_defaults_to_yesterday_when_no_date_is_supplied(): void
    {
        $response = $this->export('reports.msb2.export');

        $response->assertDownload();
        $this->assertArtifactRegistered(ReportType::Msb2);
    }

    #[Test]
    public function lmca_export_downloads_csv_for_the_month(): void
    {
        $response = $this->export('reports.lmca.export', ['month' => now()->format('Y-m')]);

        $response->assertDownload();
        $this->assertArtifactRegistered(ReportType::Lmca);
    }

    #[Test]
    public function lmca_export_rejects_a_malformed_month(): void
    {
        $this->export('reports.lmca.export', ['month' => 'not-a-month'])
            ->assertRedirect()
            ->assertSessionHasErrors('month');

        $this->assertSame(0, ReportGenerated::where('report_type', ReportType::Lmca->value)->count());
    }

    #[Test]
    public function quarterly_lvr_export_downloads_csv_for_the_quarter(): void
    {
        $quarter = sprintf('%d-Q%d', now()->year, intdiv(now()->month - 1, 3) + 1);

        $response = $this->export('reports.quarterly-lvr.export', ['quarter' => $quarter]);

        $response->assertDownload();
        $this->assertArtifactRegistered(ReportType::Qlvr);
    }

    #[Test]
    public function quarterly_lvr_export_rejects_a_malformed_quarter(): void
    {
        $this->export('reports.quarterly-lvr.export', ['quarter' => '2026-Q5'])
            ->assertRedirect()
            ->assertSessionHasErrors('quarter');
    }

    #[Test]
    public function position_limit_export_downloads_csv(): void
    {
        $response = $this->export('reports.position-limit.export');

        $response->assertDownload();
        $this->assertArtifactRegistered(ReportType::Plr);
    }

    #[Test]
    public function teller_without_view_reports_is_forbidden_from_exporting(): void
    {
        $teller = User::factory()->create(['role' => UserRole::Teller]);

        $this->actingAs($teller)
            ->withSession($this->passwordConfirmedSession())
            ->post(route('reports.msb2.export'), ['date' => now()->toDateString()])
            ->assertForbidden();
    }

    /**
     * POST an export with the password-confirmation step-up satisfied.
     *
     * @param  array<string, mixed>  $payload
     * @return TestResponse<Response>
     */
    private function export(string $routeName, array $payload = []): TestResponse
    {
        return $this->actingAs($this->manager)
            ->withSession($this->passwordConfirmedSession())
            ->post(route($routeName), $payload);
    }

    private function assertArtifactRegistered(ReportType $type): void
    {
        $this->assertSame(
            1,
            ReportGenerated::where('report_type', $type)->count(),
            "The {$type->value} export should register exactly one reports_generated artifact"
        );
    }
}
