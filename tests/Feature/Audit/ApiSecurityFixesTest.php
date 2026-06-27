<?php

namespace Tests\Feature\Audit;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\Transaction;
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

    public function test_teller_cannot_lock_customer_risk_profile(): void
    {
        $teller = User::factory()->for(Branch::factory()->create())->create([
            'role' => UserRole::Teller,
        ]);
        $customer = Customer::factory()->create();

        $this->actingAs($teller, 'sanctum')
            ->postJson(route('api.v1.risk.lock', $customer))
            ->assertForbidden();
    }

    public function test_teller_cannot_view_other_branch_customer_history(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $tellerA = User::factory()->for($branchA)->create(['role' => UserRole::Teller]);
        $customerB = Customer::factory()->create();

        Transaction::factory()->for($customerB)->for($branchB)->create([
            'user_id' => $tellerA->id,
        ]);

        $this->actingAs($tellerA, 'sanctum')
            ->getJson(route('api.v1.customers.history', $customerB))
            ->assertForbidden();
    }

    public function test_teller_sees_only_own_branch_transactions_in_customer_history(): void
    {
        $branchA = Branch::factory()->create();
        $branchB = Branch::factory()->create();
        $tellerA = User::factory()->for($branchA)->create(['role' => UserRole::Teller]);
        $customer = Customer::factory()->create();

        $branchATransaction = Transaction::factory()->for($customer)->for($branchA)->create([
            'user_id' => $tellerA->id,
        ]);
        $branchBTransaction = Transaction::factory()->for($customer)->for($branchB)->create([
            'user_id' => $tellerA->id,
        ]);

        $response = $this->actingAs($tellerA, 'sanctum')
            ->getJson(route('api.v1.customers.history', $customer));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $branchATransaction->id)
            ->assertJsonMissing(['id' => $branchBTransaction->id]);
    }

    public function test_teller_cannot_close_counter(): void
    {
        $branch = Branch::factory()->create();
        $teller = User::factory()->for($branch)->create(['role' => UserRole::Teller]);
        $counter = Counter::factory()->for($branch)->create();

        $this->actingAs($teller)
            ->post(route('counters.close', $counter))
            ->assertForbidden();
    }

    public function test_password_hash_is_not_mass_assignable(): void
    {
        $user = User::create([
            'branch_id' => Branch::factory()->create()->id,
            'username' => 'testuser',
            'email' => 'test@example.com',
            'password_hash' => 'hacked',
        ]);

        $this->assertNotSame('hacked', $user->password_hash);
    }
}
