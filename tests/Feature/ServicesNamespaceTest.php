<?php

namespace Tests\Feature;

use App\Services\Branch\BranchClosingService;
use App\Services\Branch\BranchPoolService;
use App\Services\Branch\BranchService;
use App\Services\Branch\CounterHandoverService;
use App\Services\Branch\CounterOpeningWorkflowService;
use App\Services\Branch\CounterService;
use App\Services\Branch\EmergencyCounterService;
use App\Services\Branch\TellerAllocationService;
use App\Services\Branch\TillService;
use App\Services\Customer\CustomerRelationService;
use App\Services\Customer\CustomerService;
use App\Services\Customer\UserService;
use App\Services\DTOs\PreValidationResult;
use App\Services\DTOs\SanctionCheckResult;
use App\Services\Reporting\CustomerReportService;
use App\Services\Reporting\ExportService;
use App\Services\Reporting\FinancialRatioService;
use App\Services\Reporting\ReportingService;
use App\Services\Reporting\ReportSchedulingService;
use App\Services\System\BackupService;
use App\Services\System\CacheMonitoringService;
use App\Services\System\CacheOptimizationService;
use App\Services\System\CacheTagsService;
use App\Services\System\DocumentStorageService;
use App\Services\System\EncryptionService;
use App\Services\System\LogRotationService;
use App\Services\System\MathService;
use App\Services\System\MfaService;
use App\Services\System\PerformanceBaselineService;
use App\Services\System\QueryLoggingService;
use App\Services\System\QueryOptimizerService;
use App\Services\System\RateLimitService;
use App\Services\System\SetupService;
use App\Services\System\SystemAlertService;
use App\Services\System\SystemHealthService;
use App\Services\System\TestRunnerService;
use App\Services\System\WizardSessionService;
use App\Services\Transaction\RateApiService;
use App\Services\Transaction\RateManagementService;
use App\Services\Transaction\StockReleaseService;
use App\Services\Transaction\StockTransferService;
use App\Services\Transaction\TransactionApprovalService;
use App\Services\Transaction\TransactionCancellationService;
use App\Services\Transaction\TransactionErrorHandler;
use App\Services\Transaction\TransactionImportService;
use App\Services\Transaction\TransactionMonitoringService;
use App\Services\Transaction\TransactionRecoveryService;
use App\Services\Transaction\TransactionReversalService;
use App\Services\Transaction\TransactionService;
use App\Services\Transaction\TransactionStateMachine;
use App\Services\Transaction\TransactionValidationService;
use Tests\TestCase;

class ServicesNamespaceTest extends TestCase
{
    public function test_transaction_services_are_in_transaction_namespace(): void
    {
        $this->assertSame('App\Services\Transaction', (new \ReflectionClass(TransactionService::class))->getNamespaceName());
        $this->assertSame('App\Services\Transaction', (new \ReflectionClass(TransactionStateMachine::class))->getNamespaceName());
        $this->assertSame('App\Services\Transaction', (new \ReflectionClass(TransactionValidationService::class))->getNamespaceName());
        $this->assertSame('App\Services\Transaction', (new \ReflectionClass(TransactionApprovalService::class))->getNamespaceName());
        $this->assertSame('App\Services\Transaction', (new \ReflectionClass(TransactionCancellationService::class))->getNamespaceName());
        $this->assertSame('App\Services\Transaction', (new \ReflectionClass(TransactionReversalService::class))->getNamespaceName());
        $this->assertSame('App\Services\Transaction', (new \ReflectionClass(TransactionRecoveryService::class))->getNamespaceName());
        $this->assertSame('App\Services\Transaction', (new \ReflectionClass(TransactionImportService::class))->getNamespaceName());
        $this->assertSame('App\Services\Transaction', (new \ReflectionClass(TransactionErrorHandler::class))->getNamespaceName());
        $this->assertSame('App\Services\Transaction', (new \ReflectionClass(TransactionMonitoringService::class))->getNamespaceName());
        $this->assertSame('App\Services\Transaction', (new \ReflectionClass(RateApiService::class))->getNamespaceName());
        $this->assertSame('App\Services\Transaction', (new \ReflectionClass(RateManagementService::class))->getNamespaceName());
        $this->assertSame('App\Services\Transaction', (new \ReflectionClass(StockReleaseService::class))->getNamespaceName());
        $this->assertSame('App\Services\Transaction', (new \ReflectionClass(StockTransferService::class))->getNamespaceName());
    }

    public function test_branch_services_are_in_branch_namespace(): void
    {
        $this->assertSame('App\Services\Branch', (new \ReflectionClass(BranchService::class))->getNamespaceName());
        $this->assertSame('App\Services\Branch', (new \ReflectionClass(BranchClosingService::class))->getNamespaceName());
        $this->assertSame('App\Services\Branch', (new \ReflectionClass(BranchPoolService::class))->getNamespaceName());
        $this->assertSame('App\Services\Branch', (new \ReflectionClass(CounterService::class))->getNamespaceName());
        $this->assertSame('App\Services\Branch', (new \ReflectionClass(CounterHandoverService::class))->getNamespaceName());
        $this->assertSame('App\Services\Branch', (new \ReflectionClass(CounterOpeningWorkflowService::class))->getNamespaceName());
        $this->assertSame('App\Services\Branch', (new \ReflectionClass(EmergencyCounterService::class))->getNamespaceName());
        $this->assertSame('App\Services\Branch', (new \ReflectionClass(TellerAllocationService::class))->getNamespaceName());
        $this->assertSame('App\Services\Branch', (new \ReflectionClass(TillService::class))->getNamespaceName());
    }

    public function test_transaction_services_files_exist_in_subdirectory(): void
    {
        $transactionDir = base_path('app/Services/Transaction');
        $this->assertDirectoryExists($transactionDir);
        $this->assertFileExists($transactionDir.'/TransactionService.php');
        $this->assertFileExists($transactionDir.'/TransactionStateMachine.php');
        $this->assertFileExists($transactionDir.'/TransactionValidationService.php');
        $this->assertFileExists($transactionDir.'/TransactionApprovalService.php');
        $this->assertFileExists($transactionDir.'/TransactionCancellationService.php');
        $this->assertFileExists($transactionDir.'/TransactionReversalService.php');
        $this->assertFileExists($transactionDir.'/TransactionRecoveryService.php');
        $this->assertFileExists($transactionDir.'/TransactionImportService.php');
        $this->assertFileExists($transactionDir.'/TransactionErrorHandler.php');
        $this->assertFileExists($transactionDir.'/TransactionMonitoringService.php');
        $this->assertFileExists($transactionDir.'/RateApiService.php');
        $this->assertFileExists($transactionDir.'/RateManagementService.php');
        $this->assertFileExists($transactionDir.'/StockReleaseService.php');
        $this->assertFileExists($transactionDir.'/StockTransferService.php');
    }

    public function test_branch_services_files_exist_in_subdirectory(): void
    {
        $branchDir = base_path('app/Services/Branch');
        $this->assertDirectoryExists($branchDir);
        $this->assertFileExists($branchDir.'/BranchService.php');
        $this->assertFileExists($branchDir.'/BranchClosingService.php');
        $this->assertFileExists($branchDir.'/BranchPoolService.php');
        $this->assertFileExists($branchDir.'/CounterService.php');
        $this->assertFileExists($branchDir.'/CounterHandoverService.php');
        $this->assertFileExists($branchDir.'/CounterOpeningWorkflowService.php');
        $this->assertFileExists($branchDir.'/EmergencyCounterService.php');
        $this->assertFileExists($branchDir.'/TellerAllocationService.php');
        $this->assertFileExists($branchDir.'/TillService.php');
    }

    public function test_old_files_no_longer_exist_in_services_root(): void
    {
        $servicesDir = base_path('app/Services');

        foreach (['TransactionService.php', 'TransactionStateMachine.php', 'TransactionValidationService.php',
            'TransactionApprovalService.php', 'TransactionCancellationService.php', 'TransactionReversalService.php',
            'TransactionRecoveryService.php', 'TransactionImportService.php', 'TransactionErrorHandler.php',
            'TransactionMonitoringService.php', 'RateApiService.php', 'RateManagementService.php',
            'StockReleaseService.php', 'StockTransferService.php', 'BranchService.php', 'BranchClosingService.php',
            'BranchPoolService.php', 'CounterService.php', 'CounterHandoverService.php',
            'CounterOpeningWorkflowService.php', 'EmergencyCounterService.php', 'TellerAllocationService.php',
            'TillService.php'] as $file) {
            $this->assertFileDoesNotExist($servicesDir.'/'.$file);
        }
    }

    public function test_system_services_are_in_system_namespace(): void
    {
        $this->assertSame('App\Services\System', (new \ReflectionClass(MathService::class))->getNamespaceName());
        $this->assertSame('App\Services\System', (new \ReflectionClass(EncryptionService::class))->getNamespaceName());
        $this->assertSame('App\Services\System', (new \ReflectionClass(CacheOptimizationService::class))->getNamespaceName());
        $this->assertSame('App\Services\System', (new \ReflectionClass(CacheMonitoringService::class))->getNamespaceName());
        $this->assertSame('App\Services\System', (new \ReflectionClass(CacheTagsService::class))->getNamespaceName());
        $this->assertSame('App\Services\System', (new \ReflectionClass(QueryLoggingService::class))->getNamespaceName());
        $this->assertSame('App\Services\System', (new \ReflectionClass(QueryOptimizerService::class))->getNamespaceName());
        $this->assertSame('App\Services\System', (new \ReflectionClass(RateLimitService::class))->getNamespaceName());
        $this->assertSame('App\Services\System', (new \ReflectionClass(PerformanceBaselineService::class))->getNamespaceName());
        $this->assertSame('App\Services\System', (new \ReflectionClass(BackupService::class))->getNamespaceName());
        $this->assertSame('App\Services\System', (new \ReflectionClass(DocumentStorageService::class))->getNamespaceName());
        $this->assertSame('App\Services\System', (new \ReflectionClass(LogRotationService::class))->getNamespaceName());
        $this->assertSame('App\Services\System', (new \ReflectionClass(SetupService::class))->getNamespaceName());
        $this->assertSame('App\Services\System', (new \ReflectionClass(SystemAlertService::class))->getNamespaceName());
        $this->assertSame('App\Services\System', (new \ReflectionClass(SystemHealthService::class))->getNamespaceName());
        $this->assertSame('App\Services\System', (new \ReflectionClass(TestRunnerService::class))->getNamespaceName());
        $this->assertSame('App\Services\System', (new \ReflectionClass(WizardSessionService::class))->getNamespaceName());
        $this->assertSame('App\Services\System', (new \ReflectionClass(MfaService::class))->getNamespaceName());
    }

    public function test_system_services_files_exist_in_subdirectory(): void
    {
        $systemDir = base_path('app/Services/System');
        $this->assertDirectoryExists($systemDir);
        $this->assertFileExists($systemDir.'/MathService.php');
        $this->assertFileExists($systemDir.'/EncryptionService.php');
        $this->assertFileExists($systemDir.'/CacheOptimizationService.php');
        $this->assertFileExists($systemDir.'/CacheMonitoringService.php');
        $this->assertFileExists($systemDir.'/CacheTagsService.php');
        $this->assertFileExists($systemDir.'/QueryLoggingService.php');
        $this->assertFileExists($systemDir.'/QueryOptimizerService.php');
        $this->assertFileExists($systemDir.'/RateLimitService.php');
        $this->assertFileExists($systemDir.'/PerformanceBaselineService.php');
        $this->assertFileExists($systemDir.'/BackupService.php');
        $this->assertFileExists($systemDir.'/DocumentStorageService.php');
        $this->assertFileExists($systemDir.'/LogRotationService.php');
        $this->assertFileExists($systemDir.'/SetupService.php');
        $this->assertFileExists($systemDir.'/SystemAlertService.php');
        $this->assertFileExists($systemDir.'/SystemHealthService.php');
        $this->assertFileExists($systemDir.'/TestRunnerService.php');
        $this->assertFileExists($systemDir.'/WizardSessionService.php');
        $this->assertFileExists($systemDir.'/MfaService.php');
    }

    public function test_dtos_are_in_dtos_namespace(): void
    {
        $this->assertSame('App\Services\DTOs', (new \ReflectionClass(PreValidationResult::class))->getNamespaceName());
        $this->assertSame('App\Services\DTOs', (new \ReflectionClass(SanctionCheckResult::class))->getNamespaceName());
    }

    public function test_dtos_files_exist_in_subdirectory(): void
    {
        $dtosDir = base_path('app/Services/DTOs');
        $this->assertDirectoryExists($dtosDir);
        $this->assertFileExists($dtosDir.'/PreValidationResult.php');
        $this->assertFileExists($dtosDir.'/SanctionCheckResult.php');
    }

    public function test_customer_services_are_in_customer_namespace(): void
    {
        $this->assertSame('App\Services\Customer', (new \ReflectionClass(CustomerService::class))->getNamespaceName());
        $this->assertSame('App\Services\Customer', (new \ReflectionClass(CustomerRelationService::class))->getNamespaceName());
        $this->assertSame('App\Services\Customer', (new \ReflectionClass(UserService::class))->getNamespaceName());
    }

    public function test_customer_services_files_exist_in_subdirectory(): void
    {
        $customerDir = base_path('app/Services/Customer');
        $this->assertDirectoryExists($customerDir);
        $this->assertFileExists($customerDir.'/CustomerService.php');
        $this->assertFileExists($customerDir.'/CustomerRelationService.php');
        $this->assertFileExists($customerDir.'/UserService.php');
    }

    public function test_reporting_services_are_in_reporting_namespace(): void
    {
        $this->assertSame('App\Services\Reporting', (new \ReflectionClass(ReportingService::class))->getNamespaceName());
        $this->assertSame('App\Services\Reporting', (new \ReflectionClass(CustomerReportService::class))->getNamespaceName());
        $this->assertSame('App\Services\Reporting', (new \ReflectionClass(ExportService::class))->getNamespaceName());
        $this->assertSame('App\Services\Reporting', (new \ReflectionClass(FinancialRatioService::class))->getNamespaceName());
        $this->assertSame('App\Services\Reporting', (new \ReflectionClass(ReportSchedulingService::class))->getNamespaceName());
    }

    public function test_reporting_services_files_exist_in_subdirectory(): void
    {
        $reportingDir = base_path('app/Services/Reporting');
        $this->assertDirectoryExists($reportingDir);
        $this->assertFileExists($reportingDir.'/ReportingService.php');
        $this->assertFileExists($reportingDir.'/CustomerReportService.php');
        $this->assertFileExists($reportingDir.'/ExportService.php');
        $this->assertFileExists($reportingDir.'/FinancialRatioService.php');
        $this->assertFileExists($reportingDir.'/ReportSchedulingService.php');
    }
}
