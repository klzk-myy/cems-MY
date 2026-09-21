<?php

namespace Tests\Unit\Services\Compliance;

use App\Enums\ComplianceCaseStatus;
use App\Models\Compliance\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\Customer;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\Compliance\CaseManagementService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CaseManagementServiceAuditTest extends TestCase
{
    use DatabaseTransactions;

    protected CaseManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CaseManagementService::class);
    }

    #[Test]
    public function creating_a_case_from_alerts_writes_a_case_created_audit_record(): void
    {
        $customer = Customer::factory()->create();
        $officer = User::factory()->create();

        $alerts = collect([
            Alert::factory()->create(['customer_id' => $customer->id, 'risk_score' => 80]),
            Alert::factory()->create(['customer_id' => $customer->id, 'risk_score' => 65]),
        ]);

        $case = $this->service->createFromAlerts($alerts->pluck('id')->all(), $officer->id);

        $log = SystemLog::where('action', 'compliance_case_created')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString($case->case_number, (string) $log->description);
    }

    #[Test]
    public function resolving_a_case_writes_a_case_closed_audit_record(): void
    {
        $customer = Customer::factory()->create();
        $resolvedBy = User::factory()->create()->id;

        $case = ComplianceCase::factory()->create([
            'customer_id' => $customer->id,
            'status' => ComplianceCaseStatus::UnderReview,
        ]);

        $this->service->resolveCase($case, $resolvedBy);

        $log = SystemLog::where('action', 'compliance_case_closed')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString($case->case_number, (string) $log->description);
    }
}
