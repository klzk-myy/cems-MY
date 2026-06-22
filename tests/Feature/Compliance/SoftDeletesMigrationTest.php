<?php

namespace Tests\Feature\Compliance;

use App\Models\Alert;
use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\ComplianceCaseDocument;
use App\Models\Compliance\ComplianceCaseLink;
use App\Models\Compliance\ComplianceCaseNote;
use App\Models\Compliance\ComplianceFinding;
use App\Models\FlaggedTransaction;
use App\Models\RiskScoreSnapshot;
use App\Models\SanctionEntry;
use App\Models\SanctionList;
use App\Models\ScreeningResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SoftDeletesMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function migration_adds_deleted_at_column_to_compliance_tables(): void
    {
        // The migration should have run via RefreshDatabase
        // We verify the column exists by checking schema
        $tables = [
            'compliance_cases',
            'compliance_findings',
            'alerts',
            'flagged_transactions',
            'sanction_entries',
            'sanction_lists',
            'screening_results',
            'risk_score_snapshots',
            'compliance_case_documents',
            'compliance_case_notes',
            'compliance_case_links',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(
                $this->hasColumn($table, 'deleted_at'),
                "Table '{$table}' should have 'deleted_at' column after migration"
            );
        }
    }

    #[DataProvider('softDeleteModelsProvider')]
    #[Test]
    public function each_compliance_model_supports_soft_deletes(string $modelClass, string $table): void
    {
        // Create a record
        $model = $modelClass::factory()->create();

        // Verify it exists in database
        $this->assertDatabaseHas($table, ['id' => $model->id]);

        // Soft delete
        $model->delete();

        // Should set deleted_at
        $this->assertNotNull($model->fresh()->deleted_at);

        // Should be excluded from default queries (where deleted_at is null)
        $this->assertDatabaseMissing($table, ['id' => $model->id, 'deleted_at' => null]);
        $this->assertFalse($modelClass::where('id', $model->id)->exists());

        // Should appear in withTrashed
        $this->assertTrue($modelClass::withTrashed()->where('id', $model->id)->exists());

        // Should appear in onlyTrashed
        $this->assertTrue($modelClass::onlyTrashed()->where('id', $model->id)->exists());

        // Force delete should remove permanently
        $model->forceDelete();
        $this->assertDatabaseMissing($table, ['id' => $model->id]);
    }

    public static function softDeleteModelsProvider(): array
    {
        return [
            [ComplianceCase::class, 'compliance_cases'],
            [ComplianceFinding::class, 'compliance_findings'],
            [Alert::class, 'alerts'],
            [FlaggedTransaction::class, 'flagged_transactions'],
            [SanctionEntry::class, 'sanction_entries'],
            [SanctionList::class, 'sanction_lists'],
            [ScreeningResult::class, 'screening_results'],
            [RiskScoreSnapshot::class, 'risk_score_snapshots'],
            [ComplianceCaseDocument::class, 'compliance_case_documents'],
            [ComplianceCaseNote::class, 'compliance_case_notes'],
            [ComplianceCaseLink::class, 'compliance_case_links'],
        ];
    }

    /**
     * Check if a table has a specific column.
     */
    private function hasColumn(string $table, string $column): bool
    {
        return \Schema::hasColumn($table, $column);
    }
}
