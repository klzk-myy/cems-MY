<?php

namespace App\Http\Controllers\Transaction;

use App\Actions\Transaction\ApproveTransactionAction;
use App\Enums\Permission;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\SelfApprovalException;
use App\Http\Concerns\HandlesControllerErrors;
use App\Http\Controllers\Concerns\AuthorizesBranchResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmTransactionApprovalRequest;
use App\Models\Transaction;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\AuditService;
use App\Services\Compliance\ComplianceService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Transaction\TransactionApprovalService;
use App\Services\Transaction\TransactionConfirmationService;
use App\Services\Transaction\TransactionMonitoringService;
use App\Services\Transaction\TransactionStateMachineFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionApprovalController extends Controller
{
    use AuthorizesBranchResource, HandlesControllerErrors;

    public function __construct(
        protected ApproveTransactionAction $approveAction,
        protected TransactionApprovalService $approvalService,
        protected CurrencyPositionService $positionService,
        protected ComplianceService $complianceService,
        protected TransactionMonitoringService $monitoringService,
        protected MathService $mathService,
        protected AccountingService $accountingService,
        protected AuditService $auditService,
        protected ThresholdService $thresholdService,
        protected TransactionConfirmationService $confirmationService,
        protected TransactionStateMachineFactory $stateMachineFactory
    ) {}

    /**
     * Approve a pending transaction for the teller's branch.
     *
     * Only managers and admins may approve transactions. Managers are restricted
     * to transactions within their own branch. The approval delegates to the
     * approval service, which handles status transitions, balance updates,
     * accounting entries, compliance monitoring, and audit logging.
     */
    public function approve(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('approve', $transaction);

        $result = $this->approveAction->execute($transaction, (int) auth()->id(), $request->ip());

        if (! $result->ok) {
            return back()->with('error', $result->message);
        }

        return redirect()->route('transactions.show', $transaction)
            ->with('success', $result->message);
    }

    /**
     * Reject a pending transaction for the teller's branch.
     *
     * Only managers and admins may reject transactions. Managers are restricted
     * to transactions within their own branch.
     */
    public function reject(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('reject', $transaction);

        try {
            if (! $this->approvalService->reject($transaction, (int) auth()->id(), $request->input('reason', 'Rejected by approver'))) {
                return back()->with('error', 'Transaction cannot be rejected from its current status.');
            }

            return redirect()->route('transactions.show', $transaction)
                ->with('warning', 'Transaction has been rejected.');
        } catch (SelfApprovalException $e) {
            return back()->with('error', 'You cannot reject your own transaction. Segregation of duties requires a different approver.');
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', 'The transaction is not eligible for rejection in its current state.');
        } catch (\Throwable $e) {
            return $this->handleExceptionWeb($e, 'Transaction rejection failed', 'Rejection failed due to a system error. Please contact support.', [
                'transaction_id' => $transaction->id,
            ]);
        }
    }

    /**
     * Clear a compliance hold on a pending transaction.
     *
     * Compliance officers clear holds after review. Clearing records who and
     * when; the transaction then follows the normal approval path.
     */
    public function clearHold(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('clearHold', $transaction);

        try {
            $this->approvalService->clearHold($transaction, (int) auth()->id());

            return redirect()->route('transactions.show', $transaction)
                ->with('success', 'Compliance hold cleared. Transaction may now proceed through approval.');
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return $this->handleExceptionWeb($e, 'Compliance hold clearance failed', 'Hold clearance failed. Please try again.', [
                'transaction_id' => $transaction->id,
            ]);
        }
    }

    /**
     * Show the confirmation page for large transactions.
     *
     * Transactions with an amount greater than or equal to the configured
     * threshold require manager confirmation before final approval.
     */
    public function showConfirm(Transaction $transaction): View|RedirectResponse
    {
        if (! $this->confirmationService->requiresConfirmation($transaction)) {
            return redirect()->route('transactions.show', $transaction)
                ->with('error', 'This transaction does not require confirmation.');
        }

        $confirmation = $this->confirmationService->requestConfirmation($transaction, (int) auth()->id());

        $transaction->load(['customer', 'user']);

        return view('transactions.confirm', compact('transaction', 'confirmation'));
    }

    /**
     * Process transaction confirmation for a large transaction.
     *
     * Managers confirm or reject large transactions. Self-confirmation is
     * prohibited to maintain segregation of duties for AML/CFT compliance.
     */
    public function confirm(ConfirmTransactionApprovalRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->requirePermission(Permission::ApproveTransactions);

        if (! $this->requiresConfirmation($transaction)) {
            return redirect()->route('transactions.show', $transaction)
                ->with('error', 'This transaction does not require confirmation.');
        }

        $confirmation = $this->confirmationService->pendingConfirmationFor($transaction);

        if (! $confirmation) {
            return redirect()->route('transactions.show', $transaction)
                ->with('error', 'No pending confirmation found. If one expired, please request a new confirmation.');
        }

        if ($response = $this->ensureNotSelfConfirmation($transaction)) {
            return $response;
        }

        $validated = $request->validated();

        try {
            $result = $this->confirmationService->confirm($confirmation, $validated, (int) auth()->id());

            return redirect()->route('transactions.show', $transaction)
                ->with($result['success'] ? 'success' : 'error', $result['message']);

        } catch (\Throwable $e) {
            return $this->handleExceptionWeb($e, 'Transaction confirmation failed', 'Confirmation failed. Please try again.', [
                'confirmation_id' => $confirmation->id,
                'transaction_id' => $confirmation->transaction_id,
            ]);
        }
    }

    /**
     * Determine whether the transaction requires manager confirmation.
     *
     * A transaction requires confirmation when its local-currency amount is
     * greater than or equal to the configured threshold.
     */
    protected function requiresConfirmation(Transaction $transaction): bool
    {
        return $this->confirmationService->requiresConfirmation($transaction);
    }

    /**
     * Ensure the user is not confirming a transaction they created.
     *
     * Segregation of duties requires a different approver for AML/CFT compliance.
     */
    private function ensureNotSelfConfirmation(Transaction $transaction): ?RedirectResponse
    {
        if ($transaction->user_id === auth()->id()) {
            return redirect()->route('transactions.show', $transaction)
                ->with('error', 'You cannot confirm your own transaction. Segregation of duties requires a different approver.');
        }

        return null;
    }
}
