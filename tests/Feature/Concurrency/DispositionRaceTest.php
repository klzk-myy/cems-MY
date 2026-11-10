<?php

namespace Tests\Feature\Concurrency;

use App\Enums\EddStatus;
use App\Enums\FindingStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\TransactionCreated;
use App\Exceptions\Domain\CaseManagementException;
use App\Exceptions\Domain\EddValidationException;
use App\Jobs\ComplianceScreeningJob;
use App\Listeners\TransactionCreatedListener;
use App\Models\Compliance\ComplianceFinding;
use App\Models\Compliance\CustomerRiskProfile;
use App\Models\Compliance\EnhancedDiligenceRecord;
use App\Models\Compliance\FlaggedTransaction;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Compliance\EddService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

/**
 * Regression tests for the disposition/transition races closed in the
 * second and third concurrency passes: ComplianceFinding stale dispositions,
 * EDD terminal-status rewinds, risk-profile lock clobbering, out-of-order
 * listener writes, EDD reference collisions, and sanctions-hold rewinds.
 *
 * Races are simulated with stale model instances — see ConcurrentTestCase.
 */
class DispositionRaceTest extends ConcurrentTestCase
{
    #[Test]
    public function finding_dismiss_cannot_overwrite_a_case_created_committed_by_another_path(): void
    {
        $finding = ComplianceFinding::factory()->create();
        $stale = $this->staleCopy($finding);

        // Path A wins: case created from the canonical row.
        $finding->markCaseCreated();

        // Path B arrives with the pre-A state and must lose.
        $this->expectException(CaseManagementException::class);

        try {
            $stale->dismiss('late dismiss');
        } finally {
            $this->assertSame(FindingStatus::CaseCreated, $finding->fresh()->status);
        }
    }

    #[Test]
    public function finding_mark_case_created_cannot_overwrite_a_committed_dismiss(): void
    {
        $finding = ComplianceFinding::factory()->create();
        $stale = $this->staleCopy($finding);

        $finding->dismiss('false positive');

        $this->expectException(CaseManagementException::class);

        try {
            $stale->markCaseCreated();
        } finally {
            $this->assertSame(FindingStatus::Dismissed, $finding->fresh()->status);
        }
    }

    #[Test]
    public function edd_record_update_cannot_rewind_an_approved_record_to_pending_review(): void
    {
        $customer = Customer::factory()->create();
        $record = EnhancedDiligenceRecord::factory()->create([
            'customer_id' => $customer->id,
            'status' => EddStatus::PendingReview,
        ]);
        $stale = EnhancedDiligenceRecord::findOrFail($record->id);
        $service = app(EddService::class);
        $reviewer = User::factory()->create();

        // Path A wins: the record is approved.
        $service->approve($record, $reviewer);

        // Path B's late field update must not knock it back to PendingReview.
        $service->updateEddRecord($stale, ['purpose_of_transaction' => 'Updated purpose']);

        $this->assertSame(EddStatus::Approved, EnhancedDiligenceRecord::findOrFail($record->id)->status);
    }

    #[Test]
    public function edd_submit_for_review_on_an_approved_record_throws_instead_of_rewinding(): void
    {
        $customer = Customer::factory()->create();
        $record = EnhancedDiligenceRecord::factory()->create([
            'customer_id' => $customer->id,
            'status' => EddStatus::PendingReview,
            'risk_level' => 'medium',
            'source_of_funds' => 'Salary',
            'purpose_of_transaction' => 'Investment',
        ]);
        $stale = EnhancedDiligenceRecord::findOrFail($record->id);
        $service = app(EddService::class);
        $reviewer = User::factory()->create();

        $service->approve($record, $reviewer);

        $this->expectException(EddValidationException::class);

        try {
            $service->submitForReview($stale);
        } finally {
            $this->assertSame(EddStatus::Approved, EnhancedDiligenceRecord::findOrFail($record->id)->status);
        }
    }

    #[Test]
    public function risk_profile_lock_preserves_a_score_committed_after_the_instance_was_read(): void
    {
        $customer = Customer::factory()->create();
        $profile = CustomerRiskProfile::factory()->create([
            'customer_id' => $customer->id,
            'risk_score' => 30,
        ]);
        $stale = $this->staleCopy($profile);

        // A recalculation commits a new score while B still holds the old copy.
        DB::table('customer_risk_profiles')
            ->where('id', $profile->id)
            ->update(['risk_score' => 80]);

        // B's EDD lock must not write its stale risk_score back over the commit.
        $stale->lock($userId = User::factory()->create()->id, 'EDD review');

        $fresh = $profile->fresh();
        $this->assertSame(80, $fresh->risk_score);
        $this->assertSame($userId, $fresh->locked_by);
        $this->assertTrue($fresh->isLocked());
        // The inbound instance is refreshed too — callers see committed state.
        $this->assertSame(80, $stale->risk_score);
    }

    #[Test]
    public function out_of_order_transaction_created_event_does_not_rewind_last_transaction_at(): void
    {
        $customer = Customer::factory()->create(['last_transaction_at' => now()]);
        $staleCreated = now()->subDays(2);
        $transaction = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'type' => TransactionType::Buy->value,
            'status' => TransactionStatus::Completed->value,
            'created_at' => $staleCreated,
        ]);

        app(TransactionCreatedListener::class)->handle(new TransactionCreated($transaction));

        // The delayed event must not overwrite the newer timestamp.
        $this->assertTrue(
            $customer->fresh()->last_transaction_at->greaterThan($staleCreated),
            'last_transaction_at was rewound by an out-of-order event'
        );
    }

    #[Test]
    public function edd_references_are_unique_across_sequential_creates(): void
    {
        $customer = Customer::factory()->create();
        $flag = FlaggedTransaction::factory()->create(['customer_id' => $customer->id]);
        $service = app(EddService::class);

        $first = $service->createEddRecord($flag);
        $second = $service->createEddRecord($flag);

        $this->assertNotSame($first->edd_reference, $second->edd_reference);
    }

    #[Test]
    public function sanctions_hold_skips_a_transaction_that_moved_past_the_holdable_set(): void
    {
        $customer = Customer::factory()->create();
        $movedOn = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'status' => TransactionStatus::Processing->value,
        ]);
        $stillHoldable = Transaction::factory()->create([
            'customer_id' => $customer->id,
            'status' => TransactionStatus::PendingApproval->value,
        ]);

        // A stale read captured the transaction while it was still
        // holdable; between the read and the hold it moved to Processing.
        $stale = $this->staleCopy($movedOn);
        $stale->status = TransactionStatus::PendingApproval;

        $job = new class($customer->id) extends ComplianceScreeningJob
        {
            public function holdForCustomer(Customer $customer): void
            {
                $this->placePendingTransactionsForComplianceReview($customer, 'test sanction hit');
            }
        };
        $job->holdForCustomer($customer);

        // The Processing row is never rewound; the genuinely pending one is held.
        $this->assertSame(TransactionStatus::Processing, $movedOn->fresh()->status);
        $this->assertSame(TransactionStatus::PendingApproval, $stillHoldable->fresh()->status);
        $this->assertDatabaseHas('flagged_transactions', [
            'transaction_id' => $stillHoldable->id,
            'status' => 'open',
        ]);
    }
}
