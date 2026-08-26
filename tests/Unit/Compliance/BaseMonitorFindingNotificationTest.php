<?php

namespace Tests\Unit\Compliance;

use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Enums\FindingType;
use App\Enums\UserRole;
use App\Models\Compliance\ComplianceFinding;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\Compliance\ComplianceFindingNotification;
use App\Services\Compliance\Monitors\BaseMonitor;
use App\Services\System\MathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BaseMonitorFindingNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Minimal concrete monitor used to exercise BaseMonitor::storeFinding().
     */
    private function monitor(): FindingStoringTestMonitor
    {
        return new FindingStoringTestMonitor(app(MathService::class));
    }

    /**
     * @return array<string, mixed>
     */
    private function findingData(Customer $customer): array
    {
        return [
            'finding_type' => FindingType::VelocityExceeded->value,
            'severity' => FindingSeverity::High->value,
            'subject_type' => Customer::class,
            'subject_id' => $customer->id,
            'details' => ['description' => 'Velocity exceeded'],
            'status' => FindingStatus::New->value,
            'generated_at' => now(),
        ];
    }

    #[Test]
    public function new_finding_notifies_active_compliance_officers(): void
    {
        Notification::fake();

        $officer = User::factory()->create([
            'role' => UserRole::ComplianceOfficer,
            'is_active' => true,
        ]);
        User::factory()->create(['role' => UserRole::Teller, 'is_active' => true]);
        $customer = Customer::factory()->create();

        $stored = $this->monitor()->store($this->findingData($customer));

        $this->assertInstanceOf(ComplianceFinding::class, $stored);

        Notification::assertSentTo($officer, ComplianceFindingNotification::class);
        Notification::assertSentToTimes($officer, ComplianceFindingNotification::class, 1);
    }

    #[Test]
    public function merged_duplicate_does_not_re_notify(): void
    {
        Notification::fake();

        $officer = User::factory()->create([
            'role' => UserRole::Manager,
            'is_active' => true,
        ]);
        $customer = Customer::factory()->create();

        // First detection creates the finding and notifies.
        $first = $this->monitor()->store($this->findingData($customer));
        $this->assertInstanceOf(ComplianceFinding::class, $first);

        Notification::assertSentTo($officer, ComplianceFindingNotification::class);

        // Second detection of the same open finding merges details instead.
        $second = $this->monitor()->store($this->findingData($customer));

        $this->assertNull($second);
        Notification::assertSentToTimes($officer, ComplianceFindingNotification::class, 1);
    }
}

/**
 * Minimal concrete monitor used to exercise BaseMonitor::storeFinding().
 */
final class FindingStoringTestMonitor extends BaseMonitor
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function run(): array
    {
        return [];
    }

    protected function getFindingType(): FindingType
    {
        return FindingType::VelocityExceeded;
    }

    /**
     * @param  array<string, mixed>  $findingData
     */
    public function store(array $findingData): ?ComplianceFinding
    {
        return $this->storeFinding($findingData);
    }
}
