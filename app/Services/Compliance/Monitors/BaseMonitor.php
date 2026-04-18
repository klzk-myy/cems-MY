<?php

namespace App\Services\Compliance\Monitors;

use App\Enums\FindingSeverity;
use App\Enums\FindingStatus;
use App\Enums\FindingType;
use App\Models\Compliance\ComplianceFinding;
use App\Services\MathService;

/**
 * Abstract base class for compliance monitors.
 *
 * All monitors should extend this class and implement the run() and getFindingType() methods.
 */
abstract class BaseMonitor
{
    protected MathService $math;

    public function __construct(MathService $math)
    {
        $this->math = $math;
    }

    /**
     * Run the monitor and return findings as arrays.
     *
     * @return array Array of finding data arrays
     */
    abstract public function run(): array;

    /**
     * Return the FindingType for this monitor.
     */
    abstract protected function getFindingType(): FindingType;

    /**
     * Return default severity (can be overridden).
     */
    protected function getDefaultSeverity(): FindingSeverity
    {
        return $this->getFindingType()->defaultSeverity();
    }

    /**
     * Create a finding data array (not yet saved).
     */
    protected function createFinding(
        FindingType $type,
        FindingSeverity $severity,
        string $subjectType,
        int $subjectId,
        array $details
    ): array {
        return [
            'finding_type' => $type->value,
            'severity' => $severity->value,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'details' => $details,
            'status' => FindingStatus::New->value,
            'generated_at' => now(),
        ];
    }

    /**
     * Store a finding in the database.
     */
    protected function storeFinding(array $findingData): ComplianceFinding
    {
        return ComplianceFinding::create($findingData);
    }

    /**
     * Execute: run monitor and store all findings.
     *
     * @return array Array of stored ComplianceFinding models
     */
    public function execute(): array
    {
        $findings = $this->run();
        $stored = [];
        foreach ($findings as $finding) {
            $stored[] = $this->storeFinding($finding);
        }

        return $stored;
    }
}
