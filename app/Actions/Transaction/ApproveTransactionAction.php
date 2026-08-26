<?php

namespace App\Actions\Transaction;

use App\Exceptions\Domain\DuplicateTransactionException;
use App\Exceptions\Domain\InsufficientStockException;
use App\Exceptions\Domain\SelfApprovalException;
use App\Exceptions\Domain\TransactionConfirmationRequiredException;
use App\Models\Transaction;
use App\Services\Transaction\TransactionApprovalService;
use Illuminate\Support\Facades\Log;

class ApproveTransactionAction
{
    public function __construct(
        protected TransactionApprovalService $approvalService
    ) {}

    public function execute(Transaction $transaction, int $approverId, string $ip): TransactionApprovalResult
    {
        try {
            $this->approvalService->validateApprovalEligibility($transaction, $approverId);

            $result = $this->approvalService->approve(
                $transaction,
                $approverId,
                $ip
            );

            if (! $result->success) {
                return TransactionApprovalResult::error($result->message);
            }

            return TransactionApprovalResult::success(
                message: $result->message,
                transaction: $result->transaction
            );
        } catch (SelfApprovalException) {
            return TransactionApprovalResult::error(
                'You cannot approve your own transaction. Segregation of duties requires a different approver.'
            );
        } catch (TransactionConfirmationRequiredException $e) {
            return TransactionApprovalResult::error(
                'Manager confirmation is required before this transaction can be approved.'
            );
        } catch (InsufficientStockException) {
            return TransactionApprovalResult::error('Insufficient stock available to complete this transaction.');
        } catch (DuplicateTransactionException) {
            return TransactionApprovalResult::error('This transaction appears to be a duplicate. Please verify and try again.');
        } catch (\InvalidArgumentException) {
            return TransactionApprovalResult::error('The transaction is not eligible for approval in its current state.');
        } catch (\RuntimeException) {
            return TransactionApprovalResult::error('Transaction approval failed due to a system error. Please contact support.');
        } catch (\Exception $e) {
            Log::error('Transaction approval failed', [
                'transaction_id' => $transaction->id,
                'user_id' => $approverId,
                'error' => $e->getMessage(),
            ]);

            return TransactionApprovalResult::error('Approval failed due to a system error. Please contact support.');
        }
    }
}
