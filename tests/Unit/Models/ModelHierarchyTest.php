<?php

namespace Tests\Unit\Models;

use App\Models\AccountingPeriod;
use App\Models\Alert;
use App\Models\AmlRule;
use App\Models\BackupLog;
use App\Models\BankReconciliation;
use App\Models\BaseModel;
use App\Models\Branch;
use App\Models\BranchClosureWorkflow;
use App\Models\BranchPool;
use App\Models\Budget;
use App\Models\ChartOfAccount;
use App\Models\Compliance\ComplianceCaseDocument;
use App\Models\Compliance\ComplianceCaseLink;
use App\Models\Compliance\ComplianceCaseNote;
use App\Models\Compliance\CustomerBehavioralBaseline;
use App\Models\Compliance\CustomerRiskProfile;
use App\Models\Compliance\EddQuestionnaireTemplate;
use App\Models\CostCenter;
use App\Models\Counter;
use App\Models\CounterHandover;
use App\Models\CounterSession;
use App\Models\Currency;
use App\Models\CurrencyPosition;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\CustomerNote;
use App\Models\CustomerRelation;
use App\Models\CustomerRiskHistory;
use App\Models\Department;
use App\Models\DeviceComputations;
use App\Models\EddTemplate;
use App\Models\EmergencyClosure;
use App\Models\ExchangeRate;
use App\Models\ExchangeRateHistory;
use App\Models\FiscalYear;
use App\Models\FlaggedTransaction;
use App\Models\HighRiskCountry;
use App\Models\MfaRecoveryCode;
use App\Models\PepApprovalRequest;
use App\Models\ReportGenerated;
use App\Models\ReportRun;
use App\Models\ReportSchedule;
use App\Models\RevaluationEntry;
use App\Models\RiskScoreSnapshot;
use App\Models\SanctionEntry;
use App\Models\SanctionImportLog;
use App\Models\SanctionList;
use App\Models\SanctionsAnalysis;
use App\Models\ScreeningResult;
use App\Models\StockReservation;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\SystemLog;
use App\Models\TellerAllocation;
use App\Models\ThresholdAudit;
use App\Models\TillBalance;
use App\Models\TransactionConfirmation;
use App\Models\TransactionError;
use App\Models\TransactionImport;
use App\Models\UserNotificationPreference;
use Tests\TestCase;

class ModelHierarchyTest extends TestCase
{
    public function test_branch_models_extend_base_model(): void
    {
        $models = [
            Branch::class,
            Counter::class,
            CounterSession::class,
            CounterHandover::class,
            TillBalance::class,
            TellerAllocation::class,
        ];

        foreach ($models as $model) {
            $instance = new $model;
            $this->assertInstanceOf(
                BaseModel::class,
                $instance,
                "{$model} should extend BaseModel"
            );
        }
    }

    public function test_customer_models_extend_base_model(): void
    {
        $models = [
            Customer::class,
            CustomerDocument::class,
            CustomerNote::class,
            CustomerRelation::class,
            CustomerRiskHistory::class,
            RiskScoreSnapshot::class,
        ];

        foreach ($models as $model) {
            $instance = new $model;
            $this->assertInstanceOf(
                BaseModel::class,
                $instance,
                "{$model} should extend BaseModel"
            );
        }
    }

    public function test_accounting_models_extend_base_model(): void
    {
        $models = [
            AccountingPeriod::class,
            Budget::class,
            ChartOfAccount::class,
            CostCenter::class,
            Currency::class,
            CurrencyPosition::class,
            ExchangeRate::class,
            ExchangeRateHistory::class,
            FiscalYear::class,
            RevaluationEntry::class,
        ];

        foreach ($models as $model) {
            $instance = new $model;
            $this->assertInstanceOf(
                BaseModel::class,
                $instance,
                "{$model} should extend BaseModel"
            );
        }
    }

    public function test_compliance_models_extend_base_model(): void
    {
        $models = [
            AmlRule::class,
            ComplianceCaseDocument::class,
            ComplianceCaseLink::class,
            ComplianceCaseNote::class,
            CustomerBehavioralBaseline::class,
            CustomerRiskProfile::class,
            EddQuestionnaireTemplate::class,
            EddTemplate::class,
            HighRiskCountry::class,
            PepApprovalRequest::class,
            SanctionEntry::class,
            SanctionImportLog::class,
            SanctionList::class,
            SanctionsAnalysis::class,
            ScreeningResult::class,
            ThresholdAudit::class,
        ];

        foreach ($models as $model) {
            $instance = new $model;
            $this->assertInstanceOf(
                BaseModel::class,
                $instance,
                "{$model} should extend BaseModel"
            );
        }
    }

    public function test_inventory_models_extend_base_model(): void
    {
        $models = [
            StockReservation::class,
            StockTransfer::class,
            StockTransferItem::class,
        ];

        foreach ($models as $model) {
            $instance = new $model;
            $this->assertInstanceOf(
                BaseModel::class,
                $instance,
                "{$model} should extend BaseModel"
            );
        }
    }

    public function test_transaction_models_extend_base_model(): void
    {
        $models = [
            TransactionConfirmation::class,
            TransactionError::class,
            TransactionImport::class,
            BankReconciliation::class,
        ];

        foreach ($models as $model) {
            $instance = new $model;
            $this->assertInstanceOf(
                BaseModel::class,
                $instance,
                "{$model} should extend BaseModel"
            );
        }
    }

    public function test_system_models_extend_base_model(): void
    {
        $models = [
            Alert::class,
            BackupLog::class,
            BranchClosureWorkflow::class,
            BranchPool::class,
            Department::class,
            DeviceComputations::class,
            EmergencyClosure::class,
            FlaggedTransaction::class,
            MfaRecoveryCode::class,
            ReportGenerated::class,
            ReportRun::class,
            ReportSchedule::class,
            SystemLog::class,
            UserNotificationPreference::class,
        ];

        foreach ($models as $model) {
            $instance = new $model;
            $this->assertInstanceOf(
                BaseModel::class,
                $instance,
                "{$model} should extend BaseModel"
            );
        }
    }
}
