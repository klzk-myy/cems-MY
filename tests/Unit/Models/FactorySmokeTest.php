<?php

namespace Tests\Unit\Models;

use App\Models\AccountLedger;
use App\Models\AmlRule;
use App\Models\BackupLog;
use App\Models\BankReconciliation;
use App\Models\BranchClosureWorkflow;
use App\Models\Compliance\ComplianceCaseDocument;
use App\Models\Compliance\ComplianceCaseLink;
use App\Models\Compliance\CustomerBehavioralBaseline;
use App\Models\Compliance\CustomerRiskProfile;
use App\Models\Compliance\EddDocumentRequest;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\DeviceComputations;
use App\Models\EddTemplate;
use App\Models\ExchangeRateHistory;
use App\Models\HighRiskCountry;
use App\Models\MfaRecoveryCode;
use App\Models\PepApprovalRequest;
use App\Models\RevaluationEntry;
use App\Models\SanctionImportLog;
use App\Models\SanctionsAnalysis;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\SystemLog;
use App\Models\ThresholdAudit;
use App\Models\TransactionImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactorySmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_factories_can_create_records(): void
    {
        $this->assertInstanceOf(AccountLedger::class, AccountLedger::factory()->create());
        $this->assertInstanceOf(AmlRule::class, AmlRule::factory()->create());
        $this->assertInstanceOf(BackupLog::class, BackupLog::factory()->create());
        $this->assertInstanceOf(BankReconciliation::class, BankReconciliation::factory()->create());
        $this->assertInstanceOf(BranchClosureWorkflow::class, BranchClosureWorkflow::factory()->create());
        $this->assertInstanceOf(ComplianceCaseDocument::class, ComplianceCaseDocument::factory()->create());
        $this->assertInstanceOf(ComplianceCaseLink::class, ComplianceCaseLink::factory()->create());
        $this->assertInstanceOf(CostCenter::class, CostCenter::factory()->create());
        $this->assertInstanceOf(CustomerBehavioralBaseline::class, CustomerBehavioralBaseline::factory()->create());
        $this->assertInstanceOf(CustomerRiskProfile::class, CustomerRiskProfile::factory()->create());
        $this->assertInstanceOf(Department::class, Department::factory()->create());
        $this->assertInstanceOf(DeviceComputations::class, DeviceComputations::factory()->create());
        $this->assertInstanceOf(EddDocumentRequest::class, EddDocumentRequest::factory()->create());
        $this->assertInstanceOf(EddTemplate::class, EddTemplate::factory()->create());
        $this->assertInstanceOf(ExchangeRateHistory::class, ExchangeRateHistory::factory()->create());
        $this->assertInstanceOf(HighRiskCountry::class, HighRiskCountry::factory()->create());
        $this->assertInstanceOf(MfaRecoveryCode::class, MfaRecoveryCode::factory()->create());
        $this->assertInstanceOf(PepApprovalRequest::class, PepApprovalRequest::factory()->create());
        $this->assertInstanceOf(RevaluationEntry::class, RevaluationEntry::factory()->create());
        $this->assertInstanceOf(SanctionImportLog::class, SanctionImportLog::factory()->create());
        $this->assertInstanceOf(SanctionsAnalysis::class, SanctionsAnalysis::factory()->create());
        $this->assertInstanceOf(StockTransfer::class, StockTransfer::factory()->create());
        $this->assertInstanceOf(StockTransferItem::class, StockTransferItem::factory()->create());
        $this->assertInstanceOf(SystemLog::class, SystemLog::factory()->create());
        $this->assertInstanceOf(ThresholdAudit::class, ThresholdAudit::factory()->create());
        $this->assertInstanceOf(TransactionImport::class, TransactionImport::factory()->create());
    }
}
