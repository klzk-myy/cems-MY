<?php

namespace App\Http\Controllers\Transaction;

use App\Enums\TransactionConfirmationStatus;
use App\Enums\TransactionStatus;
use App\Exceptions\Domain\SelfApprovalException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmTransactionApprovalRequest;
use App\Models\Transaction;
use App\Models\TransactionConfirmation;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\CurrencyPositionService;
use App\Services\AuditService;
use App\Services\Compliance\ComplianceService;
use App\Services\System\MathService;
use App\Services\ThresholdService;
use App\Services\Transaction\TransactionApprovalService;
use App\Services\Transaction\TransactionMonitoringService;
use App\Services\Transaction\TransactionService;
use App\Services\Transaction\TransactionStateMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class TransactionApprovalController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService,
        protected TransactionApprovalService $approvalService,
        protected CurrencyPositionService $positionService,
        protected ComplianceService $complianceService,
        protected TransactionMonitoringService $monitoringService,
        protected MathService $mathService,
        protected AccountingService $accountingService,
        protected AuditService $auditService,
        protected ThresholdService $thresholdService
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
        $this->requireManagerOrAdmin();
        $this->ensureCanApproveForBranch($transaction, auth()->user(), 'approve');

        try {
            $this->approvalService->validateApprovalEligibility($transaction, auth()->id());

            $result = $this->approvalService->approve(
                $transaction,
                auth()->id(),
                $request->ip()
            );

            if (! $result['success']) {
                return back()->with('error', $result['message']);
            }

            return redirect()->route('transactions.show', $transaction)
                ->with('success', $result['message']);

        } catch (SelfApprovalException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Transaction approval failed', [
                'transaction_id' => $transaction->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Approval failed due to a system error. Please contact support.');
        }
    }

    /**
     * Reject a pending transaction for the teller's branch.
     *
     * Only managers and admins may reject transactions. Managers are restricted
     * to transactions within their own branch.
     */
    public function reject(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->requireManagerOrAdmin();
        $this->ensureCanApproveForBranch($transaction, auth()->user(), 'reject');

        try {
            $this->approvalService->validateApprovalEligibility($transaction, auth()->id());

            $stateMachine = new TransactionStateMachine($transaction, $this->auditService);
            if (! $stateMachine->reject($request->input('reason', 'Rejected by manager'))) {
                return back()->with('error', 'Transaction cannot be rejected from its current status.');
            }

            return redirect()->route('transactions.show', $transaction)
                ->with('warning', 'Transaction has been rejected.');
        } catch (SelfApprovalException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Transaction rejection failed', [
                'transaction_id' => $transaction->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Rejection failed due to a system error. Please contact support.');
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
        if (! $this->requiresConfirmation($transaction)) {
            return redirect()->route('transactions.show', $transaction)
                ->with('error', 'This transaction does not require confirmation.');
        }

        $confirmation = TransactionConfirmation::where('transaction_id', $transaction->id)
            ->whereIn('status', [TransactionConfirmationStatus::Pending->value, TransactionConfirmationStatus::Confirmed->value])
            ->first();

        if (! $confirmation) {
            $confirmationToken = bin2hex(random_bytes(32));
            $confirmation = TransactionConfirmation::create([
                'transaction_id' => $transaction->id,
                'user_id' => auth()->id(),
                'status' => TransactionConfirmationStatus::Pending->value,
                'confirmation_token' => $confirmationToken,
                'expires_at' => now()->addMinutes(30),
            ]);

            $this->auditService->logWithSeverity('confirmation_requested', [
                'user_id' => auth()->id(),
                'entity_type' => 'Transaction',
                'entity_id' => $transaction->id,
                'new_values' => [
                    'confirmation_id' => $confirmation->id,
                    'amount_local' => $transaction->amount_local,
                ],
            ], 'INFO');
        }

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
        $this->requireManagerOrAdmin();

        if (! $this->requiresConfirmation($transaction)) {
            return redirect()->route('transactions.show', $transaction)
                ->with('error', 'This transaction does not require confirmation.');
        }

        $confirmation = TransactionConfirmation::where('transaction_id', $transaction->id)
            ->where('status', TransactionConfirmationStatus::Pending->value)
            ->first();

        if (! $confirmation) {
            return redirect()->route('transactions.show', $transaction)
                ->with('error', 'No pending confirmation found.');
        }

        if ($response = $this->ensureNotSelfConfirmation($transaction)) {
            return $response;
        }

        if ($confirmation->isExpired()) {
            $confirmation->markExpired();

            return redirect()->route('transactions.show', $transaction)
                ->with('error', 'Confirmation has expired. Please request a new confirmation.');
        }

        $validated = $request->validated();

        DB::beginTransaction();
        try {
            if ($validated['confirmation_action'] === 'confirm') {
                $confirmation->markConfirmed(auth()->id(), $validated['notes'] ?? null);

                $transaction->refresh();

                $this->auditService->logWithSeverity('transaction_confirmed', [
                    'user_id' => auth()->id(),
                    'entity_type' => 'Transaction',
                    'entity_id' => $transaction->id,
                    'new_values' => [
                        'confirmation_id' => $confirmation->id,
                        'confirmed_by' => auth()->id(),
                    ],
                ], 'INFO');

                DB::commit();

                return redirect()->route('transactions.show', $transaction)
                    ->with('success', 'Transaction confirmed and pending final approval.');

            } else {
                $confirmation->markRejected(auth()->id(), $validated['notes'] ?? null);

                $transaction->update([
                    'status' => TransactionStatus::Cancelled,
                    'cancelled_at' => now(),
                    'cancelled_by' => auth()->id(),
                    'cancellation_reason' => 'Rejected during confirmation: '.($validated['notes'] ?? 'No reason provided'),
                ]);

                $this->auditService->logWithSeverity('transaction_rejected', [
                    'user_id' => auth()->id(),
                    'entity_type' => 'Transaction',
                    'entity_id' => $transaction->id,
                    'new_values' => [
                        'confirmation_id' => $confirmation->id,
                        'rejected_by' => auth()->id(),
                        'reason' => $validated['notes'] ?? 'No reason provided',
                    ],
                ], 'WARNING');

                DB::commit();

                return redirect()->route('transactions.show', $transaction)
                    ->with('warning', 'Transaction has been rejected.');
            }

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Confirmation failed: '.$e->getMessage());
        }
    }

    /**
     * Check if the transaction requires manager confirmation.
     *
     * Confirmation is required when the local amount is greater than or equal
     * to the configured structured-transaction threshold.
     */
    protected function requiresConfirmation(Transaction $transaction): bool
    {
        $threshold = $this->thresholdService->getStrThreshold();

        return $this->mathService->compare($transaction->amount_local, $threshold) >= 0;
    }

    /**
     * Ensure the authenticated user is allowed to manage the transaction branch.
     *
     * Managers can only approve or reject transactions within their own branch.
     * Admins are exempt from this restriction.
     */
    private function ensureCanApproveForBranch(Transaction $transaction, User $user, string $action = 'manage'): void
    {
        if (! $user->isAdmin() && $transaction->branch_id !== $user->branch_id) {
            abort(403, "You can only {$action} transactions for your own branch.");
        }
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
