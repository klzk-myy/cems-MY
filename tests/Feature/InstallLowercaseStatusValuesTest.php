<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The statuses:install-lowercase-values installer remaps legacy TitleCase
 * status values to lowercase_snake on existing databases. On non-MySQL
 * connections the ENUM bridge is skipped and rows are updated in place,
 * which is the path exercised here.
 */
class InstallLowercaseStatusValuesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function legacy_status_values_are_remapped_to_lowercase(): void
    {
        DB::table('compliance_findings')->insert([
            'finding_type' => 'Risk_Score_Change',
            'severity' => 'info',
            'subject_type' => 'customer',
            'subject_id' => 1,
            'status' => 'Case_Created',
            'generated_at' => now(),
        ]);

        $this->artisanCommand('statuses:install-lowercase-values')->assertSuccessful();

        $this->assertDatabaseHas('compliance_findings', ['status' => 'case_created']);
        $this->assertDatabaseMissing('compliance_findings', ['status' => 'Case_Created']);
    }

    #[Test]
    public function the_installer_is_idempotent_and_leaves_normalized_values_untouched(): void
    {
        DB::table('compliance_findings')->insert([
            'finding_type' => 'Risk_Score_Change',
            'severity' => 'info',
            'subject_type' => 'customer',
            'subject_id' => 1,
            'status' => 'Case_Created',
            'generated_at' => now(),
        ]);
        DB::table('compliance_findings')->insert([
            'finding_type' => 'Risk_Score_Change',
            'severity' => 'info',
            'subject_type' => 'customer',
            'subject_id' => 2,
            'status' => 'reviewed',
            'generated_at' => now(),
        ]);

        $this->artisanCommand('statuses:install-lowercase-values')->assertSuccessful();
        $this->artisanCommand('statuses:install-lowercase-values')->assertSuccessful();

        $this->assertDatabaseHas('compliance_findings', ['subject_id' => 1, 'status' => 'case_created']);
        $this->assertDatabaseHas('compliance_findings', ['subject_id' => 2, 'status' => 'reviewed']);
    }
}
