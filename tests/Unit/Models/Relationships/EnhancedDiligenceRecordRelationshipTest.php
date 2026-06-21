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
        $customer = Customer::factory()->create();
        $flaggedTransaction = FlaggedTransaction::factory()->create();
        $template = EddQuestionnaireTemplate::factory()->create();
        $reviewer = User::factory()->create();
        $approver = User::factory()->create();
        $questionnaireCompleter = User::factory()->create();

        $record = EnhancedDiligenceRecord::factory()->create([
            'customer_id' => $customer->id,
            'flagged_transaction_id' => $flaggedTransaction->id,
            'edd_template_id' => $template->id,
            'reviewed_by' => $reviewer->id,
            'approved_by' => $approver->id,
            'questionnaire_completed_by' => $questionnaireCompleter->id,
        ]);

        $this->assertTrue($record->customer->is($customer));
        $this->assertTrue($record->flaggedTransaction->is($flaggedTransaction));
        $this->assertTrue($record->template->is($template));
        $this->assertTrue($record->reviewer->is($reviewer));
        $this->assertTrue($record->approvedBy->is($approver));
        $this->assertTrue($record->questionnaireCompletedBy->is($questionnaireCompleter));
    }
}
