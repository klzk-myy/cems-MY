<?php

namespace Tests\Feature\Views;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LayoutConsistencyTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('sharedLayoutViewProvider')]
    public function test_view_extends_shared_layout(string $route, string $view): void
    {
        $user = User::factory()->create(['role' => 'compliance_officer']);
        $customer = Customer::factory()->create();

        $route = str_replace('{customer}', (string) $customer->id, $route);

        if ($route === '/mfa/recovery-codes') {
            $response = $this->actingAs($user)
                ->withSession(['mfa_recovery_codes' => ['code1', 'code2']])
                ->get($route);
        } else {
            $response = $this->actingAs($user)->get($route);
        }

        $response->assertStatus(200);

        $viewPath = resource_path('views/'.str_replace('.', '/', $view).'.blade.php');
        $this->assertFileExists($viewPath);
        $content = file_get_contents($viewPath);
        $this->assertStringContainsString('<x-app-layout', $content);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $content);
    }

    public static function sharedLayoutViewProvider(): array
    {
        return [
            'risk-dashboard-customer' => ['/compliance/risk-dashboard/customer/{customer}', 'compliance.risk-dashboard.customer'],
            'risk-dashboard-trends' => ['/compliance/risk-dashboard/trends', 'compliance.risk-dashboard.trends'],
            'sanctions-import-logs' => ['/compliance/sanctions/import-logs', 'compliance.sanctions.import-logs.index'],
            'screening-show' => ['/compliance/screening/{customer}', 'compliance.screening.show'],
            'unified-index' => ['/compliance/unified', 'compliance.unified.index'],
            'mfa-recovery-codes' => ['/mfa/recovery-codes', 'pages.mfa.recovery-codes'],
        ];
    }

    public function test_recovery_codes_redirects_without_session_codes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('mfa.recovery-codes'));

        $response->assertRedirect(route('mfa.setup'));
    }
}
