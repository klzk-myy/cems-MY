<?php

namespace Tests\Feature\Audit;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiSecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('reportRouteProvider')]
    public function test_teller_cannot_access_report_routes(string $method, string $route, array $params, array $payload): void
    {
        $teller = User::factory()->for(Branch::factory()->create())->create([
            'role' => UserRole::Teller,
        ]);

        $this->actingAs($teller, 'sanctum')
            ->json($method, route($route, $params), $payload)
            ->assertForbidden();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<string, string>, 3: array<string, string>}>
     */
    public static function reportRouteProvider(): array
    {
        $yesterday = today()->subDay()->toDateString();

        return [
            'msb2 generate' => [
                'POST',
                'api.v1.reports.msb2',
                [],
                ['date' => $yesterday],
            ],
            'msb2 status' => [
                'POST',
                'api.v1.reports.msb2.status',
                [],
                ['date' => $yesterday, 'status' => 'Submitted'],
            ],
            'lmca status' => [
                'POST',
                'api.v1.reports.lmca.status',
                [],
                ['month' => today()->subMonth()->format('Y-m'), 'status' => 'Submitted'],
            ],
            'report download' => [
                'GET',
                'api.v1.reports.download',
                ['filename' => 'report.csv'],
                [],
            ],
        ];
    }

    public function test_teller_cannot_list_compliance_findings(): void
    {
        $teller = User::factory()->for(Branch::factory()->create())->create([
            'role' => UserRole::Teller,
        ]);

        $this->actingAs($teller, 'sanctum')
            ->getJson(route('api.v1.compliance.findings.index'))
            ->assertForbidden();
    }
}
