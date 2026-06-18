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
}
