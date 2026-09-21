<?php

namespace Tests\Unit\Services\Compliance;

use App\Enums\AlertPriority;
use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Enums\FindingType;
use App\Models\Compliance\Alert;
use App\Models\Compliance\ComplianceFinding;
use App\Models\Customer;
use App\Services\Compliance\UnifiedAlertQueryService;
use App\ValueObjects\UnifiedAlertFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UnifiedAlertQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_merges_alerts_and_findings_into_one_page_with_enum_labels(): void
    {
        $customer = Customer::factory()->create();
        $alert = Alert::factory()->create([
            'customer_id' => $customer->id,
            'priority' => AlertPriority::Low,
        ]);
        $finding = ComplianceFinding::factory()->create([
            'subject_type' => 'Customer',
            'subject_id' => $customer->id,
            'finding_type' => FindingType::SanctionMatch,
            'severity' => FindingSeverity::Critical,
            'status' => FindingStatus::CaseCreated,
        ]);

        $result = app(UnifiedAlertQueryService::class)->page(
            new UnifiedAlertFilters('all', null, null, null, null, null, null)
        );

        $ids = array_column($result['items'], 'id');
        $this->assertContains('A-'.$alert->id, $ids);
        $this->assertContains('F-'.$finding->id, $ids);

        $findingItem = collect($result['items'])->firstWhere('id', 'F-'.$finding->id);
        $this->assertSame('Sanction Match', $findingItem['type_label']);
        $this->assertSame('Case Created', $findingItem['status_label']);

        $this->assertSame(2, $result['stats']['total']);
        $this->assertSame(1, $result['stats']['critical']);
    }

    #[Test]
    public function it_labels_legacy_underscored_status_values(): void
    {
        $customer = Customer::factory()->create();
        $alert = Alert::factory()->create(['priority' => AlertPriority::Low]);
        $finding = ComplianceFinding::factory()->create([
            'subject_type' => 'Customer',
            'subject_id' => $customer->id,
            'finding_type' => FindingType::SanctionMatch,
            'severity' => FindingSeverity::Critical,
            'status' => FindingStatus::CaseCreated,
        ]);

        // Legacy TitleCase/underscored values written before the enum
        // normalization must still resolve to labels — Str::snake alone
        // would turn 'Under_Review' into 'under__review'.
        DB::table('alerts')->where('id', $alert->id)->update(['status' => 'Under_Review']);
        DB::table('compliance_findings')->where('id', $finding->id)->update(['status' => 'Case_Created']);

        $result = app(UnifiedAlertQueryService::class)->page(
            new UnifiedAlertFilters('all', null, null, null, null, null, null)
        );

        $alertItem = collect($result['items'])->firstWhere('id', 'A-'.$alert->id);
        $this->assertSame('Under Review', $alertItem['status_label']);

        $findingItem = collect($result['items'])->firstWhere('id', 'F-'.$finding->id);
        $this->assertSame('Case Created', $findingItem['status_label']);
    }

    #[Test]
    public function it_returns_an_empty_page_when_the_source_matches_neither_side(): void
    {
        $result = app(UnifiedAlertQueryService::class)->page(
            new UnifiedAlertFilters('none', null, null, null, null, null, null)
        );

        $this->assertSame([], $result['items']);
        $this->assertSame(0, $result['stats']['total']);
        $this->assertSame(0, $result['pagination']['total']);
    }
}
