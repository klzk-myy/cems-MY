<?php

namespace Tests\Feature\Concurrency;

use App\Enums\AlertStatus;
use App\Enums\FindingStatus;
use App\Events\RelatedPartyOwnershipConcern;
use App\Events\SanctionsListUpdated;
use App\Exceptions\Domain\BranchDeactivationException;
use App\Listeners\RelatedPartyOwnershipConcernListener;
use App\Models\Branch;
use App\Models\Budget;
use App\Models\Compliance\Alert;
use App\Models\Compliance\ComplianceFinding;
use App\Models\Compliance\CustomerRiskProfile;
use App\Models\Compliance\SanctionList;
use App\Models\Customer;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\Accounting\BudgetService;
use App\Services\Audit\AuditChainService;
use App\Services\Branch\BranchService;
use App\Services\Compliance\SanctionsImportService;
use Illuminate\Cache\Lock;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

/**
 * Guard tests for the concurrency-adjacent invariants closed in the third
 * pass: import serialization, seal-attempt accounting, PEP cessation,
 * listener dedup, budget upserts, and branch hierarchy invariants.
 */
class InvariantGuardTest extends ConcurrentTestCase
{
    #[Test]
    public function sanctions_import_serializes_through_the_per_list_cache_lock(): void
    {
        Event::fake([SanctionsListUpdated::class]);
        $list = SanctionList::factory()->create();

        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('block')
            ->once()
            ->with(120, Mockery::type('callable'))
            ->andReturnUsing(fn (int $wait, callable $callback) => $callback());

        // partialMock keeps every other Cache call real so the import body
        // runs normally against the array driver.
        Cache::partialMock()
            ->shouldReceive('lock')
            ->once()
            ->with("sanctions_import:{$list->id}", 600)
            ->andReturn($lock);

        app(SanctionsImportService::class)->importWithData($list, []);
    }

    #[Test]
    public function failed_seal_attempts_increment_and_quarantine_at_the_threshold(): void
    {
        $log = SystemLog::factory()->create([
            'entry_hash' => null,
            'created_at' => now()->subMinutes(10),
            'seal_attempts' => 0,
        ]);

        $chain = Mockery::mock(AuditChainService::class);
        $chain->shouldReceive('sealLogEntry')->andThrow(new \RuntimeException('corrupt chain'));
        $chain->shouldReceive('quarantineEntry')->once()->with($log->id);
        $this->app->instance(AuditChainService::class, $chain);

        // First sweep counts the failure without losing it to a racing read.
        $this->assertSame(0, Artisan::call('audit:seal-pending', ['--attempts' => 2]));
        $this->assertSame(1, (int) $log->fresh()->seal_attempts);

        // Second sweep crosses the threshold and quarantines the row so the
        // chain can seal across it.
        $this->assertSame(0, Artisan::call('audit:seal-pending', ['--attempts' => 2]));
        $this->assertSame(2, (int) $log->fresh()->seal_attempts);
    }

    #[Test]
    public function pep_cessation_review_clears_the_flag_for_an_eligible_former_pep(): void
    {
        $customer = Customer::factory()->create([
            'is_active' => true,
            'pep_status' => true,
            'pep_role_ended_at' => now()->subYears(6),
            'current_role_domain' => 'private_sector',
            'former_pep_domain' => 'government',
        ]);

        $this->assertSame(0, Artisan::call('customers:pep-cessation-review'));

        $this->assertFalse((bool) $customer->fresh()->pep_status);
    }

    #[Test]
    public function pep_cessation_review_skips_a_locked_risk_profile(): void
    {
        $customer = Customer::factory()->create([
            'is_active' => true,
            'pep_status' => true,
            'pep_role_ended_at' => now()->subYears(6),
            'current_role_domain' => 'private_sector',
            'former_pep_domain' => 'government',
        ]);
        CustomerRiskProfile::factory()->create([
            'customer_id' => $customer->id,
            'locked_until' => now()->addYear(),
        ]);

        $this->assertSame(0, Artisan::call('customers:pep-cessation-review'));

        $this->assertTrue((bool) $customer->fresh()->pep_status);
    }

    #[Test]
    public function pep_cessation_review_dry_run_makes_no_changes(): void
    {
        $customer = Customer::factory()->create([
            'is_active' => true,
            'pep_status' => true,
            'pep_role_ended_at' => now()->subYears(6),
            'current_role_domain' => 'private_sector',
            'former_pep_domain' => 'government',
        ]);

        $this->assertSame(0, Artisan::call('customers:pep-cessation-review', ['--dry-run' => true]));

        $this->assertTrue((bool) $customer->fresh()->pep_status);
    }

    #[Test]
    public function duplicate_ownership_concern_events_do_not_stack_findings_or_alerts(): void
    {
        $customer = Customer::factory()->create();
        $relatedParty = Customer::factory()->create();
        $listener = app(RelatedPartyOwnershipConcernListener::class);

        $listener(new RelatedPartyOwnershipConcern($customer, $relatedParty, 0.30));
        $listener(new RelatedPartyOwnershipConcern($customer, $relatedParty, 0.30));

        $this->assertSame(1, ComplianceFinding::where('status', FindingStatus::New->value)->count());
        $this->assertSame(1, Alert::where('status', AlertStatus::Open)->count());
    }

    #[Test]
    public function repeated_set_budget_updates_in_place_instead_of_erroring_on_the_unique_key(): void
    {
        $user = User::factory()->create();
        $service = app(BudgetService::class);

        $service->setBudget('6000', '2026-01', '1000.00', $user->id);
        $service->setBudget('6000', '2026-01', '2500.00', $user->id);

        $budgets = Budget::where('account_code', '6000')
            ->where('period_code', '2026-01')
            ->get();

        $this->assertCount(1, $budgets);
        $this->assertSame('2500.0000', (string) $budgets->first()->budget_myr);
    }

    #[Test]
    public function deactivating_a_branch_with_active_children_is_rejected(): void
    {
        $parent = Branch::factory()->create(['is_active' => true, 'is_main' => false]);
        Branch::factory()->create(['parent_id' => $parent->id, 'is_active' => true]);

        $this->expectException(BranchDeactivationException::class);

        app(BranchService::class)->deactivateBranch($parent);
    }

    #[Test]
    public function activating_a_child_under_an_inactive_parent_is_rejected_on_create_and_update(): void
    {
        $parent = Branch::factory()->create(['is_active' => false]);
        $service = app(BranchService::class);

        $this->expectException(BranchDeactivationException::class);

        try {
            $service->createBranch([
                'code' => 'CHILD01',
                'name' => 'Child Branch',
                'type' => Branch::TYPE_BRANCH,
                'is_active' => true,
                'parent_id' => $parent->id,
            ]);
        } finally {
            // updateBranch must enforce the same invariant.
            $child = Branch::factory()->create([
                'parent_id' => $parent->id,
                'is_active' => false,
            ]);

            try {
                $service->updateBranch($child, [
                    'code' => $child->code,
                    'name' => $child->name,
                    'type' => $child->type,
                    'is_active' => true,
                    'parent_id' => $parent->id,
                ]);
                $this->fail('updateBranch activated a child under an inactive parent');
            } catch (BranchDeactivationException) {
                // expected
            }
        }
    }

    #[Test]
    public function promoting_a_branch_to_main_demotes_the_previous_main_atomically(): void
    {
        $oldMain = Branch::factory()->main()->create();
        $challenger = Branch::factory()->create(['is_main' => false]);

        app(BranchService::class)->updateBranch($challenger, [
            'code' => $challenger->code,
            'name' => $challenger->name,
            'type' => $challenger->type,
            'is_main' => true,
        ]);

        $this->assertTrue((bool) $challenger->fresh()->is_main);
        $this->assertFalse((bool) $oldMain->fresh()->is_main);
        $this->assertSame(1, Branch::where('is_main', true)->count());
    }
}
