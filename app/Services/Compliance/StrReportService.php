<?php

namespace App\Services\Compliance;

use App\Enums\ComplianceCaseStatus;
use App\Enums\StrReportStatus;
use App\Exceptions\Domain\CaseManagementException;
use App\Models\Compliance\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\FlaggedTransaction;
use App\Models\Compliance\StrReport;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * StrReportService
 *
 * Suspicious Transaction Report workflow (pd-00.md section 22): drafts an STR
 * from a closed compliance case whose aggregate flagged amount meets the
 * RM 50,000 threshold, then tracks the BNM FIED submission lifecycle
 * (Draft -> Submitted -> Acknowledged/Rejected). All monetary arithmetic is
 * BCMath string math at scale 4 to match decimal(18,4) storage.
 */
class StrReportService
{
    /**
     * pd-00 section 22 filing threshold in MYR.
     */
    public const THRESHOLD = '50000';

    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Draft an STR from a completed compliance case.
     *
     * @throws CaseManagementException when the case is not closed, the
     *                                 aggregate amount is below threshold,
     *                                 or a report already exists for the case
     */
    public function createFromCase(ComplianceCase $case, User $by): StrReport
    {
        return DB::transaction(function () use ($case, $by) {
            $lockedCase = ComplianceCase::whereKey($case->id)->lockForUpdate()->firstOrFail();

            if ($lockedCase->status !== ComplianceCaseStatus::Closed) {
                throw new CaseManagementException(
                    "STR drafts can only be created from closed cases (case {$lockedCase->case_number} is {$lockedCase->status->value})"
                );
            }

            if (StrReport::where('case_id', $lockedCase->id)->exists()) {
                throw new CaseManagementException(
                    "An STR already exists for case {$lockedCase->case_number}"
                );
            }

            return $this->persistDraft($lockedCase, $by);
        });
    }

    /**
     * Threshold-check the case and insert the draft row. Runs under the
     * caller's case-row lock so concurrent drafts serialize on the case.
     */
    private function persistDraft(ComplianceCase $case, User $by): StrReport
    {
        $triggerAmountMyr = $this->computeTriggerAmountMyr($case);

        if (! $this->meetsThreshold($triggerAmountMyr)) {
            throw new CaseManagementException(
                'Case '.$case->case_number.' aggregate MYR '.$triggerAmountMyr.' is below the '.self::THRESHOLD.' threshold'
            );
        }

        $report = StrReport::create([
            'case_id' => $case->id,
            'customer_id' => $case->customer_id,
            'trigger_amount_myr' => $triggerAmountMyr,
            'trigger_reason' => $this->buildTriggerReason($case),
            'status' => StrReportStatus::Draft,
            'created_by' => $by->id,
        ]);

        $this->auditService->logAction('str_report_drafted', 'StrReport', $report->id, [
            'user_id' => $by->id,
            'new_values' => [
                'case_id' => $case->id,
                'customer_id' => $report->customer_id,
                'trigger_amount_myr' => $triggerAmountMyr,
                'status' => StrReportStatus::Draft->value,
            ],
        ]);

        return $report;
    }

    /**
     * Lodge the draft with BNM: Draft -> Submitted.
     *
     * @throws CaseManagementException when the report is not a draft or the
     *                                 reference is already used elsewhere
     */
    public function submit(StrReport $report, string $bnmReference, ?User $by = null): StrReport
    {
        return DB::transaction(function () use ($report, $bnmReference, $by) {
            $locked = StrReport::whereKey($report->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== StrReportStatus::Draft) {
                throw new CaseManagementException(
                    "Only draft STRs can be submitted (report {$locked->id} is {$locked->status->value})"
                );
            }

            $taken = StrReport::where('bnm_reference', $bnmReference)
                ->where('id', '!=', $locked->id)
                ->exists();

            if ($taken) {
                throw new CaseManagementException("BNM reference {$bnmReference} is already recorded on another STR");
            }

            $oldStatus = $locked->status->value;

            $locked->update([
                'bnm_reference' => $bnmReference,
                'submitted_at' => now(),
                'status' => StrReportStatus::Submitted,
            ]);

            $this->auditService->logAction('str_report_submitted', 'StrReport', $locked->id, [
                'user_id' => $by?->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => [
                    'status' => StrReportStatus::Submitted->value,
                    'bnm_reference' => $bnmReference,
                    'submitted_at' => optional($locked->submitted_at)->toIso8601String(),
                ],
            ], 'WARNING');

            return $locked->fresh();
        });
    }

    /**
     * Record BNM acknowledgement: Submitted -> Acknowledged.
     *
     * @throws CaseManagementException when the report has not been submitted
     */
    public function acknowledge(StrReport $report, ?User $by = null): StrReport
    {
        return DB::transaction(function () use ($report, $by) {
            $locked = StrReport::whereKey($report->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->canTransitionTo(StrReportStatus::Acknowledged)) {
                throw new CaseManagementException(
                    "Only submitted STRs can be acknowledged (report {$locked->id} is {$locked->status->value})"
                );
            }

            $oldStatus = $locked->status->value;

            $locked->update([
                'acknowledged_at' => now(),
                'status' => StrReportStatus::Acknowledged,
            ]);

            $this->auditService->logAction('str_report_acknowledged', 'StrReport', $locked->id, [
                'user_id' => $by?->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => [
                    'status' => StrReportStatus::Acknowledged->value,
                    'acknowledged_at' => optional($locked->acknowledged_at)->toIso8601String(),
                ],
            ]);

            return $locked->fresh();
        });
    }

    /**
     * Aggregate the MYR amount behind a case's flagged transactions.
     *
     * compliance_cases stores no amount column, so the total is derived from
     * the transactions behind every flagged transaction tied to the case: the
     * primary flag plus each alert-linked flag. Missing or zero-amount links
     * contribute nothing rather than aborting the aggregation.
     */
    public function computeTriggerAmountMyr(ComplianceCase $case): string
    {
        $flagIds = Alert::where('case_id', $case->id)
            ->pluck('flagged_transaction_id')
            ->filter()
            ->unique()
            ->values();

        if ($case->primary_flag_id) {
            $flagIds->push($case->primary_flag_id);
        }

        $total = '0';

        if ($flagIds->isEmpty()) {
            return $total;
        }

        FlaggedTransaction::with('transaction:id,amount_myr')
            ->whereIn('id', $flagIds)
            ->get()
            ->each(function (FlaggedTransaction $flag) use (&$total): void {
                $amountMyr = $flag->transaction?->amount_myr;

                if ($amountMyr !== null && is_numeric($amountMyr) && bccomp($amountMyr, '0', 4) > 0) {
                    $total = bcadd($total, $amountMyr, 4);
                }
            });

        return $total;
    }

    /**
     * Whether an aggregate MYR amount meets the pd-00 section 22 threshold.
     */
    public function meetsThreshold(string $amountMyr): bool
    {
        if (! is_numeric($amountMyr)) {
            throw new \InvalidArgumentException('STR aggregate amount must be numeric.');
        }

        return bccomp($amountMyr, self::THRESHOLD, 4) >= 0;
    }

    /**
     * Auto-draft hook for case closure. Never throws: a failed STR draft must
     * never block the closure workflow. Returns the created report, or null
     * when no report is warranted (below threshold, already drafted) or the
     * draft could not be persisted (the error is logged instead of rethrown).
     *
     * WIRING (central): call this after a case is persisted as Closed in
     * CaseManagementService::resolveCase()/closeCase()/updateStatus(), e.g.:
     *
     *   try {
     *       app(\App\Services\Compliance\StrReportService::class)
     *           ->autoDraftForClosedCase($case);
     *   } catch (\Throwable $e) {
     *       \Illuminate\Support\Facades\Log::error('STR auto-draft failed', [
     *           'case_id' => $case->id, 'error' => $e->getMessage(),
     *       ]);
     *   }
     */
    public function autoDraftForClosedCase(ComplianceCase $case): ?StrReport
    {
        try {
            if ($case->status !== ComplianceCaseStatus::Closed) {
                return null;
            }

            if ($case->customer_id === null || StrReport::where('case_id', $case->id)->exists()) {
                return null;
            }

            if (! $this->meetsThreshold($this->computeTriggerAmountMyr($case))) {
                return null;
            }

            // Attribute the draft to the assigned officer; closure call sites
            // do not always carry an authenticated user (e.g. batch jobs).
            $actor = $case->assigned_to !== null ? User::find($case->assigned_to) : null;

            if ($actor === null) {
                return null;
            }

            return $this->createFromCase($case, $actor);
        } catch (\Throwable $e) {
            Log::error('STR auto-draft failed', [
                'case_id' => $case->id,
                'error' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);

            return null;
        }
    }

    /**
     * Compose the human-readable trigger reason stored on the report.
     */
    protected function buildTriggerReason(ComplianceCase $case): string
    {
        $reasons = $case->alerts()->pluck('reason')->filter()->unique()->take(3)->all();

        $parts = [
            'Case '.$case->case_number.' ('.$case->case_type->label().') closed with aggregate flagged transactions at or above RM '.number_format((float) self::THRESHOLD, 2).'.',
        ];

        if ($reasons !== []) {
            $parts[] = 'Alert reasons: '.implode('; ', $reasons).'.';
        }

        if ($case->resolution_notes) {
            $parts[] = 'Resolution notes: '.$case->resolution_notes;
        }

        return implode(' ', $parts);
    }
}
