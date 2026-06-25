<?php

namespace Tests\Unit\Models\Relationships;

use App\Models\Compliance\ComplianceCase;
use App\Models\Compliance\ComplianceCaseDocument;
use App\Models\Compliance\ComplianceCaseNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserInverseRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_inverse_compliance_relationships(): void
    {
        $user = User::factory()->create();

        ComplianceCase::factory()->create(['assigned_to' => $user->id]);
        ComplianceCaseDocument::factory()->create(['uploaded_by' => $user->id]);
        ComplianceCaseNote::factory()->create(['author_id' => $user->id]);

        $this->assertCount(1, $user->assignedComplianceCases);
        $this->assertCount(1, $user->uploadedComplianceDocuments);
        $this->assertCount(1, $user->complianceCaseNotes);
    }
}
