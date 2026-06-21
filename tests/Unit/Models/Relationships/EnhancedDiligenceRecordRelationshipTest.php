<?php

namespace Tests\Unit\Models\Relationships;

use App\Models\Compliance\EddQuestionnaireTemplate;
use App\Models\Customer;
use App\Models\EnhancedDiligenceRecord;
use App\Models\FlaggedTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnhancedDiligenceRecordRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_belongs_to_related_entities(): void
    {
        $record = EnhancedDiligenceRecord::factory()->create([
            'customer_id' => Customer::factory(),
            'flagged_transaction_id' => FlaggedTransaction::factory(),
            'edd_template_id' => EddQuestionnaireTemplate::factory(),
            'reviewed_by' => User::factory(),
            'approved_by' => User::factory(),
            'questionnaire_completed_by' => User::factory(),
        ]);

        $this->assertInstanceOf(Customer::class, $record->customer);
        $this->assertInstanceOf(FlaggedTransaction::class, $record->flaggedTransaction);
        $this->assertInstanceOf(EddQuestionnaireTemplate::class, $record->template);
        $this->assertInstanceOf(User::class, $record->reviewer);
        $this->assertInstanceOf(User::class, $record->approvedBy);
        $this->assertInstanceOf(User::class, $record->questionnaireCompletedBy);
    }
}
