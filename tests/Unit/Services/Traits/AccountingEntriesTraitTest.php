<?php

namespace Tests\Unit\Services\Traits;

use App\Enums\CddLevel;
use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\Accounting\TransactionAccountingService;
use App\Services\Audit\AuditTrailHelper;
use App\Services\Traits\AccountingEntriesTrait;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class AccountingEntriesTraitTest extends TestCase
{
    use AccountingEntriesTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditTrailHelper = $this->createMock(AuditTrailHelper::class);
        $this->transactionAccountingService = $this->createMock(TransactionAccountingService::class);
    }

    public function test_create_accounting_entries_defers_enhanced_cdd_with_logging(): void
    {
        /** @var AuditTrailHelper&MockObject $mockAudit */
        $mockAudit = $this->auditTrailHelper;
        $mockAudit->expects($this->once())
            ->method('recordTransaction')
            ->with(
                $this->equalTo(1),
                'journal_entries_deferred',
                $this->callback(fn ($ctx) => $ctx['cdd_level'] === 'Enhanced')
            );

        /** @var TransactionAccountingService&MockObject $mockTransactionAccounting */
        $mockTransactionAccounting = $this->transactionAccountingService;
        $mockTransactionAccounting->expects($this->never())
            ->method('createImmediateAccountingEntries');

        $transaction = new Transaction;
        $transaction->id = 1;
        $transaction->cdd_level = CddLevel::Enhanced;
        $transaction->status = TransactionStatus::PendingApproval;

        $this->createAccountingEntries($transaction, '127.0.0.1', null, true);
    }

    public function test_create_accounting_entries_defers_enhanced_cdd_without_logging(): void
    {
        /** @var AuditTrailHelper&MockObject $mockAudit */
        $mockAudit = $this->auditTrailHelper;
        $mockAudit->expects($this->never())
            ->method('recordTransaction');

        /** @var TransactionAccountingService&MockObject $mockTransactionAccounting */
        $mockTransactionAccounting = $this->transactionAccountingService;
        $mockTransactionAccounting->expects($this->never())
            ->method('createImmediateAccountingEntries');

        $transaction = new Transaction;
        $transaction->cdd_level = CddLevel::Enhanced;
        $transaction->status = TransactionStatus::PendingApproval;

        $this->createAccountingEntries($transaction, '127.0.0.1', null, false);
    }

    public function test_create_accounting_entries_calls_service_for_completed_transaction(): void
    {
        /** @var AuditTrailHelper&MockObject $mockAudit */
        $mockAudit = $this->auditTrailHelper;
        $mockAudit->expects($this->never())
            ->method('recordTransaction');

        /** @var TransactionAccountingService&MockObject $mockTransactionAccounting */
        $mockTransactionAccounting = $this->transactionAccountingService;
        $mockTransactionAccounting->expects($this->once())
            ->method('createImmediateAccountingEntries')
            ->with($this->isInstanceOf(Transaction::class));

        $transaction = new Transaction;
        $transaction->cdd_level = CddLevel::Standard;
        $transaction->status = TransactionStatus::Completed;

        $this->createAccountingEntries($transaction, '127.0.0.1', null);
    }
}
