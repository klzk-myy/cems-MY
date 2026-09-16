<?php

namespace App\Services\Transaction\Checks;

use App\Enums\ComplianceFlagType;

/**
 * A monitoring check's verdict: the flag to persist plus an optional AML audit
 * event emitted after the flag is created.
 */
final readonly class FlagDescriptor
{
    /**
     * @param  array<string, mixed>  $auditPayload
     */
    public function __construct(
        public ComplianceFlagType $type,
        public string $reason,
        public ?string $auditEvent = null,
        public array $auditPayload = [],
    ) {}
}
