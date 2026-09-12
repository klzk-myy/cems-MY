<?php

namespace Tests\Feature\Audit;

use App\Models\Compliance\ComplianceCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ComplianceCaseNumberTest extends TestCase
{
    use DatabaseTransactions;

    public function test_case_number_is_auto_generated_with_expected_format(): void
    {
        $case = ComplianceCase::factory()->create();

        $this->assertMatchesRegularExpression('/^CASE-\d{4}-\d{5}$/', $case->case_number);
    }

    public function test_case_numbers_are_unique_and_sequential(): void
    {
        $cases = ComplianceCase::factory()->count(5)->create();
        $numbers = $cases->pluck('case_number')->all();

        $this->assertSame($numbers, array_unique($numbers), 'Case numbers must be unique');

        $sequences = array_map(fn (string $n) => (int) substr($n, -5), $numbers);
        $sorted = $sequences;
        sort($sorted);
        $this->assertSame(range($sorted[0], $sorted[0] + count($sorted) - 1), $sorted,
            'Case numbers should be sequential with no gaps or duplicates');
    }

    public function test_case_number_does_not_reuse_soft_deleted_numbers(): void
    {
        $case = ComplianceCase::factory()->create();
        $deletedSequence = (int) substr($case->case_number, -5);
        $case->delete();

        $next = ComplianceCase::factory()->create();

        $this->assertGreaterThan(
            $deletedSequence,
            (int) substr($next->case_number, -5),
            'Soft-deleted case numbers still occupy their sequence under the unique index'
        );
    }
}
